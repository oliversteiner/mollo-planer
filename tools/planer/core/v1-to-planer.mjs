import { parse } from './parse.mjs';
import { softwareDevLabels } from './builtin-labels.mjs';
import { TITLE_MAX } from './format.mjs';
import { validate } from './validate.mjs';

/**
 * Führt eine v1-Datei (version-<name>.md) nach planer-v2 über. Liefert ein Plan-Objekt (ungeprüft).
 *
 * Abbildung (spec/format-v2.md, Abschnitt 11):
 * - Milestone „## vM.m Titel“ mit Status → Milestone mit release { version, status }.
 * - ToDo, Task → ToDo, Task. Erledigt → status done, offen → open.
 * - Art des Tasks → Label (Schlüssel der Vorlage „Softwareentwicklung“, planer-abgleich 3.7).
 * - Commit → commit. „Ohne Commit“ → commit "none".
 * - Beschreibungen bleiben als Text erhalten.
 * - Projekt: name und locale kommen von außen (der Dateiname liefert keinen Namen),
 *   Mitglieder gibt es in v1 nicht, die Liste bleibt leer. Revision ist 1.
 *
 * Die v1-Datei muss vorher gültig sein. Sonst wird ein Fehler geworfen.
 */
/** v1-Wörter auf die Werte der Datei (planer-abgleich §5.1). */
const V1_RELEASE_STATUS = { geplant: 'planned', veröffentlicht: 'released' };

export function planerFromV1(markdown, { name, locale = 'DE', releaseDates = new Map(), labels: labelOverrides = {}, notes = {} }) {
  const errors = validate(markdown, { convert: true }).filter((issue) => issue.severity === 'error');
  if (errors.length > 0) {
    throw new ConvertError(`Die v1-Datei ist ungültig, Zeile ${errors[0].line}: ${errors[0].message} Zuerst: mollo-planer validate`);
  }

  const data = parse(markdown, { legacyCommit: true, convert: true });
  // IDs sind je Datei eindeutig. Jede Art zählt über die ganze Datei, nicht je Elternteil.
  const counts = { m: 0, t: 0, k: 0 };
  const next = (prefix) => {
    counts[prefix] += 1;
    return sequence(prefix, counts[prefix]);
  };

  notes.shortenedTitles = [];
  notes.multipleCommits = [];
  notes.releasedWithoutDate = [];
  notes.datesNotUsed = [];
  const versions = new Set(data.milestones.map((milestone) => milestone.number));
  notes.datesForUnknownVersions = [...releaseDates.keys()].filter((version) => !versions.has(version));

  const milestones = data.milestones.map((milestone) => {
    const shortened = shortenTitle(milestone.title);
    if (shortened.changed) notes.shortenedTitles.push({ line: milestone.line, what: 'Milestone', old: milestone.title, new: shortened.title });
    const entry = {
      id: next('m'),
      title: shortened.title,
      release: { version: milestone.number, status: V1_RELEASE_STATUS[milestone.status] },
    };
    // Das Datum kennt die v1-Datei nicht: nur aus --release-dates, nur bei veröffentlicht (immer optional).
    const date = releaseDates.get(milestone.number);
    if (entry.release.status === 'released') {
      if (date !== undefined) entry.release.date = date;
      else notes.releasedWithoutDate.push(milestone.number);
    } else if (date !== undefined) {
      notes.datesNotUsed.push(milestone.number);
    }
    const milestoneDescription = withOriginalTitle(shortened, milestone.description, milestone.line);
    if (milestoneDescription) entry.description = milestoneDescription;

    entry.todos = milestone.todos.map((todo) => {
      const shortTodo = shortenTitle(todo.title);
      if (shortTodo.changed) notes.shortenedTitles.push({ line: todo.line, what: 'ToDo', old: todo.title, new: shortTodo.title });
      const todoEntry = { id: next('t'), title: shortTodo.title };
      const todoDescription = withOriginalTitle(shortTodo, todo.description, todo.line);
      if (todoDescription) todoEntry.description = todoDescription;

      todoEntry.tasks = todo.tasks.map((task) => {
        const shortTask = shortenTitle(task.title);
        if (shortTask.changed) notes.shortenedTitles.push({ line: task.line, what: 'Task', old: task.title, new: shortTask.title });
        const taskEntry = {
          id: next('k'),
          title: shortTask.title,
          status: task.done ? 'done' : 'open',
          labels: [task.kind],
        };
        let description = withOriginalTitle(shortTask, task.description, task.line);
        // Mehrere Commits: der letzte Hash ist der commit, die übrigen stehen in der Beschreibung.
        if (task.extraCommits !== undefined) {
          notes.multipleCommits.push({ line: task.line, title: task.title });
          description = [description, `Weitere Commits: ${task.extraCommits.join(', ')}`].filter(Boolean).join('\n\n');
        }
        if (description) taskEntry.description = description;
        if (task.commit !== null) taskEntry.commit = task.commit === 'ohne' ? 'none' : task.commit;
        return taskEntry;
      });
      return todoEntry;
    });

    return entry;
  });

  return {
    format: 'planer-v2',
    revision: 1,
    project: { name, locale: locale.toLowerCase(), members: [], labels: labelsFor(data, labelOverrides, notes) },
    milestones,
  };
}

export class ConvertError extends Error {}

/** Farben für neue Labels, der Reihe nach (Werte des Schemas). */
const NEW_LABEL_COLORS = ['gray', 'orange', 'cyan', 'purple', 'yellow', 'blue', 'green', 'red', 'indigo', 'pink'];
const LABEL_KEY = /^[a-z0-9_-]{1,40}$/;
const LABEL_COLORS = new Set(['gray', 'red', 'orange', 'yellow', 'green', 'cyan', 'blue', 'indigo', 'purple', 'pink']);
/** Kategorie der Arten, die die Vorlage schon kennt (feature, bug, planning): „type“ gibt es also schon, keine neue Ordnung. */
const NEW_LABEL_CATEGORY = 'type';
const MAX_LABELS = 30;
const MAX_DESCRIPTION = 5000;

/**
 * Die Labels der Datei: die der Vorlage „Softwareentwicklung“ und für jede weitere Art der v1-Datei eines (Schlüssel =
 * Art, Name = Art, Kategorie wie die Arten der Vorlage). `--labels` (Objekt Schlüssel → { names, color, category })
 * ändert Namen, Farbe und Kategorie, auch der eingebauten. Mehr als 30 Labels oder ein Schlüssel, der nicht passt, ist ein Fehler.
 */
function labelsFor(data, overrides, notes) {
  const labels = softwareDevLabels();
  const known = new Set(labels.map((label) => label.key));
  const seen = new Map();

  for (const milestone of data.milestones) {
    for (const todo of milestone.todos) {
      for (const task of todo.tasks) {
        if (!known.has(task.kind) && !seen.has(task.kind)) seen.set(task.kind, task.line);
      }
    }
  }

  notes.newLabels = [];
  for (const [kind, line] of seen) {
    if (!LABEL_KEY.test(kind)) {
      throw new ConvertError(`Zeile ${line}: Die Art [${kind}] ist kein gültiger Label-Schlüssel (a-z, 0-9, _ und -, höchstens 40 Zeichen).`);
    }
    labels.push({ key: kind, names: { de: kind, en: kind }, category: NEW_LABEL_CATEGORY, color: NEW_LABEL_COLORS[notes.newLabels.length % NEW_LABEL_COLORS.length] });
    notes.newLabels.push(kind);
  }

  for (const [key, change] of Object.entries(overrides)) {
    const label = labels.find((entry) => entry.key === key);
    if (label === undefined) continue; // eine Art, die die Datei nicht benutzt
    if (change.names !== undefined) label.names = { ...label.names, ...change.names };
    if (change.category !== undefined) label.category = change.category;
    if (change.color !== undefined) {
      if (!LABEL_COLORS.has(change.color)) {
        throw new ConvertError(`--labels: Farbe „${change.color}“ von ${key} gibt es nicht. Erlaubt: ${[...LABEL_COLORS].join(', ')}.`);
      }
      label.color = change.color;
    }
  }

  if (labels.length > MAX_LABELS) {
    throw new ConvertError(`Die Datei braucht ${labels.length} Labels, erlaubt sind ${MAX_LABELS}.`);
  }
  return labels;
}

/**
 * Titel auf höchstens 160 Zeichen, nie mitten im Wort: am letzten Satzende, das hineinpasst; sonst vor dem ersten
 * Doppelpunkt; sonst am letzten Wortende mit „…“. Der ganze Originaltext steht danach in der Beschreibung (withOriginalTitle).
 */
export function shortenTitle(title) {
  if (title.length <= TITLE_MAX) return { title, changed: false };

  const head = title.slice(0, TITLE_MAX + 1);
  const sentenceEnds = [...head.matchAll(/[.!?](?=\s)/g)].map((match) => match.index + 1).filter((end) => end >= 2 && end <= TITLE_MAX);
  if (sentenceEnds.length > 0) {
    return { title: title.slice(0, sentenceEnds[sentenceEnds.length - 1]).trim(), changed: true, original: title };
  }

  const colon = title.indexOf(':');
  if (colon >= 2 && colon <= TITLE_MAX) {
    return { title: title.slice(0, colon).trim(), changed: true, original: title };
  }

  const cut = title.slice(0, TITLE_MAX - 1);
  const space = cut.lastIndexOf(' ');
  return { title: `${(space > 0 ? cut.slice(0, space) : cut).trimEnd()}…`, changed: true, original: title };
}

/** Beschreibung mit dem ganzen Originaltitel als erstem Absatz, wenn der Titel gekürzt wurde (Grenze: 5000 Zeichen). */
function withOriginalTitle(shortened, description, line) {
  if (!shortened.changed) return description;
  const text = [shortened.original, description].filter(Boolean).join('\n\n');
  if (text.length > MAX_DESCRIPTION) {
    throw new ConvertError(`Zeile ${line}: Der ganze Titel und die Beschreibung zusammen sind länger als ${MAX_DESCRIPTION} Zeichen. Nichts wurde gekürzt, bitte die Zeile von Hand ändern.`);
  }
  return text;
}

/**
 * Liest die Datei für --release-dates: je Zeile „Version,Datum“ (JJJJ-MM-TT), eine Kopfzeile „version,date“ ist erlaubt,
 * Leerzeilen und Zeilen mit # am Anfang werden übersprungen. Doppelte Versionen und falsche Formate sind Fehler mit Zeile.
 */
export function parseReleaseDates(csv) {
  const dates = new Map();
  csv.split(/\r?\n/).forEach((raw, index) => {
    const line = raw.trim();
    if (line === '' || line.startsWith('#')) return;
    const [version, date, ...rest] = line.split(/[,;]/).map((part) => part.trim());
    if (index === 0 && /^version$/i.test(version) && /^(date|datum)$/i.test(date ?? '')) return;
    if (rest.length > 0 || date === undefined) {
      throw new ConvertError(`--release-dates, Zeile ${index + 1}: erwartet „Version,Datum“.`);
    }
    if (!/^[0-9]+\.[0-9]+$/.test(version)) {
      throw new ConvertError(`--release-dates, Zeile ${index + 1}: Version „${version}“ ist nicht von der Form M.m.`);
    }
    if (!/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/.test(date) || Number.isNaN(Date.parse(date))) {
      throw new ConvertError(`--release-dates, Zeile ${index + 1}: Datum „${date}“ ist nicht von der Form JJJJ-MM-TT.`);
    }
    if (dates.has(version)) {
      throw new ConvertError(`--release-dates, Zeile ${index + 1}: Version ${version} kommt doppelt vor.`);
    }
    dates.set(version, date);
  });
  return dates;
}

/** Ausgabe einer ID wie m-001, t-012, k-007. */
/** Neue IDs aus der Konvertierung: vorläufig, der Import vergibt die echten (planer-abgleich V1). */
function sequence(prefix, n) {
  return `${prefix}-new-${n}`;
}
