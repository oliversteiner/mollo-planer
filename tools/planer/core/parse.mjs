import { DEFAULT_STATUS, KINDS, STATUSES } from './format.mjs';

/**
 * Reads a mollo-planer Markdown file into milestones, todos and tasks.
 *
 * Call validate() first: parse() throws on the first structural error it meets,
 * so it can be run on a file that has passed validation.
 *
 * Result shape:
 * {
 *   milestones: [{
 *     major, minor, number ("1.4"), title, status ("geplant" | "veröffentlicht"), description,
 *     todos: [{ title, description, tasks: [{ title, description, done, kind, commit }] }]
 *   }]
 * }
 */

const MILESTONE = /^## v(\d+)\.(\d+)\s+(.+)$/;
const TODO = /^### (.+)$/;
const TASK = /^- \[( |x)\] \[(\w+)\] (.+)$/;
const COMMIT = /^ {2}Commit ([0-9a-f]{7,40})$/;

/**
 * Options: legacyCommit: true setzt den Commit eines Altbestand-Tasks („  Ohne Commit“)
 * auf 'ohne' statt auf null. Der Standard bleibt unverändert, damit `json` gleich bleibt.
 */
export function parse(markdown, options = {}) {
  const milestones = [];
  let context = null; // 'milestone' | 'todo' | 'task' | null
  let inMilestone = false;
  let inTodo = false;
  let inTask = false;
  let contentSeen = false; // anything but the status line seen under the current milestone

  const lines = markdown.split(/\r?\n|\r/);

  lines.forEach((raw, index) => {
    const number = index + 1;
    const line = raw.replace(/\s+$/, '');

    const milestone = line.match(MILESTONE);
    if (milestone) {
      milestones.push({
        major: Number(milestone[1]),
        minor: Number(milestone[2]),
        number: `${Number(milestone[1])}.${Number(milestone[2])}`,
        title: milestone[3].trim(),
        status: DEFAULT_STATUS,
        description: null,
        todos: [],
      });
      contentSeen = false;
      inMilestone = true;
      inTodo = false;
      inTask = false;
      context = 'milestone';
      return;
    }

    if (line.startsWith('## ')) {
      inMilestone = false;
      inTodo = false;
      inTask = false;
      context = null;
      return;
    }

    if (!inMilestone) {
      return;
    }

    const current = milestones[milestones.length - 1];

    const statusLine = line.match(/^Status:\s*(.*)$/);
    if (statusLine && !contentSeen && current.todos.length === 0) {
      const value = statusLine[1].trim();
      if (STATUSES.includes(value)) {
        current.status = value;
      }
      contentSeen = true;
      return;
    }

    const todo = line.match(TODO);
    if (todo) {
      contentSeen = true;
      current.todos.push({ title: todo[1].trim(), description: null, tasks: [] });
      inTodo = true;
      inTask = false;
      context = 'todo';
      return;
    }

    const task = line.match(TASK);
    if (task) {
      contentSeen = true;
      if (!inTodo) {
        throw new Error(`Zeile ${number}: Task steht außerhalb eines ToDos.`);
      }
      if (!KINDS.includes(task[2])) {
        throw new Error(`Zeile ${number}: unbekannte Art [${task[2]}].`);
      }
      const todoEntry = current.todos[current.todos.length - 1];
      todoEntry.tasks.push({
        title: task[3].trim(),
        description: null,
        done: task[1] === 'x',
        kind: task[2],
        commit: null,
      });
      inTask = true;
      context = 'task';
      return;
    }

    if (line.trim() === '') {
      return;
    }

    if (line === '  Ohne Commit' && inTask) {
      if (options.legacyCommit) {
        const todoEntry = current.todos[current.todos.length - 1];
        todoEntry.tasks[todoEntry.tasks.length - 1].commit = 'ohne';
      }
      return; // Legacy task without commit: commit stays null unless legacyCommit is set.
    }

    const commit = line.match(COMMIT);
    if (commit && inTask) {
      const todoEntry = current.todos[current.todos.length - 1];
      todoEntry.tasks[todoEntry.tasks.length - 1].commit = commit[1];
      return;
    }

    const text = line.trim();
    contentSeen = true;

    if (inTask && line.startsWith('  ')) {
      const todoEntry = current.todos[current.todos.length - 1];
      const taskEntry = todoEntry.tasks[todoEntry.tasks.length - 1];
      taskEntry.description = append(taskEntry.description, text);
      return;
    }

    if (context === 'milestone') {
      current.description = append(current.description, text);
    } else if (context === 'todo') {
      const todoEntry = current.todos[current.todos.length - 1];
      todoEntry.description = append(todoEntry.description, text);
    }
  });

  return { milestones };
}

function append(text, line) {
  return text === null ? line : `${text}\n${line}`;
}
