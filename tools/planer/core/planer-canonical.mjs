/**
 * Kanonische Form einer Planer-Datei (planer-v2): feste Schlüsselreihenfolge,
 * zwei Leerzeichen Einrückung, Zeilenende am Schluss. Dieselben Daten ergeben
 * immer denselben Text, dadurch bleiben Git-Diffs lesbar. Nicht gesetzte optionale
 * Felder (null) und leere Listen (labels, members) fehlen in der kanonischen Form.
 */

const ORDER = {
  root: ['$schema', 'format', 'revision', 'changed', 'project', 'milestones'],
  project: ['name', 'description', 'locale', 'members', 'labels'],
  member: ['email', 'role'],
  label: ['key', 'names', 'category', 'color'],
  milestone: ['id', 'appId', 'title', 'description', 'dueDate', 'release', 'todos'],
  release: ['version', 'status'],
  todo: ['id', 'appId', 'title', 'description', 'dueDate', 'tasks'],
  task: ['id', 'appId', 'title', 'description', 'dueDate', 'status', 'assigneeEmail', 'labels', 'commit', 'userStory'],
  story: ['role', 'goal', 'benefit'],
};

/** Kanonischer Text einer geprüften Planer-Datei, mit Zeilenende. */
export function canonicalText(plan) {
  return `${JSON.stringify(canonicalize(plan), null, 2)}\n`;
}

function canonicalize(plan) {
  return order(plan, ORDER.root, {
    changed: (changed) => (changed === true ? true : undefined),
    project: (project) => order(project, ORDER.project, {
      members: (members) => members.map((member) => order(member, ORDER.member)),
      labels: (labels) => (labels.length > 0 ? labels.map((label) => order(label, ORDER.label, {
        names: (names) => order(names, ['de', 'en']),
      })) : undefined),
    }),
    milestones: (milestones) => milestones.map((milestone) => order(milestone, ORDER.milestone, {
      release: (release) => order(release, ORDER.release),
      todos: (todos) => todos.map((todo) => order(todo, ORDER.todo, {
        tasks: (tasks) => tasks.map((task) => order(task, ORDER.task, {
          userStory: (story) => (story === null ? null : order(story, ORDER.story)),
          labels: (keys) => (keys.length > 0 ? keys : undefined),
        })),
      })),
    })),
  });
}

/** Schreibt die Schlüssel eines Objekts in fester Reihenfolge. Unbekannte Schlüssel kommen ans Ende. */
function order(node, keys, children = {}) {
  const result = {};
  const rest = Object.keys(node).filter((key) => !keys.includes(key));
  for (const key of [...keys, ...rest]) {
    if (!Object.hasOwn(node, key)) {
      continue;
    }
    const value = children[key] ? children[key](node[key]) : node[key];
    // null und fehlend bedeuten dasselbe: nicht gesetzt. Die kanonische Form lässt beides weg.
    if (value === undefined || value === null) {
      continue;
    }
    result[key] = value;
  }
  return result;
}
