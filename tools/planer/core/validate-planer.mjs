import { readFileSync } from 'node:fs';
import { validateAgainst } from './json-schema.mjs';
import { canonicalText } from './planer-canonical.mjs';
import { parseDescription } from './richtext.mjs';

/**
 * Prüft eine Planer-Datei `<projekt>.planer.json` (Format planer-v2, siehe spec/format-v2.md).
 * Liefert eine Liste { path, severity: 'error' | 'warning', message }. Fehler brechen ab,
 * Hinweise nicht. Der Pfad zeigt auf die Stelle, z. B. `milestones[0].todos[0].tasks[1].commit`.
 *
 * Drei Stufen:
 * 1. Schema: spec/planer-v2.schema.json, Form, Typen, Grenzen, Muster.
 * 2. Semantik (nur wenn das Schema passt): eindeutige IDs, Verweise auf Mitglieder,
 *    Release-Regel, Commit-Regeln.
 * 3. Kanonische Form, als Hinweis: zwei Leerzeichen Einrückung, Zeilenende am Schluss.
 *
 * Stufe 2 prüft außerdem die Whitelist der Formatierung in Beschreibungen (Spec, Abschnitt 6): Konstrukte außerhalb sind Fehler, Schreibweisen aus §9.5 Hinweise (core/richtext.mjs).
 */

const schema = JSON.parse(readFileSync(new URL('../../../spec/planer-v2.schema.json', import.meta.url), 'utf8'));

export function validatePlaner(text) {
  let data;
  try {
    data = JSON.parse(text);
  } catch (error) {
    return [{ path: '', severity: 'error', message: `Keine gültige JSON-Datei: ${error.message}` }];
  }

  const schemaIssues = validateAgainst(schema, data).map((issue) => ({ ...issue, severity: 'error' }));
  if (schemaIssues.length > 0) {
    return schemaIssues;
  }

  const issues = semantics(data);
  issues.push(...markdownIssues(data));

  if (text !== canonicalText(data)) {
    issues.push({
      path: '',
      severity: 'warning',
      message: 'Formatierung weicht von der kanonischen Form ab (zwei Leerzeichen Einrückung, Zeilenende am Schluss).',
    });
  }

  return issues;
}

/**
 * Whitelist der Formatierung (spec/format-v2.md, Abschnitt 6): nur Absätze, Überschriften
 * Ebene 2 und 3, Listen, Zitate, harte Zeilenumbrüche, fett, kursiv, Links (http, https, mailto).
 * Verboten sind HTML, Bilder, Tabellen, Code-Blöcke, andere Überschriften und andere Link-Schemata.
 *
 * Inline-Code (`Text`) ist erlaubt (Mark `code`, Spec Abschnitt 6).
 */
function markdownIssues(plan) {
  const issues = [];
  const check = (text, path) => {
    if (typeof text !== 'string') return;
    const { errors, hints } = parseDescription(text);
    for (const message of errors) {
      issues.push({ path, severity: 'error', message });
    }
    if (errors.length === 0) {
      for (const message of hints) {
        issues.push({ path, severity: 'warning', message });
      }
    }
  };

  check(plan.project.description, 'project.description');
  plan.milestones.forEach((milestone, mi) => {
    const mp = `milestones[${mi}]`;
    check(milestone.description, `${mp}.description`);
    milestone.todos.forEach((todo, ti) => {
      const tp = `${mp}.todos[${ti}]`;
      check(todo.description, `${tp}.description`);
      todo.tasks.forEach((task, ki) => {
        check(task.description, `${tp}.tasks[${ki}].description`);
      });
    });
  });
  return issues;
}

export { forbiddenMarkdown } from './richtext.mjs';

function semantics(plan) {
  const issues = [];
  const add = (path, message) => issues.push({ path, severity: 'error', message });

  const firstSeen = new Map();
  const claimId = (id, path) => {
    if (firstSeen.has(id)) {
      add(path, `ID ${id} ist doppelt (zuerst bei ${firstSeen.get(id)})`);
    } else {
      firstSeen.set(id, path);
    }
  };

  const members = new Set(plan.project.members.map((member) => member.email.toLowerCase()));

  const labelKeys = new Set();
  const labelNames = new Set();
  (plan.project.labels ?? []).forEach((label, li) => {
    const lp = `project.labels[${li}]`;
    if (labelKeys.has(label.key)) {
      add(`${lp}.key`, `Schlüssel ${label.key} ist doppelt`);
    }
    labelKeys.add(label.key);
    if (label.names[plan.project.locale] === undefined) {
      add(`${lp}.names`, `Name in der Sprache des Projekts (${plan.project.locale}) fehlt`);
    }
    for (const [lang, name] of Object.entries(label.names)) {
      const seen = `${lang}:${name}`;
      if (labelNames.has(seen)) {
        add(`${lp}.names.${lang}`, `Name ${name} ist in ${lang} doppelt`);
      }
      labelNames.add(seen);
    }
  });

  plan.milestones.forEach((milestone, mi) => {
    const mp = `milestones[${mi}]`;
    claimId(milestone.id, `${mp}.id`);

    const released = milestone.release?.status === 'released';
    let open = 0;

    milestone.todos.forEach((todo, ti) => {
      const tp = `${mp}.todos[${ti}]`;
      claimId(todo.id, `${tp}.id`);

      todo.tasks.forEach((task, ki) => {
        const kp = `${tp}.tasks[${ki}]`;
        claimId(task.id, `${kp}.id`);

        if (task.status !== 'done') {
          open++;
        }

        if (task.assigneeEmail && !members.has(task.assigneeEmail.toLowerCase())) {
          add(`${kp}.assigneeEmail`, `${task.assigneeEmail} ist kein Mitglied des Projekts`);
        }

        (task.labels ?? []).forEach((key, li) => {
          if (!labelKeys.has(key)) {
            add(`${kp}.labels[${li}]`, `${key} ist kein Label des Projekts`);
          }
        });

        if (task.commit === 'none' && task.status !== 'done') {
          add(`${kp}.commit`, '„none“ ist nur bei erledigten Tasks erlaubt');
        }

        // Ein fehlendes commit-Feld zählt wie null (nicht gesetzt).
        if (released && task.status === 'done' && (task.commit ?? null) === null) {
          add(`${kp}.commit`, `Erledigter Task in veröffentlichter Version ${milestone.release.version} braucht einen Hash oder „none“`);
        }
      });
    });

    if (released && open > 0) {
      add(mp, `Version ${milestone.release.version} ist veröffentlicht, hat aber ${open} nicht erledigte Task(s)`);
    }
  });

  return issues;
}
