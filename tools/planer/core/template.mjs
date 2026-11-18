/**
 * Vorlagen (planer-abgleich §3.8). Eine Vorlage ist ein gültiges planer-v2-Dokument
 * mit festen Werten: revision 0, nur -new--IDs, keine Mitglieder, Zuweisungen,
 * Fälligkeiten und Commits, release.status nur planned, Task-Status planned,
 * mindestens ein Label. Diese Datei prüft die Regeln und setzt sie.
 */

const NEW_ID = /^[mtk]-new-[0-9]+$/;

/** Verletzungen der Vorlagen-Regeln (ohne die Prüfung des Schemas, die kommt vorher). */
export function templateIssues(plan) {
  const issues = [];
  const add = (path, message) => issues.push({ path, severity: 'error', message });

  if (plan.revision !== 0) add('revision', 'Eine Vorlage hat revision 0');
  if (plan.changed !== undefined) add('changed', 'Eine Vorlage hat kein changed');
  if (plan.project.members.length > 0) add('project.members', 'Eine Vorlage hat keine Mitglieder');
  if (!plan.project.labels || plan.project.labels.length === 0) add('project.labels', 'Eine Vorlage braucht mindestens ein Label');

  plan.milestones.forEach((milestone, mi) => {
    const mp = `milestones[${mi}]`;
    checkId(milestone, mp, add);
    if (milestone.dueDate !== undefined) add(`${mp}.dueDate`, 'Eine Vorlage hat keine Fälligkeit');
    if (milestone.release !== undefined && milestone.release.status !== 'planned') {
      add(`${mp}.release.status`, 'Eine Vorlage hat release.status planned');
    }

    milestone.todos.forEach((todo, ti) => {
      const tp = `${mp}.todos[${ti}]`;
      checkId(todo, tp, add);
      if (todo.dueDate !== undefined) add(`${tp}.dueDate`, 'Eine Vorlage hat keine Fälligkeit');

      todo.tasks.forEach((task, ki) => {
        const kp = `${tp}.tasks[${ki}]`;
        checkId(task, kp, add);
        if (task.status !== 'planned') add(`${kp}.status`, 'Eine Vorlage hat Task-Status planned');
        if (task.dueDate !== undefined) add(`${kp}.dueDate`, 'Eine Vorlage hat keine Fälligkeit');
        if (task.assigneeEmail !== undefined) add(`${kp}.assigneeEmail`, 'Eine Vorlage hat keine Zuweisung');
        if (task.commit !== undefined) add(`${kp}.commit`, 'Eine Vorlage hat keinen Commit');
      });
    });
  });

  return issues;
}

function checkId(node, path, add) {
  if (!NEW_ID.test(node.id ?? '')) {
    add(`${path}.id`, `Eine Vorlage hat nur neue IDs (m-new-N, t-new-N, k-new-N), gefunden: ${node.id}`);
  }
}

/**
 * Macht aus einem Plan eine Vorlage: entfernt die App-Bezüge, vergibt neue IDs in
 * Dokumentreihenfolge und setzt die festen Werte. Liefert ein Objekt, das mit
 * templateIssues und validatePlaner geprüft werden muss.
 */
export function toTemplate(plan) {
  const counters = { m: 0, t: 0, k: 0 };
  const next = (prefix) => {
    counters[prefix] += 1;
    return `${prefix}-new-${counters[prefix]}`;
  };

  const milestones = plan.milestones.map((milestone) => ({
    id: next('m'),
    title: milestone.title,
    ...(milestone.description !== undefined ? { description: milestone.description } : {}),
    ...(milestone.release !== undefined ? { release: { version: milestone.release.version, status: 'planned' } } : {}),
    todos: milestone.todos.map((todo) => ({
      id: next('t'),
      title: todo.title,
      ...(todo.description !== undefined ? { description: todo.description } : {}),
      tasks: todo.tasks.map((task) => ({
        id: next('k'),
        title: task.title,
        ...(task.description !== undefined ? { description: task.description } : {}),
        ...(task.userStory !== undefined ? { userStory: task.userStory } : {}),
        status: 'planned',
        ...(task.labels !== undefined && task.labels.length > 0 ? { labels: task.labels } : {}),
      })),
    })),
  }));

  return {
    format: plan.format,
    revision: 0,
    project: {
      name: plan.project.name,
      ...(plan.project.description !== undefined ? { description: plan.project.description } : {}),
      locale: plan.project.locale,
      members: [],
      ...(plan.project.labels !== undefined ? { labels: plan.project.labels } : {}),
    },
    milestones,
  };
}
