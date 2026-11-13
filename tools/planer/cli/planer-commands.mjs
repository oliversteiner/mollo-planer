import { existsSync, readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { basename, join, resolve } from 'node:path';
import { validatePlaner } from '../core/validate-planer.mjs';
import { canonicalText } from '../core/planer-canonical.mjs';
import { CommandError, addElement, findNode, listLines, moveElement, setStatus } from '../core/planer-edit.mjs';

/**
 * Helfer-Befehle für planer/2: add, move, status, list, show, revision.
 * Jede Änderung lädt die Datei, prüft sie, ändert sie, prüft das Ergebnis und
 * schreibt erst dann. Schlägt eine Prüfung fehl, bleibt die Datei unverändert.
 */

export const HELPERS = ['add', 'move', 'status', 'list', 'show', 'revision'];

const TASK_STATUSES = ['idea', 'planned', 'open', 'in_progress', 'review', 'done'];
const RELEASE_STATUSES = ['planned', 'released'];
const ELEMENT_FLAGS = ['title', 'description', 'due', 'version', 'release-status', 'assignee', 'label', 'commit', 'status'];

const USAGE = `Verwendung (planer/2, Datei per --datei oder docs/*.planer.json):
  mollo-planer add milestone --title <Text> [--due JJJJ-MM-TT] [--version M.m [--release-status planned|released]]
  mollo-planer add todo --milestone <m-NNN> --title <Text> [--due JJJJ-MM-TT]
  mollo-planer add task --todo <t-NNN> --title <Text> [--status idea|planned|open|in_progress|review|done] [--assignee <E-Mail>] [--label <Name|Schlüssel>]... [--commit <Hash|none>] [--due JJJJ-MM-TT]
  mollo-planer add <milestone|todo|task> --json          Element als JSON auf stdin (für Beschreibung, userStory, …)
  mollo-planer move <id> [--to <ziel-id>] [--position <N>]
  mollo-planer status <k-NNN> idea|planned|open|in_progress|review|done [--commit <Hash|none>]
  mollo-planer list [--compact]
  mollo-planer show <id>
  mollo-planer revision`;

/**
 * Führt einen Helfer-Befehl aus. Liefert { status, stdout, stderr }, schreibt aber nichts nach außen.
 *
 * @param {string[]} argv     Argumente nach dem Befehlsnamen, z. B. ['add', 'task', '--todo', 't-001', …]
 * @param {object}   options  cwd (Ordner für docs/), readStdin (liefert den Text für --json)
 */
export function runPlanerCommand(argv, options) {
  try {
    return dispatch(argv, options);
  } catch (error) {
    if (error instanceof CommandError) {
      return { status: error.status, stdout: '', stderr: `${error.message}\n` };
    }
    throw error;
  }
}

function dispatch(argv, options) {
  const { positional, flags } = parseArgs(argv);
  const [command, ...args] = positional;

  switch (command) {
    case 'add':
      return add(args, flags, options);
    case 'move':
      return move(args, flags, options);
    case 'status':
      return status(args, flags, options);
    case 'list':
      return list(flags, options);
    case 'show':
      return show(args, flags, options);
    case 'revision':
      return revision(flags, options);
    default:
      throw new CommandError(`Unbekannter Helfer-Befehl „${command ?? ''}“.\n\n${USAGE}`, 2);
  }
}

function add(args, flags, options) {
  const [kind] = args;
  if (!['milestone', 'todo', 'task'].includes(kind)) {
    throw new CommandError(`add braucht milestone, todo oder task.\n\n${USAGE}`, 2);
  }
  allowFlags(flags, ['datei', 'json', 'milestone', 'todo', ...ELEMENT_FLAGS]);

  const parentId = kind === 'todo' ? flags.milestone : kind === 'task' ? flags.todo : undefined;
  if (kind === 'todo' && flags.todo !== undefined) throw new CommandError('Ein ToDo gehört zu einem Milestone (--milestone), nicht zu --todo.', 2);
  if (kind === 'task' && flags.milestone !== undefined) throw new CommandError('Ein Task gehört zu einem ToDo (--todo), nicht zu --milestone.', 2);
  if (kind === 'milestone' && (flags.milestone !== undefined || flags.todo !== undefined)) {
    throw new CommandError('Ein Milestone hat kein übergeordnetes Element.', 2);
  }

  const element = flags.json ? elementFromStdin(kind, flags, options) : elementFromFlags(kind, flags);
  return change(flags, options, (plan) => {
    if (element.labelNames !== undefined) {
      element.labels = resolveLabelNames(plan, element.labelNames);
      delete element.labelNames;
    }
    const id = addElement(plan, kind, element, parentId);
    return `${id} angelegt`;
  });
}

function move(args, flags, options) {
  const [id] = args;
  if (!id) throw new CommandError(`move braucht eine ID.\n\n${USAGE}`, 2);
  allowFlags(flags, ['datei', 'to', 'position']);

  const position = flags.position === undefined ? undefined : integerFlag('--position', flags.position);
  return change(flags, options, (plan) => {
    moveElement(plan, id, flags.to, position);
    return `${id} verschoben`;
  });
}

function status(args, flags, options) {
  const [id, value] = args;
  if (!id || !value) throw new CommandError(`status braucht eine Task-ID und einen Status.\n\n${USAGE}`, 2);
  if (!TASK_STATUSES.includes(value)) {
    throw new CommandError(`Unbekannter Status „${value}“. Erlaubt: ${TASK_STATUSES.join(', ')}.`, 2);
  }
  allowFlags(flags, ['datei', 'commit']);

  return change(flags, options, (plan) => {
    const previous = setStatus(plan, id, value, flags.commit);
    return `${id}: ${previous} → ${value}`;
  });
}

function list(flags, options) {
  allowFlags(flags, ['datei', 'compact']);
  const plan = readPlan(resolvePath(flags, options));
  return ok(`${listLines(plan).join('\n')}\n`);
}

function show(args, flags, options) {
  const [id] = args;
  if (!id) throw new CommandError(`show braucht eine ID.\n\n${USAGE}`, 2);
  allowFlags(flags, ['datei']);

  const plan = readPlan(resolvePath(flags, options));
  const found = findNode(plan, id);
  if (!found) throw new CommandError(`ID ${id} nicht gefunden.`, 1);
  return ok(`${JSON.stringify(found.node, null, 2)}\n`);
}

function revision(flags, options) {
  allowFlags(flags, ['datei']);
  const plan = readPlan(resolvePath(flags, options));
  return ok(`${plan.revision}\n`);
}

/**
 * Lädt, ändert, prüft und schreibt. Setzt `changed`: die Revision zählt nur die App.
 * Die Ausgabe ist die Meldung der Änderung.
 */
function change(flags, options, mutate) {
  const path = resolvePath(flags, options);
  const plan = readPlan(path);
  const message = mutate(plan);

  plan.changed = true;
  const text = canonicalText(plan);
  const errors = validatePlaner(text).filter((issue) => issue.severity === 'error');
  if (errors.length > 0) {
    const first = errors[0];
    throw new CommandError(`Änderung abgelehnt, Datei unverändert. ${first.path || 'Datei'}: ${first.message}`, 1);
  }

  writeFileSync(path, text);
  return ok(`${message}\n`);
}

function readPlan(path) {
  const text = readFileSync(path, 'utf8');
  const errors = validatePlaner(text).filter((issue) => issue.severity === 'error');
  if (errors.length > 0) {
    const first = errors[0];
    throw new CommandError(`${basename(path)} ist ungültig, ${first.path || 'Datei'}: ${first.message}. Zuerst: mollo-planer validate`, 1);
  }
  return JSON.parse(text);
}

function resolvePath(flags, options) {
  if (flags.datei !== undefined) {
    const path = resolve(options.cwd, flags.datei);
    if (!existsSync(path)) throw new CommandError(`Datei nicht gefunden: ${path}`, 2);
    return path;
  }

  const dir = join(options.cwd, 'docs');
  const matches = existsSync(dir)
    ? readdirSync(dir).filter((name) => name.endsWith('.planer.json'))
    : [];
  if (matches.length !== 1) {
    throw new CommandError('Keine Datei angegeben, und docs/*.planer.json ist nicht eindeutig. Mit --datei angeben.', 2);
  }
  return join(dir, matches[0]);
}

function elementFromFlags(kind, flags) {
  if (flags.title === undefined) throw new CommandError('--title fehlt.', 2);

  const element = { title: flags.title };
  if (flags.description !== undefined) element.description = flags.description;
  if (flags.due !== undefined) element.dueDate = flags.due;

  if (kind === 'milestone') {
    element.todos = [];
    if (flags.version !== undefined) {
      const releaseStatus = flags['release-status'] ?? 'planned';
      if (!RELEASE_STATUSES.includes(releaseStatus)) {
        throw new CommandError(`Unbekannter Release-Status „${releaseStatus}“. Erlaubt: ${RELEASE_STATUSES.join(', ')}.`, 2);
      }
      element.release = { version: flags.version, status: releaseStatus };
    } else if (flags['release-status'] !== undefined) {
      throw new CommandError('--release-status braucht --version.', 2);
    }
  } else if (kind === 'todo') {
    element.tasks = [];
  } else {
    const status = flags.status ?? 'open';
    if (!TASK_STATUSES.includes(status)) {
      throw new CommandError(`Unbekannter Status „${status}“. Erlaubt: ${TASK_STATUSES.join(', ')}.`, 2);
    }
    element.status = status;
    if (flags.assignee !== undefined) element.assigneeEmail = flags.assignee;
    if (flags.label !== undefined) element.labelNames = flags.label;
    if (flags.commit !== undefined) element.commit = flags.commit;
  }
  return element;
}

/** --label nimmt Schlüssel oder Namen aus den Labels des Projekts und gibt Schlüssel zurück. */
function resolveLabelNames(plan, values) {
  const labels = plan.project.labels ?? [];
  return values.map((value) => {
    const label = labels.find((candidate) => candidate.key === value || Object.values(candidate.names).includes(value));
    if (!label) {
      const known = labels.map((candidate) => candidate.key).join(', ') || 'keine';
      throw new CommandError(`Label „${value}“ gibt es im Projekt nicht. Vorhanden: ${known}.`, 2);
    }
    return label.key;
  });
}

function elementFromStdin(kind, flags, options) {
  if (ELEMENT_FLAGS.some((name) => flags[name] !== undefined)) {
    throw new CommandError('--json verträgt keine weiteren Element-Flags. Alles in den JSON-Text.', 2);
  }
  const text = options.readStdin();
  let element;
  try {
    element = JSON.parse(text);
  } catch (error) {
    throw new CommandError(`Der JSON-Text auf stdin ist ungültig: ${error.message}`, 2);
  }
  if (element === null || typeof element !== 'object' || Array.isArray(element)) {
    throw new CommandError('Das JSON auf stdin muss ein Objekt sein.', 2);
  }
  if (Object.hasOwn(element, 'id') || Object.hasOwn(element, 'appId')) {
    throw new CommandError('id und appId werden nicht selbst gesetzt. Die ID vergibt der Helfer, appId setzt der Import.', 2);
  }
  if (kind === 'milestone' && element.todos === undefined) element.todos = [];
  if (kind === 'todo' && element.tasks === undefined) element.tasks = [];
  if (kind === 'task' && element.status === undefined) element.status = 'open';
  return element;
}

function integerFlag(name, value) {
  if (!/^\d+$/.test(value)) throw new CommandError(`${name} braucht eine ganze Zahl ab 0.`, 2);
  return Number(value);
}

function allowFlags(flags, allowed) {
  for (const name of Object.keys(flags)) {
    if (!allowed.includes(name)) {
      throw new CommandError(`Unbekannte Option --${name} für diesen Befehl.\n\n${USAGE}`, 2);
    }
  }
}

function ok(stdout) {
  return { status: 0, stdout, stderr: '' };
}

/**
 * Trennt Positionsargumente von Optionen. --json und --compact sind Schalter, alle anderen
 * Optionen brauchen einen Wert. --label darf mehrfach stehen und sammelt eine Liste.
 */
export function parseArgs(argv) {
  const SWITCHES = new Set(['json', 'compact']);
  const REPEATABLE = new Set(['label']);
  const positional = [];
  const flags = {};

  for (let i = 0; i < argv.length; i++) {
    const arg = argv[i];
    if (!arg.startsWith('--')) {
      positional.push(arg);
      continue;
    }
    const name = arg.slice(2);
    if (SWITCHES.has(name)) {
      flags[name] = true;
      continue;
    }
    const value = argv[i + 1];
    if (value === undefined || value.startsWith('--')) {
      throw new CommandError(`--${name} braucht einen Wert.`, 2);
    }
    i++;
    if (REPEATABLE.has(name)) {
      flags[name] = [...(flags[name] ?? []), value];
    } else {
      flags[name] = value;
    }
  }

  return { positional, flags };
}
