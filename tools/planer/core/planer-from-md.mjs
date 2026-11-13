/**
 * Liest die Markdown-Ansicht einer planer-v2-Datei zurück (Grammatik: spec/format-v2.md, Abschnitt 7).
 * Das Gegenstück zu core/render-planer.mjs. Ergebnis ist ein Plan-Objekt, das anschließend
 * validatePlaner prüft. Fehler nennen die Zeile.
 */

export class MarkdownError extends Error {
  constructor(line, message) {
    super(`Zeile ${line}: ${message}`);
    this.line = line;
  }
}

import { LANGUAGES } from './planer-words.mjs';

const HEADER = /^<!-- Ansicht von planer-v2, revision (\d+)(, geändert)?, lang (de|en)\./;
const MILESTONE_VERSION = /^## v(\d+\.\d+) (.+)$/;
const MILESTONE_PLAIN = /^## (.+)$/;
const TODO = /^### (.+)$/;
const TASK = /^- \[( |~|\?|x|i|p)\] (.+?) <!-- id: (k-\d{3})(?:, app: (\d+))? -->$/;
const ID_LINE = /^<!-- id: ([mt]-\d{3})(?:, app: (\d+))? -->$/;
const STORY = /^ {2}\*(?:Als (.+?) möchte ich (.+?), damit (.+)|As (.+?) I want (.+?), so that (.+))\.\*$/;
const MEMBER = /^- (\S+) \| (owner|manager|member)$/;
const MARKS = { i: 'idea', p: 'planned', ' ': 'open', '~': 'in_progress', '?': 'review', x: 'done' };
/** Feste Wörter der Ansicht in beiden Sprachen auf die Werte der Datei (planer-words.mjs). */
const STATUS_KEYS = { geplant: 'planned', veröffentlicht: 'released', planned: 'planned', released: 'released' };
/** Schlüsselwörter der Metadaten in beiden Sprachen auf die deutschen Namen, die der Leser benutzt. */
const KEY_ALIASES = { Due: 'Fällig', Assignee: 'Zuständig' };

/** Wandelt die Markdown-Ansicht in ein Plan-Objekt (ungeprüft). */
export function planerFromMarkdown(markdown) {
  const lines = markdown.split(/\r?\n/);
  const reader = new Reader(lines);

  const { revision, changed, lang } = readRevision(reader);
  const project = readProject(reader);
  const milestones = [];

  reader.skipBlank();
  while (!reader.done()) {
    if (reader.peek() !== '---') {
      throw new MarkdownError(reader.number(), 'Erwartet „---“ vor einem Milestone.');
    }
    reader.next();
    reader.skipBlank();
    if (reader.peek() === '## Labels') {
      break;
    }
    milestones.push(readMilestone(reader));
    reader.skipBlank();
  }

  const labels = readLegend(reader, { lang, projectLang: project.locale });
  if (labels.length > 0) {
    project.labels = labels;
  }
  for (const milestone of milestones) {
    for (const todo of milestone.todos) {
      for (const task of todo.tasks) {
        resolveTaskLabels(task, labels);
      }
    }
  }

  return { format: 'planer-v2', revision, ...(changed ? { changed: true } : {}), project, milestones };
}

class Reader {
  constructor(lines) {
    this.lines = lines;
    this.index = 0;
  }

  done() {
    return this.index >= this.lines.length;
  }

  peek() {
    return this.done() ? undefined : this.lines[this.index];
  }

  next() {
    const line = this.lines[this.index];
    this.index += 1;
    return line;
  }

  number() {
    return this.index + 1;
  }

  skipBlank() {
    while (!this.done() && this.peek().trim() === '') {
      this.index += 1;
    }
  }

  /** Sammelt Zeilen, bis die Bedingung zutrifft. Abschließende Leerzeilen gehen nicht mit. */
  collectUntil(stop) {
    const block = [];
    while (!this.done() && !stop(this.peek())) {
      block.push(this.next());
    }
    while (block.length > 0 && block[block.length - 1].trim() === '') {
      block.pop();
    }
    return block;
  }
}

/**
 * Die Legende „## Labels“ am Schluss. Fehlt sie, gibt es keine Labels.
 * Spalten: Schlüssel, Name (Sprache der Ansicht), je weitere Sprache eine Spalte, Kategorie, Farbe.
 */
function readLegend(reader, { lang, projectLang }) {
  reader.skipBlank();
  if (reader.done()) {
    return [];
  }
  if (reader.peek() !== '## Labels') {
    throw new MarkdownError(reader.number(), 'Nach dem letzten Milestone ist nur die Legende „## Labels“ erlaubt.');
  }
  reader.next();
  reader.skipBlank();

  const headerNumber = reader.number();
  const header = splitRow(reader.next());
  if (header.length < 4 || header.slice(2, -2).some((code) => !LANGUAGES.includes(code))) {
    throw new MarkdownError(headerNumber, 'Kopf der Legende: Schlüssel, Name, optional Sprachcodes (de, en), Kategorie, Farbe.');
  }
  if (reader.peek() === undefined || !/^\|(---\|)+$/.test(reader.next().trim()) ) {
    throw new MarkdownError(reader.number() - 1, 'Trennzeile der Legende erwartet: „|---|…|“, eine Spalte je Kopf.');
  }

  const languageColumns = header.slice(2, -2).map((code, index) => [code, index + 2]);
  const labels = [];
  while (!reader.done() && reader.peek().startsWith('|')) {
    const number = reader.number();
    const cells = splitRow(reader.next());
    if (cells.length !== header.length) {
      throw new MarkdownError(number, 'Eine Zeile der Legende hat nicht so viele Spalten wie ihr Kopf.');
    }
    const names = {};
    for (const [code, column] of [[lang, 1], ...languageColumns]) {
      if (cells[column] !== '–' && cells[column] !== '') {
        names[code] = cells[column];
      }
    }
    if (names[projectLang] === undefined) {
      throw new MarkdownError(number, `Der Name in der Sprache des Projekts (${projectLang}) fehlt in der Legende.`);
    }
    const [key, , , ...rest] = cells;
    const category = rest[rest.length - 2];
    const color = rest[rest.length - 1];
    labels.push({ key, names, ...(category ? { category } : {}), color });
  }

  reader.skipBlank();
  if (!reader.done()) {
    throw new MarkdownError(reader.number(), 'Nach der Legende folgt nichts mehr.');
  }
  return labels;
}

/** Zerlegt eine Tabellenzeile „| a | b |“ in Zellen ohne Rand. */
function splitRow(line) {
  return (line ?? '').split('|').slice(1, -1).map((cell) => cell.trim());
}

/** Am Task stehen die Namen der Labels (Zeile „Labels:“), die Datei braucht die Schlüssel. */
function resolveTaskLabels(task, labels) {
  if (task.labelNames === undefined) {
    return;
  }
  const keys = task.labelNames.map((name) => {
    const label = labels.find((candidate) => Object.values(candidate.names).includes(name));
    if (!label) {
      throw new MarkdownError(task.labelNamesLine, `Label „${name}“ steht nicht in der Legende „## Labels“.`);
    }
    return label.key;
  });
  task.labels = keys;
  delete task.labelNames;
  delete task.labelNamesLine;
}

function readRevision(reader) {
  while (!reader.done()) {
    const line = reader.next();
    const match = line.match(HEADER);
    if (match) {
      return { revision: Number(match[1]), changed: match[2] !== undefined, lang: match[3] };
    }
    if (line.trim() !== '') {
      break;
    }
  }
  throw new MarkdownError(1, 'Kopfzeile „<!-- Ansicht von planer-v2, revision N, lang de. … -->“ fehlt.');
}

function readProject(reader) {
  reader.skipBlank();
  const titleLine = reader.next();
  const title = titleLine?.match(/^# (.+)$/);
  if (!title) {
    throw new MarkdownError(reader.number() - 1, 'Erwartet „# Projektname“.');
  }

  reader.skipBlank();
  const locale = reader.next()?.match(/^- (?:Sprache|Language): (de|en)$/);
  if (!locale) {
    throw new MarkdownError(reader.number() - 1, 'Erwartet „- Sprache: de“ oder „- Language: en“.');
  }

  reader.skipBlank();
  const description = reader.collectUntil((line) => line === '## Mitglieder' || line === '## Members');
  if (reader.done()) {
    throw new MarkdownError(reader.number(), 'Abschnitt „## Mitglieder“ (oder „## Members“) fehlt.');
  }
  reader.next();

  reader.skipBlank();
  const members = [];
  while (!reader.done() && reader.peek().trim() !== '' && reader.peek() !== '---') {
    const line = reader.next();
    const member = line.match(MEMBER);
    if (!member) {
      throw new MarkdownError(reader.number() - 1, 'Mitglied erwartet: „- <E-Mail> | <Rolle>“.');
    }
    members.push({ email: member[1], role: member[2] });
    reader.skipBlank();
  }

  return {
    name: title[1],
    description: description.length > 0 ? description.join('\n') : undefined,
    locale: locale[1],
    members,
  };
}

function readMilestone(reader) {
  const number = reader.number();
  const heading = reader.next();
  const version = heading.match(MILESTONE_VERSION);
  const plain = version ? null : heading.match(MILESTONE_PLAIN);
  if (!version && !plain) {
    throw new MarkdownError(number, 'Milestone erwartet: „## vM.m Titel“ oder „## Titel“.');
  }

  reader.skipBlank();
  const { id, appId } = readIdLine(reader, 'm');
  reader.skipBlank();

  const milestone = { id, title: version ? version[2] : plain[1] };
  if (appId !== undefined) milestone.appId = appId;

  const meta = readMeta(reader, ['Status', 'Fällig', 'Due']);
  if (meta.Status !== undefined) {
    if (!version) {
      throw new MarkdownError(number, 'Status gibt es nur bei einem Milestone mit Version.');
    }
    if (STATUS_KEYS[meta.Status] === undefined) {
      throw new MarkdownError(number, `Unbekannter Status „${meta.Status}“.`);
    }
    milestone.release = { version: version[1], status: STATUS_KEYS[meta.Status] };
  } else if (version) {
    milestone.release = { version: version[1], status: 'geplant' };
  }
  if (meta['Fällig'] !== undefined) milestone.dueDate = meta['Fällig'];

  reader.skipBlank();
  const description = reader.collectUntil(isStructure);
  if (description.length > 0) milestone.description = description.join('\n');
  reader.skipBlank();

  milestone.todos = [];
  while (!reader.done() && reader.peek().startsWith('### ')) {
    milestone.todos.push(readTodo(reader));
    reader.skipBlank();
  }
  return milestone;
}

function readTodo(reader) {
  const number = reader.number();
  const title = reader.next().match(TODO);
  if (!title) throw new MarkdownError(number, 'ToDo erwartet: „### Titel“.');

  reader.skipBlank();
  const { id, appId } = readIdLine(reader, 't');
  reader.skipBlank();

  const todo = { id, title: title[1] };
  if (appId !== undefined) todo.appId = appId;

  const meta = readMeta(reader, ['Fällig', 'Due']);
  if (meta['Fällig'] !== undefined) todo.dueDate = meta['Fällig'];

  reader.skipBlank();
  const description = reader.collectUntil(isStructure);
  if (description.length > 0) todo.description = description.join('\n');
  reader.skipBlank();

  todo.tasks = [];
  while (!reader.done() && reader.peek().startsWith('- [')) {
    todo.tasks.push(readTask(reader));
    reader.skipBlank();
  }
  return todo;
}

function readTask(reader) {
  const number = reader.number();
  const match = reader.next().match(TASK);
  if (!match) {
    throw new MarkdownError(number, 'Task nicht lesbar. Erwartet: „- [ ] Titel <!-- id: k-NNN -->“.');
  }

  const task = {
    id: match[3],
    title: match[2],
    status: MARKS[match[1]],
  };
  if (match[4] !== undefined) task.appId = Number(match[4]);

  // Der Block unter dem Task: Story, Metadaten, Beschreibung. Jeder Teil durch Leerzeilen getrennt.
  reader.skipBlank();
  if (!reader.done() && /^ {2}\*(?:Als|As) /.test(reader.peek())) {
    const storyNumber = reader.number();
    const story = reader.next().match(STORY);
    if (!story) {
      throw new MarkdownError(storyNumber, 'Story passt nicht zum Muster „*Als … möchte ich …, damit ….*“.');
    }
    task.userStory = story[1] !== undefined
      ? { role: story[1], goal: story[2], benefit: story[3] }
      : { role: story[4], goal: story[5], benefit: story[6] };
    reader.skipBlank();
  }

  const meta = readTaskMeta(reader);
  if (meta.dueDate !== undefined) task.dueDate = meta.dueDate;
  if (meta.assigneeEmail !== undefined) task.assigneeEmail = meta.assigneeEmail;
  if (meta.labelNames !== undefined) {
    task.labelNames = meta.labelNames;
    task.labelNamesLine = meta.labelNamesLine;
  }
  if (meta.commit !== undefined) task.commit = meta.commit;
  reader.skipBlank();

  const block = [];
  while (!reader.done() && (reader.peek().startsWith('  ') || reader.peek().trim() === '')) {
    if (reader.peek().trim() === '') {
      reader.next();
      block.push('');
    } else {
      block.push(reader.next().slice(2));
    }
  }
  while (block.length > 0 && block[block.length - 1] === '') block.pop();
  if (block.length > 0) task.description = block.join('\n');

  return task;
}

/** Metadaten eines Tasks: eingerückte Aufzählung, Schlüssel fest. */
function readTaskMeta(reader) {
  const meta = {};
  while (!reader.done()) {
    // Nur bekannte Schlüssel gehören zu den Metadaten. Alles andere ist Beschreibung, z. B. eine Liste.
    const match = reader.peek().match(/^ {2}- (Fällig|Due|Zuständig|Assignee|Labels|Commit): (.*)$/);
    if (!match) break;
    const lineNumber = reader.number();
    reader.next();
    const [, rawKey, value] = match;
    const key = KEY_ALIASES[rawKey] ?? rawKey;
    if (key === 'Fällig') meta.dueDate = value;
    if (key === 'Zuständig') meta.assigneeEmail = value;
    if (key === 'Labels') {
      meta.labelNames = value.split(', ');
      meta.labelNamesLine = lineNumber;
    }
    if (key === 'Commit') meta.commit = value === 'Ohne Commit' || value === 'None' ? 'none' : value;
    reader.skipBlank();
  }
  return meta;
}

/** Metadaten von Milestone und ToDo: nicht eingerückte Aufzählung mit festen Schlüsseln. */
function readMeta(reader, keys) {
  const meta = {};
  while (!reader.done() && /^- /.test(reader.peek())) {
    const line = reader.peek();
    const match = line.match(/^- (Status|Fällig|Due): (.*)$/);
    if (!match || !keys.includes(match[1])) break;
    reader.next();
    meta[KEY_ALIASES[match[1]] ?? match[1]] = match[2];
    reader.skipBlank();
  }
  return meta;
}

function readIdLine(reader, prefix) {
  const number = reader.number();
  const line = reader.next();
  const match = line?.match(ID_LINE);
  if (!match || !match[1].startsWith(`${prefix}-`)) {
    throw new MarkdownError(number, `ID-Zeile erwartet: „<!-- id: ${prefix}-NNN -->“.`);
  }
  return { id: match[1], appId: match[2] === undefined ? undefined : Number(match[2]) };
}

/** Zeilen, die die Struktur beginnen: Milestone, ToDo, Task, Trennlinie. */
function isStructure(line) {
  return line === '---' || line.startsWith('## ') || line.startsWith('### ') || line.startsWith('- [');
}
