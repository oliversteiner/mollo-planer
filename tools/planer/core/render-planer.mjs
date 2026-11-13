/**
 * Markdown-Ansicht einer geprüften Planer-Datei (planer/2). Die Grammatik steht in
 * spec/format-v2.md, Abschnitt 7. Der Renderer erzeugt nur Text. Die Datei bleibt die Quelle.
 */
import { LANGUAGES, WORDS } from './planer-words.mjs';

const MARKS = { idea: 'i', planned: 'p', open: ' ', in_progress: '~', review: '?', done: 'x' };

/**
 * Rendert den Plan als Markdown. Blöcke sind durch Leerzeilen getrennt.
 *
 * Optionen:
 * - lang: Sprache der festen Wörter und der Label-Namen (Standard: Sprache des Projekts).
 * - legendTranslations: fügt in der Legende je weitere Sprache eine Spalte hinzu.
 *
 * Wirft einen Fehler, wenn eine Beschreibung oder ein Label die Struktur der Ansicht zerstören würde.
 */
export function renderPlaner(plan, { lang = plan.project.locale, legendTranslations = false } = {}) {
  const projectLang = plan.project.locale;
  const words = WORDS[lang];
  const labels = plan.project.labels ?? [];
  const names = new Map(labels.map((label) => [label.key, labelName(label, lang, projectLang)]));

  for (const label of labels) {
    for (const text of [...Object.values(label.names), label.category ?? '']) {
      if (/[|,]/.test(text)) {
        throw new Error(`Label „${label.key}“: Namen und Kategorien dürfen weder „|“ noch „,“ enthalten, sonst bricht die Ansicht.`);
      }
    }
  }

  const chunks = [
    `<!-- Ansicht von planer/2, revision ${plan.revision}${plan.changed === true ? ', geändert' : ''}, lang ${lang}. Änderungen nur über mollo-planer (convert md2json oder die Helfer), nicht von Hand. -->`,
    `# ${plan.project.name}`,
    `- ${words.language}: ${projectLang}`,
  ];

  if (plan.project.description) {
    chunks.push(plan.project.description);
  }

  chunks.push(`## ${words.members}`);
  if (plan.project.members.length > 0) {
    chunks.push(plan.project.members.map((member) => `- ${member.email} | ${member.role}`).join('\n'));
  }

  for (const milestone of plan.milestones) {
    chunks.push('---', ...milestoneChunks(milestone, { words, names, lang }));
  }

  if (labels.length > 0) {
    chunks.push('---', legendChunk(labels, { words, lang, projectLang, legendTranslations }));
  }

  return `${chunks.join('\n\n')}\n`;
}

/** Name eines Labels in der gewünschten Sprache, sonst in der Sprache des Projekts. */
export function labelName(label, lang, projectLang) {
  return label.names[lang] ?? label.names[projectLang];
}

function milestoneChunks(milestone, { words, names, lang }) {
  const heading = milestone.release
    ? `## v${milestone.release.version} ${milestone.title}`
    : `## ${milestone.title}`;
  const chunks = [`${heading}\n${idComment(milestone)}`];

  const meta = [];
  if (milestone.release) {
    meta.push(`- Status: ${words.status[milestone.release.status]}`);
  }
  if (milestone.dueDate) {
    meta.push(`- ${words.due}: ${milestone.dueDate}`);
  }
  if (meta.length > 0) {
    chunks.push(meta.join('\n'));
  }

  if (milestone.description) {
    chunks.push(plainBlock(milestone.description, `Milestone ${milestone.id}`));
  }

  for (const todo of milestone.todos) {
    chunks.push(...todoChunks(todo, { words, names, lang }));
  }

  return chunks;
}

function todoChunks(todo, { words, names, lang }) {
  const chunks = [`### ${todo.title}\n${idComment(todo)}`];

  if (todo.dueDate) {
    chunks.push(`- ${words.due}: ${todo.dueDate}`);
  }

  if (todo.description) {
    chunks.push(plainBlock(todo.description, `ToDo ${todo.id}`));
  }

  for (const task of todo.tasks) {
    chunks.push(...taskChunks(task, { words, names, lang }));
  }

  return chunks;
}

function taskChunks(task, { words, names, lang }) {
  const chunks = [`- [${MARKS[task.status]}] ${task.title} ${idComment(task)}`];

  if (task.userStory) {
    const story = task.userStory;
    chunks.push(indent(`*${words.story(story.role, story.goal, story.benefit)}*`));
  }

  const meta = [];
  if (task.dueDate) {
    meta.push(`${words.due}: ${task.dueDate}`);
  }
  if (task.assigneeEmail) {
    meta.push(`${words.assignee}: ${task.assigneeEmail}`);
  }
  if (task.labels && task.labels.length > 0) {
    meta.push(`${words.labels}: ${task.labels.map((key) => names.get(key)).join(', ')}`);
  }
  if (task.commit) {
    meta.push(`${words.commit}: ${task.commit === 'none' ? words.noCommit : task.commit}`);
  }
  if (meta.length > 0) {
    chunks.push(meta.map((line) => `  - ${line}`).join('\n'));
  }

  if (task.description) {
    chunks.push(indent(task.description));
  }

  return chunks;
}

/**
 * Legende der Labels am Schluss der Ansicht: die einzige Tabelle der Ansicht.
 * Die Namensspalte zeigt die gewählte Sprache. Ist die Sprache eine andere als die des Projekts,
 * folgt die Projektsprache immer als Spalte, damit die Datei beim Zurücklesen vollständig bleibt.
 * Mit legendTranslations folgt je weitere Sprache eine Spalte mit dem Code als Kopf. „–“ fehlt die Übersetzung.
 */
function legendChunk(labels, { words, lang, projectLang, legendTranslations }) {
  const others = LANGUAGES.filter((code) => code !== lang && (code === projectLang || legendTranslations));
  const header = [words.legendKey, words.legendName, ...others, words.legendCategory, words.legendColor];
  const rows = labels.map((label) => [
    label.key,
    labelName(label, lang, projectLang),
    ...others.map((code) => label.names[code] ?? '–'),
    label.category ?? '',
    label.color,
  ]);
  const line = (cells) => `| ${cells.join(' | ')} |`;
  return ['## ' + words.labels, line(header), `|${header.map(() => '---').join('|')}|`, ...rows.map(line)].join('\n');
}

/** Die ID-Zeile unter Milestone- und ToDo-Überschrift, am Task das Zeilenende. */
function idComment(node) {
  const app = node.appId === null || node.appId === undefined ? '' : `, app: ${node.appId}`;
  return `<!-- id: ${node.id}${app} -->`;
}

/** Beschreibung ohne Einrückung, wie im Beispiel. Zeilen, die die Struktur brechen, sind ein Fehler. */
function plainBlock(text, owner) {
  for (const line of text.split('\n')) {
    if (line.startsWith('#') || line.startsWith('- [')) {
      throw new Error(`Beschreibung von ${owner} hat eine Zeile, die als Struktur gelesen würde: „${line.slice(0, 40)}“. Einrücken oder umformulieren.`);
    }
  }
  return text;
}

function indent(text) {
  return text
    .split('\n')
    .map((line) => (line === '' ? '' : `  ${line}`))
    .join('\n');
}
