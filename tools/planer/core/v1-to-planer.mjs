import { parse } from './parse.mjs';
import { softwareDevLabels } from './builtin-labels.mjs';
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

export function planerFromV1(markdown, { name, locale = 'DE', releaseDates = new Map(), notes = {} }) {
  const errors = validate(markdown).filter((issue) => issue.severity === 'error');
  if (errors.length > 0) {
    throw new ConvertError(`Die v1-Datei ist ungültig, Zeile ${errors[0].line}: ${errors[0].message} Zuerst: mollo-planer validate`);
  }

  const data = parse(markdown, { legacyCommit: true });
  // IDs sind je Datei eindeutig. Jede Art zählt über die ganze Datei, nicht je Elternteil.
  const counts = { m: 0, t: 0, k: 0 };
  const next = (prefix) => {
    counts[prefix] += 1;
    return sequence(prefix, counts[prefix]);
  };

  notes.releasedWithoutDate = [];
  notes.datesNotUsed = [];
  const versions = new Set(data.milestones.map((milestone) => milestone.number));
  notes.datesForUnknownVersions = [...releaseDates.keys()].filter((version) => !versions.has(version));

  const milestones = data.milestones.map((milestone) => {
    const entry = {
      id: next('m'),
      title: milestone.title,
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
    if (milestone.description) entry.description = milestone.description;

    entry.todos = milestone.todos.map((todo) => {
      const todoEntry = { id: next('t'), title: todo.title };
      if (todo.description) todoEntry.description = todo.description;

      todoEntry.tasks = todo.tasks.map((task) => {
        const taskEntry = {
          id: next('k'),
          title: task.title,
          status: task.done ? 'done' : 'open',
          labels: [task.kind],
        };
        if (task.description) taskEntry.description = task.description;
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
    project: { name, locale: locale.toLowerCase(), members: [], labels: softwareDevLabels() },
    milestones,
  };
}

export class ConvertError extends Error {}

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
