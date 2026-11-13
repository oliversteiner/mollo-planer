import { readFileSync } from 'node:fs';
import { validateAgainst } from './json-schema.mjs';
import { canonicalText } from './planer-canonical.mjs';

/**
 * Prüft eine Planer-Datei `<projekt>.planer.json` (Format planer/2, siehe spec/format-v2.md).
 * Liefert eine Liste { path, severity: 'error' | 'warning', message }. Fehler brechen ab,
 * Hinweise nicht. Der Pfad zeigt auf die Stelle, z. B. `milestones[0].todos[0].tasks[1].commit`.
 *
 * Drei Stufen:
 * 1. Schema: spec/planer-2.schema.json, Form, Typen, Grenzen, Muster.
 * 2. Semantik (nur wenn das Schema passt): eindeutige IDs, Verweise auf Mitglieder,
 *    Release-Regel, Commit-Regeln.
 * 3. Kanonische Form, als Hinweis: zwei Leerzeichen Einrückung, Zeilenende am Schluss.
 *
 * Stufe 2 prüft außerdem die Whitelist der Formatierung in Beschreibungen (Spec, Abschnitt 6).
 * Nicht geprüft: Labels (das Label-Set steht nicht in der Datei, die Prüfung folgt beim Import in P6).
 */

const schema = JSON.parse(readFileSync(new URL('../../../spec/planer-2.schema.json', import.meta.url), 'utf8'));

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
    for (const message of forbiddenMarkdown(text)) {
      issues.push({ path, severity: 'error', message });
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

/** Liefert die Verstöße eines Markdown-Textes gegen die Whitelist. Leere Liste, wenn alles erlaubt ist. */
export function forbiddenMarkdown(text) {
  const problems = [];
  if (/<\/?[A-Za-z!][^>]*>/.test(text)) {
    problems.push('HTML ist in Beschreibungen nicht erlaubt');
  }
  if (/!\[[^\]]*\]\(/.test(text)) {
    problems.push('Bilder sind in Beschreibungen nicht erlaubt');
  }
  for (const line of text.split('\n')) {
    if (/^# /.test(line) || /^#{4,} /.test(line)) {
      problems.push('Nur Überschriften der Ebene 2 und 3 sind erlaubt');
    }
    if (line.startsWith('```')) {
      problems.push('Code-Blöcke sind in Beschreibungen nicht erlaubt');
    }
    if (/^\s*\|.*\|/.test(line)) {
      problems.push('Tabellen sind in Beschreibungen nicht erlaubt');
    }
  }
  for (const match of text.matchAll(/\]\(([^)\s]*)/g)) {
    if (!/^(https?:\/\/|mailto:)/i.test(match[1])) {
      problems.push(`Link-Schema nicht erlaubt: ${match[1]}`);
    }
  }
  return [...new Set(problems)];
}

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

  plan.milestones.forEach((milestone, mi) => {
    const mp = `milestones[${mi}]`;
    claimId(milestone.id, `${mp}.id`);

    const released = milestone.release?.status === 'veröffentlicht';
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

        if (task.commit === 'ohne' && task.status !== 'done') {
          add(`${kp}.commit`, '„ohne“ ist nur bei erledigten Tasks erlaubt');
        }

        // Ein fehlendes commit-Feld zählt wie null (nicht gesetzt).
        if (released && task.status === 'done' && (task.commit ?? null) === null) {
          add(`${kp}.commit`, `Erledigter Task in veröffentlichter Version ${milestone.release.version} braucht einen Hash oder „ohne“`);
        }
      });
    });

    if (released && open > 0) {
      add(mp, `Version ${milestone.release.version} ist veröffentlicht, hat aber ${open} nicht erledigte Task(s)`);
    }
  });

  return issues;
}
