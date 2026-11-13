/**
 * Markdown-Ansicht einer geprüften Planer-Datei (planer/2). Die Grammatik steht in
 * spec/format-v2.md, Abschnitt 7. Der Renderer erzeugt nur Text. Die Datei bleibt die Quelle.
 */

const MARKS = { open: ' ', in_progress: '~', review: '?', done: 'x' };
const COMMIT_OHNE = 'ohne';

/**
 * Rendert den Plan als Markdown. Blöcke sind durch Leerzeilen getrennt.
 * Wirft einen Fehler, wenn eine Beschreibung die Struktur der Ansicht zerstören würde.
 */
export function renderPlaner(plan) {
  const chunks = [
    `<!-- Ansicht von planer/2, revision ${plan.revision}. Änderungen nur über mollo-planer (convert md2json oder die Helfer), nicht von Hand. -->`,
    `# ${plan.project.name}`,
    `- Sprache: ${plan.project.locale}`,
  ];

  if (plan.project.description) {
    chunks.push(plan.project.description);
  }

  chunks.push('## Mitglieder');
  if (plan.project.members.length > 0) {
    chunks.push(plan.project.members.map((member) => `- ${member.email} | ${member.role}`).join('\n'));
  }

  for (const milestone of plan.milestones) {
    chunks.push('---', ...milestoneChunks(milestone));
  }

  return `${chunks.join('\n\n')}\n`;
}

function milestoneChunks(milestone) {
  const heading = milestone.release
    ? `## v${milestone.release.version} ${milestone.title}`
    : `## ${milestone.title}`;
  const chunks = [`${heading}\n${idComment(milestone)}`];

  const meta = [];
  if (milestone.release) {
    meta.push(`- Status: ${milestone.release.status}`);
  }
  if (milestone.dueDate) {
    meta.push(`- Fällig: ${milestone.dueDate}`);
  }
  if (meta.length > 0) {
    chunks.push(meta.join('\n'));
  }

  if (milestone.description) {
    chunks.push(plainBlock(milestone.description, `Milestone ${milestone.id}`));
  }

  for (const todo of milestone.todos) {
    chunks.push(...todoChunks(todo));
  }

  return chunks;
}

function todoChunks(todo) {
  const chunks = [`### ${todo.title}\n${idComment(todo)}`];

  if (todo.dueDate) {
    chunks.push(`- Fällig: ${todo.dueDate}`);
  }

  if (todo.description) {
    chunks.push(plainBlock(todo.description, `ToDo ${todo.id}`));
  }

  for (const task of todo.tasks) {
    chunks.push(...taskChunks(task));
  }

  return chunks;
}

function taskChunks(task) {
  const chunks = [`- [${MARKS[task.status]}] ${task.title} ${idComment(task)}`];

  if (task.userStory) {
    const story = task.userStory;
    chunks.push(indent(`*Als ${story.role} möchte ich ${story.goal}, damit ${story.benefit}.*`));
  }

  const meta = [];
  if (task.dueDate) {
    meta.push(`Fällig: ${task.dueDate}`);
  }
  if (task.assigneeEmail) {
    meta.push(`Zuständig: ${task.assigneeEmail}`);
  }
  if (task.labelNames && task.labelNames.length > 0) {
    meta.push(`Labels: ${task.labelNames.join(', ')}`);
  }
  if (task.commit) {
    meta.push(`Commit: ${task.commit === COMMIT_OHNE ? 'Ohne Commit' : task.commit}`);
  }
  if (meta.length > 0) {
    chunks.push(meta.map((line) => `  - ${line}`).join('\n'));
  }

  if (task.description) {
    chunks.push(indent(task.description));
  }

  return chunks;
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
