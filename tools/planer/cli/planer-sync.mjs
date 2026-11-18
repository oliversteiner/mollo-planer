/**
 * pull, push, sync und pull --watch (docs/auftrag-m5.md §2.2). Die Datei ist die Quelle im Repository,
 * die App zählt die Revision. Das Werkzeug committet nie.
 *
 * Jede Funktion bekommt einen Kontext { cwd, env, fetch, out, err, sleep, stopped } und liefert den Exit-Code:
 * 0 ok oder unverändert, 1 Aufruf falsch, 2 Datei ungültig, 3 lokale Änderungen, 4 Revision, 5 Löschungen ohne
 * --yes, 6 Verbindung oder Zugang.
 */
import { existsSync, mkdirSync, readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { canonicalText } from '../core/planer-canonical.mjs';
import { validatePlaner } from '../core/validate-planer.mjs';
import { McpClient, RemoteError, ToolError, rememberProject, resolveSettings } from './planer-remote.mjs';

export const EXIT = { OK: 0, USAGE: 1, INVALID: 2, LOCAL_CHANGES: 3, REVISION: 4, DELETIONS: 5, REMOTE: 6 };

export const SYNC_COMMANDS = ['pull', 'push', 'sync'];

/** Liest die Optionen. Der Zugang hat absichtlich keine Option (Shell-Verlauf). */
export function parseSyncArgs(rest) {
  const args = { file: undefined, project: undefined, force: false, yes: false, partial: false, watch: false, interval: 60 };
  for (let i = 0; i < rest.length; i++) {
    const arg = rest[i];
    switch (arg) {
      case '--project': args.project = rest[++i]; break;
      case '--interval': args.interval = Number(rest[++i]); break;
      case '--force': args.force = true; break;
      case '--yes': args.yes = true; break;
      case '--partial': args.partial = true; break;
      case '--watch': args.watch = true; break;
      default:
        if (arg.startsWith('--')) {
          throw new RemoteError(`Unbekannte Option: ${arg}`, { exit: EXIT.USAGE });
        }
        if (args.file !== undefined) {
          throw new RemoteError('Nur eine Datei angeben.', { exit: EXIT.USAGE });
        }
        args.file = arg;
    }
  }
  if (!Number.isFinite(args.interval)) {
    throw new RemoteError('--interval braucht eine Zahl (Sekunden).', { exit: EXIT.USAGE });
  }
  return args;
}

/** Führt einen Befehl aus und fängt die erwarteten Fehler ab. */
export async function runSync(command, rest, ctx) {
  try {
    const args = parseSyncArgs(rest);
    if (command === 'pull') {
      return args.watch ? await watch(ctx, args) : await pull(ctx, args);
    }
    if (command === 'push') {
      return await push(ctx, args);
    }
    return await sync(ctx, args);
  } catch (error) {
    if (error instanceof RemoteError) {
      report(ctx, error);
      return error.exit;
    }
    throw error;
  }
}

function report(ctx, error) {
  const errors = error.payload?.errors ?? error.errors;
  if (Array.isArray(errors) && errors.length > 0) {
    ctx.err(`${errors.length === 1 ? 'Fehler' : `${errors.length} Fehler`} beim Eintragen, es wurde nichts geschrieben:`);
    for (const item of errors) {
      ctx.err(`  ${item.path ? `${item.path}: ` : ''}${item.message}`);
    }
    return;
  }
  ctx.err(error.message);
}

/** Die Datei: das Argument, sonst die einzige docs/*.planer.json. */
function filePath(ctx, args) {
  if (args.file) {
    return resolve(ctx.cwd, args.file);
  }
  const dir = join(ctx.cwd, 'docs');
  const found = existsSync(dir) ? readdirSync(dir).filter((name) => name.endsWith('.planer.json')) : [];
  if (found.length !== 1) {
    throw new RemoteError('Keine Datei angegeben, und docs/*.planer.json ist nicht eindeutig.', { exit: EXIT.USAGE });
  }
  return join(dir, found[0]);
}

function connect(ctx, args, path) {
  const settings = resolveSettings({ env: ctx.env, cwd: ctx.cwd, filePath: path, projectOption: args.project, warn: ctx.err });
  if (settings.project === undefined) {
    throw new RemoteError('Kein Projekt. --project <id> angeben, MOLLO_PLANER_PROJECT setzen oder die Datei in .mollo-planer unter „projects“ eintragen.', { exit: EXIT.USAGE });
  }
  const client = new McpClient({ url: settings.url, token: settings.token, fetch: ctx.fetch });
  return { client, project: settings.project, configPath: settings.configPath };
}

function readLocal(path) {
  if (!existsSync(path)) {
    return null;
  }
  const text = readFileSync(path, 'utf8');
  try {
    return { text, doc: JSON.parse(text) };
  } catch {
    throw new RemoteError(`${path} ist keine gültige JSON-Datei.`, { exit: EXIT.INVALID });
  }
}

/** Der Plan der App. Den Hinweis `$schema` einer vorhandenen Datei behält die neue. */
async function exportDocument(client, project, schemaHint) {
  const { document } = await client.call('export_plan', { project_id: project });
  return schemaHint ? { $schema: schemaHint, ...document } : document;
}

function writeCanonical(path, doc) {
  mkdirSync(dirname(path), { recursive: true });
  writeFileSync(path, canonicalText(doc));
}

export async function pull(ctx, args, { quietUnchanged = false } = {}) {
  const path = filePath(ctx, args);
  const { client, project, configPath } = connect(ctx, args, path);
  const local = readLocal(path);

  if (local?.doc?.changed === true && !args.force) {
    ctx.err('Lokale Änderungen. Zuerst `push` oder `sync`, oder `pull --force` (verwirft sie).');
    return EXIT.LOCAL_CHANGES;
  }

  const doc = await exportDocument(client, project, local?.doc?.$schema);
  const text = canonicalText(doc);

  if (args.project !== undefined && configPath) {
    rememberProject(configPath, path, args.project);
  }

  if (local && local.text === text) {
    if (!quietUnchanged) {
      ctx.out(`unverändert (Revision ${doc.revision})`);
    }
    return EXIT.OK;
  }

  mkdirSync(dirname(path), { recursive: true });
  writeFileSync(path, text);
  if (!local) {
    ctx.out(`Angelegt: ${path} (Revision ${doc.revision})`);
  } else if (local.doc.changed === true) {
    ctx.out(`Lokale Änderungen verworfen. Revision ${local.doc.revision} → ${doc.revision}`);
  } else {
    ctx.out(`Revision ${local.doc.revision} → ${doc.revision}`);
  }
  return EXIT.OK;
}

/** import_plan; Fehler der App kommen als ToolError mit `errors`. */
async function importPlan(client, project, plan, mode, dryRun) {
  return client.call('import_plan', { project_id: project, plan, mode, dry_run: dryRun, notify_assignees: false });
}

function summary(changes) {
  const count = (kind) => changes.filter((change) => change.kind === kind).length;
  const parts = [[count('CREATE'), 'neu'], [count('UPDATE'), 'geändert'], [count('MOVE'), 'verschoben'], [count('DELETE'), 'gelöscht']]
    .filter(([n]) => n > 0)
    .map(([n, label]) => `${n} ${label}`);
  return `${changes.length} ${changes.length === 1 ? 'Änderung' : 'Änderungen'}: ${parts.join(', ')}`;
}

function revisionAdvice(ctx, fileRevision, appRevision) {
  ctx.err(`Die App ist bei Revision ${appRevision}, die Datei bei ${fileRevision}.`);
  ctx.err('Zuerst `pull`: Es bricht ab, solange die Datei lokale Änderungen hat, nichts geht verloren.');
  ctx.err('Eigene Änderungen übernimmst du so: Datei sichern, `pull --force`, die Änderungen mit den Helfern (add, move, status) neu anwenden, dann `push`.');
}

export async function push(ctx, args) {
  const path = filePath(ctx, args);
  const local = readLocal(path);
  if (!local) {
    ctx.err(`Datei nicht gefunden: ${path}`);
    return EXIT.INVALID;
  }

  const errors = validatePlaner(local.text).filter((issue) => issue.severity === 'error');
  if (errors.length > 0) {
    ctx.err(`Datei ist ungültig (${errors.length} Fehler), nichts wird eingetragen:`);
    for (const issue of errors) {
      ctx.err(`  ${issue.path || 'Datei'}: ${issue.message}`);
    }
    return EXIT.INVALID;
  }

  if (local.doc.changed !== true) {
    ctx.out('Nichts einzutragen.');
    return EXIT.OK;
  }

  const { client, project } = connect(ctx, args, path);
  const mode = args.partial ? 'partial' : 'full';

  if (args.partial && !args.force) {
    const app = await exportDocument(client, project);
    if (app.revision !== local.doc.revision) {
      revisionAdvice(ctx, local.doc.revision, app.revision);
      ctx.err('Mit --force trägst du die Teile trotzdem ein.');
      return EXIT.REVISION;
    }
  }

  let dry;
  try {
    dry = await importPlan(client, project, local.doc, mode, true);
  } catch (error) {
    if (error instanceof ToolError && error.payload?.errors?.some((item) => item.kind === 'revision')) {
      const app = await exportDocument(client, project);
      revisionAdvice(ctx, local.doc.revision, app.revision);
      return EXIT.REVISION;
    }
    throw error;
  }

  const deletions = dry.changes.filter((change) => change.kind === 'DELETE');
  if (dry.changes.length > 0) {
    ctx.out(summary(dry.changes));
  }

  if (deletions.length > 0 && !args.yes) {
    ctx.err(`Die Datei lässt ${deletions.length} ${deletions.length === 1 ? 'Element' : 'Elemente'} der App weg. Sie kämen in den Papierkorb:`);
    for (const change of deletions) {
      ctx.err(`  ${change.title ?? change.id}`);
    }
    ctx.err('Mit --yes eintragen, nachdem ein Mensch das bestätigt hat.');
    return EXIT.DELETIONS;
  }

  const schema = local.doc.$schema;

  if (dry.changes.length === 0) {
    const { changed: _changed, ...rest } = local.doc;
    writeCanonical(path, rest);
    ctx.out('Keine Änderungen gegenüber der App. „changed“ entfernt.');
    return EXIT.OK;
  }

  const real = await importPlan(client, project, local.doc, mode, false);
  writeCanonical(path, schema ? { $schema: schema, ...real.document } : real.document);
  ctx.out(`Eingetragen. Revision ${local.doc.revision} → ${real.document.revision}`);
  return EXIT.OK;
}

export async function sync(ctx, args) {
  const path = filePath(ctx, args);
  const local = readLocal(path);

  if (local?.doc?.changed === true) {
    const code = await push(ctx, args);
    if (code !== EXIT.OK) {
      return code;
    }
  }
  return pull(ctx, { ...args, force: false });
}

/** pull in einer Schleife. Fehler 429 und Netz brechen nicht ab, ein ungültiger Zugang schon. */
export async function watch(ctx, args) {
  const seconds = Math.max(10, args.interval);
  const path = filePath(ctx, args);
  let warned = false;

  while (!ctx.stopped()) {
    try {
      const local = readLocal(path);
      if (local?.doc?.changed === true && !args.force) {
        if (!warned) {
          ctx.out('Lokale Änderungen in der Datei: pull wartet. `push` oder `sync` trägt sie ein.');
          warned = true;
        }
      } else {
        warned = false;
        await pull(ctx, args, { quietUnchanged: true });
      }
    } catch (error) {
      if (!(error instanceof RemoteError) || !['rate', 'network'].includes(error.kind)) {
        throw error;
      }
      ctx.err(`${error.message} Nächster Versuch in ${seconds} s.`);
    }

    if (ctx.stopped()) {
      break;
    }
    await ctx.sleep(seconds * 1000);
  }
  return EXIT.OK;
}
