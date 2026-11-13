/**
 * Liest die Markdown-Ansicht einer planer/2-Datei zurück (Grammatik: spec/format-v2.md, Abschnitt 7).
 * Das Gegenstück zu core/render-planer.mjs. Ergebnis ist ein Plan-Objekt, das anschließend
 * validatePlaner prüft. Fehler nennen die Zeile.
 */

export class MarkdownError extends Error {
  constructor(line, message) {
    super(`Zeile ${line}: ${message}`);
    this.line = line;
  }
}

const HEADER = /^<!-- Ansicht von planer\/2, revision (\d+)\./;
const MILESTONE_VERSION = /^## v(\d+\.\d+) (.+)$/;
const MILESTONE_PLAIN = /^## (.+)$/;
const TODO = /^### (.+)$/;
const TASK = /^- \[( |~|\?|x)\] (.+?) <!-- id: (k-\d{3})(?:, app: (\d+))? -->$/;
const ID_LINE = /^<!-- id: ([mt]-\d{3})(?:, app: (\d+))? -->$/;
const STORY = /^ {2}\*Als (.+?) möchte ich (.+?), damit (.+)\.\*$/;
const MEMBER = /^- (\S+) \| (owner|manager|member)$/;
const MARKS = { ' ': 'open', '~': 'in_progress', '?': 'review', x: 'done' };
const RELEASE_STATUSES = ['geplant', 'veröffentlicht'];

/** Wandelt die Markdown-Ansicht in ein Plan-Objekt (ungeprüft). */
export function planerFromMarkdown(markdown) {
  const lines = markdown.split(/\r?\n/);
  const reader = new Reader(lines);

  const revision = readRevision(reader);
  const project = readProject(reader);
  const milestones = [];

  reader.skipBlank();
  while (!reader.done()) {
    if (reader.peek() !== '---') {
      throw new MarkdownError(reader.number(), 'Erwartet „---“ vor einem Milestone.');
    }
    reader.next();
    reader.skipBlank();
    milestones.push(readMilestone(reader));
    reader.skipBlank();
  }

  return { format: 'planer/2', revision, project, milestones };
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

function readRevision(reader) {
  while (!reader.done()) {
    const line = reader.next();
    const match = line.match(HEADER);
    if (match) {
      return Number(match[1]);
    }
    if (line.trim() !== '') {
      break;
    }
  }
  throw new MarkdownError(1, 'Kopfzeile „<!-- Ansicht von planer/2, revision N. … -->“ fehlt.');
}

function readProject(reader) {
  reader.skipBlank();
  const titleLine = reader.next();
  const title = titleLine?.match(/^# (.+)$/);
  if (!title) {
    throw new MarkdownError(reader.number() - 1, 'Erwartet „# Projektname“.');
  }

  reader.skipBlank();
  const locale = reader.next()?.match(/^- Sprache: (DE|EN)$/);
  if (!locale) {
    throw new MarkdownError(reader.number() - 1, 'Erwartet „- Sprache: DE“ oder „- Sprache: EN“.');
  }

  reader.skipBlank();
  const description = reader.collectUntil((line) => line === '## Mitglieder');
  if (reader.done()) {
    throw new MarkdownError(reader.number(), 'Abschnitt „## Mitglieder“ fehlt.');
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

  const meta = readMeta(reader, ['Status', 'Fällig']);
  if (meta.Status !== undefined) {
    if (!version) {
      throw new MarkdownError(number, 'Status gibt es nur bei einem Milestone mit Version.');
    }
    if (!RELEASE_STATUSES.includes(meta.Status)) {
      throw new MarkdownError(number, `Unbekannter Status „${meta.Status}“.`);
    }
    milestone.release = { version: version[1], status: meta.Status };
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

  const meta = readMeta(reader, ['Fällig']);
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
  if (!reader.done() && reader.peek().startsWith('  *Als ')) {
    const storyNumber = reader.number();
    const story = reader.next().match(STORY);
    if (!story) {
      throw new MarkdownError(storyNumber, 'Story passt nicht zum Muster „*Als … möchte ich …, damit ….*“.');
    }
    task.userStory = { role: story[1], goal: story[2], benefit: story[3] };
    reader.skipBlank();
  }

  const meta = readTaskMeta(reader);
  if (meta.dueDate !== undefined) task.dueDate = meta.dueDate;
  if (meta.assigneeEmail !== undefined) task.assigneeEmail = meta.assigneeEmail;
  if (meta.labelNames !== undefined) task.labelNames = meta.labelNames;
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
    const match = reader.peek().match(/^ {2}- (Fällig|Zuständig|Labels|Commit): (.*)$/);
    if (!match) break;
    reader.next();
    const [, key, value] = match;
    if (key === 'Fällig') meta.dueDate = value;
    if (key === 'Zuständig') meta.assigneeEmail = value;
    if (key === 'Labels') meta.labelNames = value.split(', ');
    if (key === 'Commit') meta.commit = value === 'Ohne Commit' ? 'ohne' : value;
    reader.skipBlank();
  }
  return meta;
}

/** Metadaten von Milestone und ToDo: nicht eingerückte Aufzählung mit festen Schlüsseln. */
function readMeta(reader, keys) {
  const meta = {};
  while (!reader.done() && /^- /.test(reader.peek())) {
    const line = reader.peek();
    const match = line.match(/^- (Status|Fällig): (.*)$/);
    if (!match || !keys.includes(match[1])) break;
    reader.next();
    meta[match[1]] = match[2];
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
