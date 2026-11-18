/**
 * Operationen auf einem geprüften Plan (planer-v2), ohne Dateizugriff.
 * Die Befehle in src/planer-commands.mjs laden, rufen diese Funktionen auf,
 * prüfen das Ergebnis und schreiben erst dann.
 */

export class CommandError extends Error {
  constructor(message, status = 1) {
    super(message);
    this.status = status;
  }
}

const PREFIX = { milestone: 'm', todo: 't', task: 'k' };
const LABEL = { milestone: 'Milestone', todo: 'ToDo', task: 'Task' };

/** Findet ein Element über seine ID. Liefert Art, Element, Liste, in der es steht, und Index. */
export function findNode(plan, id) {
  for (const [mi, milestone] of plan.milestones.entries()) {
    if (milestone.id === id) {
      return { kind: 'milestone', node: milestone, container: plan.milestones, index: mi };
    }
    for (const [ti, todo] of milestone.todos.entries()) {
      if (todo.id === id) {
        return { kind: 'todo', node: todo, container: milestone.todos, index: ti, parent: milestone };
      }
      for (const [ki, task] of todo.tasks.entries()) {
        if (task.id === id) {
          return { kind: 'task', node: task, container: todo.tasks, index: ki, parent: todo };
        }
      }
    }
  }
  return null;
}

/**
 * Nächste freie ID der Art. Die höchste vorhandene Nummer plus eins.
 * Die Helfer löschen nichts, deshalb wird keine Nummer wiederverwendet.
 */
export function nextId(plan, kind) {
  const prefix = PREFIX[kind];
  let max = 0;
  for (const id of allIds(plan)) {
    const match = id.match(new RegExp(`^${prefix}-new-(\\d+)$`));
    if (match) {
      max = Math.max(max, Number(match[1]));
    }
  }
  return `${prefix}-new-${max + 1}`;
}

/** Hängt ein neues Element ans Ende seiner Liste an und liefert seine neue ID. */
export function addElement(plan, kind, element, parentId) {
  let container;
  if (kind === 'milestone') {
    if (parentId !== undefined) {
      throw new CommandError('Ein Milestone hat kein übergeordnetes Element. --milestone und --todo entfallen.', 2);
    }
    container = plan.milestones;
  } else if (kind === 'todo') {
    container = requireParent(plan, parentId, 'milestone', '--milestone').todos;
  } else {
    container = requireParent(plan, parentId, 'todo', '--todo').tasks;
  }

  const id = nextId(plan, kind);
  container.push({ id, ...element });
  return id;
}

/**
 * Verschiebt ein Element. Ohne `to` bleibt der Elternteil, nur die Position ändert sich.
 * Ohne `position` kommt das Element ans Ende der Zielliste. Die ID bleibt.
 */
export function moveElement(plan, id, to, position) {
  const found = findNode(plan, id);
  if (!found) {
    throw new CommandError(`ID ${id} nicht gefunden.`, 1);
  }

  let target;
  if (found.kind === 'milestone') {
    if (to !== undefined) {
      throw new CommandError('Ein Milestone wird mit --position verschoben, nicht mit --to.', 2);
    }
    target = plan.milestones;
  } else if (to === undefined) {
    target = found.container;
  } else if (found.kind === 'todo') {
    target = requireParent(plan, to, 'milestone', '--to').todos;
  } else {
    target = requireParent(plan, to, 'todo', '--to').tasks;
  }

  found.container.splice(found.index, 1);
  const index = position === undefined ? target.length : position;
  if (!Number.isInteger(index) || index < 0 || index > target.length) {
    throw new CommandError(`Position ${position} liegt außerhalb der Liste (0 bis ${target.length}).`, 2);
  }
  target.splice(index, 0, found.node);
}

/** Setzt den Status eines Tasks und optional den Commit. Liefert den bisherigen Status. */
export function setStatus(plan, id, status, commit) {
  const found = findNode(plan, id);
  if (!found) {
    throw new CommandError(`ID ${id} nicht gefunden.`, 1);
  }
  if (found.kind !== 'task') {
    throw new CommandError(`${id} ist kein Task. Der Status gilt nur für Tasks (k-id).`, 2);
  }
  const previous = found.node.status;
  found.node.status = status;
  if (commit !== undefined) {
    found.node.commit = commit;
  }
  return previous;
}

/** Eine Zeile je Element, ohne Beschreibungen. */
export function listLines(plan) {
  const lines = [];
  for (const milestone of plan.milestones) {
    const version = milestone.release ? ` v${milestone.release.version} [${milestone.release.status}]` : '';
    lines.push(`${milestone.id}${version} ${milestone.title}`);
    for (const todo of milestone.todos) {
      lines.push(`  ${todo.id} ${todo.title}`);
      for (const task of todo.tasks) {
        lines.push(`    ${task.id} [${MARK[task.status]}] ${task.title}`);
      }
    }
  }
  return lines;
}

const MARK = { idea: 'i', planned: 'p', open: ' ', in_progress: '~', review: '?', done: 'x' };

function allIds(plan) {
  const ids = [];
  for (const milestone of plan.milestones) {
    ids.push(milestone.id);
    for (const todo of milestone.todos) {
      ids.push(todo.id);
      for (const task of todo.tasks) {
        ids.push(task.id);
      }
    }
  }
  return ids;
}

function requireParent(plan, id, kind, flag) {
  if (id === undefined) {
    throw new CommandError(`${flag} fehlt. Das übergeordnete Element ist ein ${LABEL[kind]}.`, 2);
  }
  const found = findNode(plan, id);
  if (!found || found.kind !== kind) {
    throw new CommandError(`${flag} ${id}: kein ${LABEL[kind]} mit dieser ID.`, 1);
  }
  return found.node;
}
