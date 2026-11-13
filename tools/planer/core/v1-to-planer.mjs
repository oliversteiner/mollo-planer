import { parse } from './parse.mjs';
import { validate } from './validate.mjs';

/**
 * Führt eine v1-Datei (version-<name>.md) nach planer/2 über. Liefert ein Plan-Objekt (ungeprüft).
 *
 * Abbildung (spec/format-v2.md, Abschnitt 11):
 * - Milestone „## vM.m Titel“ mit Status → Milestone mit release { version, status }.
 * - ToDo, Task → ToDo, Task. Erledigt → status done, offen → open.
 * - Art des Tasks → Label in labelNames (die Art ist ein Label, SSoT §2 Entscheidung 5).
 * - Commit → commit. „Ohne Commit“ → commit "ohne".
 * - Beschreibungen bleiben als Text erhalten.
 * - Projekt: name und locale kommen von außen (der Dateiname liefert keinen Namen),
 *   Mitglieder gibt es in v1 nicht, die Liste bleibt leer. Revision ist 1.
 *
 * Die v1-Datei muss vorher gültig sein. Sonst wird ein Fehler geworfen.
 */
export function planerFromV1(markdown, { name, locale = 'DE' }) {
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

  const milestones = data.milestones.map((milestone) => {
    const entry = {
      id: next('m'),
      title: milestone.title,
      release: { version: milestone.number, status: milestone.status },
    };
    if (milestone.description) entry.description = milestone.description;

    entry.todos = milestone.todos.map((todo) => {
      const todoEntry = { id: next('t'), title: todo.title };
      if (todo.description) todoEntry.description = todo.description;

      todoEntry.tasks = todo.tasks.map((task) => {
        const taskEntry = {
          id: next('k'),
          title: task.title,
          status: task.done ? 'done' : 'open',
          labelNames: [task.kind],
        };
        if (task.description) taskEntry.description = task.description;
        if (task.commit !== null) taskEntry.commit = task.commit;
        return taskEntry;
      });
      return todoEntry;
    });

    return entry;
  });

  return {
    format: 'planer/2',
    revision: 1,
    project: { name, locale, members: [] },
    milestones,
  };
}

export class ConvertError extends Error {}

/** Ausgabe einer ID wie m-001, t-012, k-007. */
function sequence(prefix, n) {
  return `${prefix}-${String(n).padStart(3, '0')}`;
}
