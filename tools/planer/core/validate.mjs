import { DEFAULT_STATUS, KINDS, PLAIN_SECTIONS, STATUSES, TITLE_MAX } from './format.mjs';

/**
 * Checks a mollo-planer Markdown file line by line. Returns a list of issues:
 * { line, severity: 'error' | 'warning', message }. Errors break the import,
 * warnings do not. Sorted by line.
 *
 * Rules (see spec/format-v1.md):
 * - Milestone: "## vM.m Titel", only major and minor, increasing order.
 * - Status: optional line "Status: geplant" or "Status: veröffentlicht" directly below the milestone.
 * - ToDo: "### Titel", inside a milestone, not empty, contains tasks.
 * - Task: "- [x] [art] Titel" or "- [ ] [art] Titel", kind from KINDS, unique title per todo.
 * - Done task (x) needs "  Commit <hash>" directly below it (error if missing).
 *   Legacy exception: "  Ohne Commit" directly below a done task (history without hash).
 * - Release gate: a released milestone has no open task (error). A planned milestone
 *   with only done tasks gets a hint to set its status.
 */
export function validate(markdown) {
  const issues = [];
  const add = (line, severity, message) => issues.push({ line, severity, message });

  let previous = null; // [major, minor] of the last milestone
  // Current milestone: line, number ("1.0"), todos, open and total tasks,
  // status, and whether content (anything but the status line) was seen yet.
  let milestone = null;
  let todo = null; // { line, tasks, titles } of the current todo
  let inMilestone = false;
  let pendingTask = null; // { line, commit } of a done task waiting for its commit line

  const closePendingTask = () => {
    if (pendingTask !== null && pendingTask.commit === null) {
      add(pendingTask.line, 'error', 'Erledigter Task ohne „  Commit <Hash>“ darunter.');
    }
    pendingTask = null;
  };

  const closeTodo = () => {
    if (todo !== null && todo.tasks === 0) {
      add(todo.line, 'error', 'ToDo ohne Task.');
    }
    todo = null;
  };

  const closeMilestone = () => {
    if (milestone !== null && milestone.todos === 0) {
      add(milestone.line, 'error', 'Milestone ohne ToDo.');
    }
    if (milestone !== null && milestone.status === 'veröffentlicht' && milestone.open > 0) {
      add(milestone.line, 'error', `Version ${milestone.number} ist veröffentlicht, hat aber ${milestone.open} offene Task(s).`);
    }
    if (milestone !== null && milestone.status === 'geplant' && milestone.total > 0 && milestone.open === 0) {
      add(milestone.line, 'warning', `Alle Tasks von ${milestone.number} sind erledigt. Status auf „veröffentlicht“ setzen, wenn die Version fertig ist.`);
    }
    milestone = null;
  };

  const lines = markdown.split(/\r?\n|\r/);

  lines.forEach((raw, index) => {
    const number = index + 1;
    const line = raw.replace(/\s+$/, '');

    // Legacy task without commit (history before the commit rule), only for done tasks.
    if (line === '  Ohne Commit') {
      if (pendingTask !== null) {
        pendingTask.commit = 'ohne';
      } else {
        add(number, 'error', '„  Ohne Commit“ steht nur unter einem erledigten Task.');
      }
      return;
    }

    // Commit line directly below a task.
    if (line.startsWith('  Commit ') && pendingTask !== null) {
      if (/^  Commit [0-9a-f]{7,40}$/.test(line)) {
        pendingTask.commit = line.slice('  Commit '.length);
      } else {
        add(number, 'error', 'Commit-Zeile muss „  Commit <Hash>“ sein.');
      }
      return;
    }

    if (line.startsWith('## ')) {
      closePendingTask();
      closeTodo();
      closeMilestone();

      const milestoneMatch = line.match(/^## v(\d+)\.(\d+)\s+(.+)$/);
      if (milestoneMatch === null) {
        inMilestone = false;
        if (/^## v\d+\.\d+\.\d+/.test(line)) {
          add(number, 'error', 'Patch-Stufe (vM.m.p) ist nicht erlaubt, nur vM.m.');
        } else if (!PLAIN_SECTIONS.includes(line.slice(3).trim())) {
          add(number, 'warning', 'Abschnitt ist kein Milestone (erwartet „## vM.m Titel“) und wird übersprungen.');
        }
        return;
      }

      const version = [Number(milestoneMatch[1]), Number(milestoneMatch[2])];
      if (previous !== null && compareVersions(version, previous) <= 0) {
        add(number, 'error', `Version ${version.join('.')} ist nicht größer als die vorherige (${previous.join('.')}).`);
      }
      if (milestoneMatch[3].trim().length > TITLE_MAX) {
        add(number, 'error', `Titel des Milestones ist länger als ${TITLE_MAX} Zeichen.`);
      }

      previous = version;
      milestone = {
        line: number,
        number: version.join('.'),
        todos: 0,
        open: 0,
        total: 0,
        status: DEFAULT_STATUS,
        contentSeen: false,
      };
      inMilestone = true;
      return;
    }

    const statusLine = line.match(/^Status:\s*(.*)$/);
    if (statusLine !== null && milestone !== null) {
      const value = statusLine[1].trim();
      if (milestone.contentSeen || milestone.todos > 0) {
        add(number, 'error', 'Status steht nur direkt unter der Milestone-Überschrift.');
      } else if (!STATUSES.includes(value)) {
        add(number, 'error', `Unbekannter Status „${value}“. Erlaubt: ${STATUSES.join(', ')}.`);
      } else {
        milestone.status = value;
      }
      milestone.contentSeen = true;
      return;
    }

    if (line.startsWith('### ')) {
      closePendingTask();
      closeTodo();

      if (!inMilestone) {
        add(number, 'error', 'ToDo steht außerhalb eines Milestones (erwartet „## vM.m Titel“ davor).');
        return;
      }

      const title = line.slice(4).trim();
      if (title === '' || title.length > TITLE_MAX) {
        add(number, 'error', 'ToDo-Titel fehlt oder ist zu lang.');
      }

      milestone.todos++;
      milestone.contentSeen = true;
      todo = { line: number, tasks: 0, titles: [] };
      return;
    }

    if (line.trimStart().startsWith('- [')) {
      closePendingTask();

      const task = line.trimStart().match(/^- \[( |x)\] \[(\w+)\] (.+)$/);
      if (task === null) {
        add(number, 'error', 'Task nicht lesbar. Erwartet: „- [x] [art] Titel“ oder „- [ ] [art] Titel“.');
        return;
      }
      if (todo === null) {
        add(number, 'error', 'Task steht außerhalb eines ToDos.');
        return;
      }
      if (!KINDS.includes(task[2])) {
        add(number, 'error', `Unbekannte Art [${task[2]}]. Erlaubt: ${KINDS.join(', ')}.`);
      }

      const title = task[3].trim();
      if (title.length < 2 || title.length > TITLE_MAX) {
        add(number, 'error', `Task-Titel muss 2 bis ${TITLE_MAX} Zeichen lang sein.`);
      }
      if (todo.titles.includes(title)) {
        add(number, 'error', `Doppelter Task-Titel im ToDo: „${title}“.`);
      }
      todo.titles.push(title);
      todo.tasks++;
      milestone.total++;
      milestone.contentSeen = true;

      if (task[1] === 'x') {
        pendingTask = { line: number, commit: null };
      } else {
        milestone.open++;
      }
      return;
    }

    // Blank lines and descriptions do not end a pending task.
    if (line.trim() !== '' && milestone !== null) {
      milestone.contentSeen = true;
    }
  });

  closePendingTask();
  closeTodo();
  closeMilestone();

  if (previous === null) {
    add(1, 'error', 'Kein Milestone gefunden.');
  }

  return issues.sort((a, b) => a.line - b.line);
}

function compareVersions(a, b) {
  return a[0] - b[0] || a[1] - b[1];
}
