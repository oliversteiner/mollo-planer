#!/usr/bin/env node
import { existsSync, readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { basename, join, resolve } from 'node:path';
import { parse } from '../../core/parse.mjs';
import { validate } from '../../core/validate.mjs';
import { validatePlaner } from '../../core/validate-planer.mjs';
import { canonicalText } from '../../core/planer-canonical.mjs';
import { renderPlaner } from '../../core/render-planer.mjs';
import { templateIssues, toTemplate } from '../../core/template.mjs';
import { HELPERS, runPlanerCommand } from '../planer-commands.mjs';
import { runConvert } from '../planer-convert.mjs';
import { SYNC_COMMANDS, runSync } from '../planer-sync.mjs';

const USAGE = `Verwendung:
  mollo-planer validate [datei] [--template]   Prüft die Datei (Fehler brechen ab, Hinweise nicht)
                                   version-*.md zeilengenau, *.planer.json (planer-v2) mit Pfad;
                                   --template prüft zusätzlich die Vorlagen-Regeln (§3.8)
  mollo-planer template <datei> [--out <datei>]   Macht aus einer planer-v2-Datei eine Vorlage (§3.8)
  mollo-planer json [datei]       Schreibt die Datei als JSON nach stdout
                                   version-*.md als gelesene Struktur, planer-v2 kanonisch
  mollo-planer render [datei] [--out <datei>] [--lang de|en] [--legend-translations]
                                   Erzeugt die Markdown-Ansicht einer planer-v2-Datei
  mollo-planer add|move|status|list|show|revision ...
                                   Helfer für planer-v2, ändern nur die Datei (Hilfe: skills/planer/SKILL.md)
  mollo-planer convert v1-zu-v2 <version-*.md> [--out <datei>] [--name <Name>] [--locale DE|EN]
                                   Führt eine v1-Datei nach planer-v2 über (nur lesen, Ausgabe nach stdout)
  mollo-planer convert md2json <datei.md> [--out <datei>]
                                   Liest die Markdown-Ansicht von planer-v2 zurück in die Datei

  mollo-planer pull [datei] [--project <id>] [--force]
                                   Holt den Plan aus der App (MCP export_plan) und schreibt die Datei kanonisch.
                                   Hat die Datei „changed“, bricht pull ab; --force verwirft die Änderungen.
  mollo-planer push [datei] [--partial] [--force] [--yes]
                                   Trägt die Datei in die App ein (import_plan): erst ein Trockenlauf mit den Änderungen,
                                   Löschungen nur mit --yes. Schreibt danach die echten IDs und die neue Revision.
  mollo-planer sync [datei] [--yes]   Mit „changed“: push, dann pull. Sonst pull.
  mollo-planer pull --watch [--interval 60]
                                   pull in einer Schleife (Abstand mindestens 10 s), Strg-C beendet.

Ohne Datei sucht das Werkzeug im aktuellen Ordner nach docs/version-*.md oder docs/*.planer.json.

Verbindung (Vorrang von oben nach unten):
  Adresse   MOLLO_PLANER_URL, sonst "url" in .mollo-planer
  Zugang    MOLLO_PLANER_TOKEN, sonst "token" in .mollo-planer (nie als Option, wegen des Shell-Verlaufs)
  Projekt   --project, sonst MOLLO_PLANER_PROJECT, sonst "projects" in .mollo-planer (Pfad der Datei als Schlüssel)
.mollo-planer ist JSON, gehört nicht ins Repository und nur dem Benutzer: chmod 600 .mollo-planer.

Exit-Codes: 0 ok oder unverändert, 1 Aufruf falsch, 2 Datei ungültig, 3 lokale Änderungen,
            4 Revision weicht ab, 5 Löschungen ohne --yes, 6 Verbindung oder Zugang.`;

const isPlanerFile = (path) => path.endsWith('.planer.json');

function findDefaultFile() {
  const dir = join(process.cwd(), 'docs');
  if (!existsSync(dir)) {
    return null;
  }
  const matches = readdirSync(dir).filter((name) => /^version-.+\.md$/.test(name) || /\.planer\.json$/.test(name));
  if (matches.length !== 1) {
    return null;
  }
  return join(dir, matches[0]);
}

function readInput(argument) {
  const path = argument ? resolve(argument) : findDefaultFile();
  if (path === null) {
    throw new Error('Keine Datei angegeben, und docs/version-*.md ist nicht eindeutig.');
  }
  if (!existsSync(path)) {
    throw new Error(`Datei nicht gefunden: ${path}`);
  }
  return { path, markdown: readFileSync(path, 'utf8') };
}

function runValidate(argument, { template = false } = {}) {
  const { path, markdown } = readInput(argument);
  let issues = isPlanerFile(path)
    ? validatePlaner(markdown)
    : validate(markdown);
  if (template && isPlanerFile(path) && hasNoOutline(markdown)) {
    // Das Gerüst einer Vorlage ist optional (planer-abgleich §3.6): ohne Milestones ist sie gültig.
    issues = issues.filter((issue) => !(issue.severity === 'error' && issue.path === 'milestones'));
  }
  if (template && isPlanerFile(path) && issues.every((issue) => issue.severity !== 'error')) {
    issues.push(...templateIssues(JSON.parse(markdown)));
  } else if (template && !isPlanerFile(path)) {
    console.error('--template gilt für planer-v2 (*.planer.json).');
    return 2;
  }
  const errors = issues.filter((issue) => issue.severity === 'error').length;
  const warnings = issues.length - errors;

  for (const issue of issues) {
    const label = issue.severity === 'error' ? 'FEHLER' : 'Hinweis';
    const where = isPlanerFile(path)
      ? `${basename(path)}:${issue.path || 'Datei'}`
      : `${basename(path)}:${issue.line}`;
    console.log(`${where}  ${label}  ${issue.message}`);
  }

  if (errors > 0) {
    console.log(`\n${errors} Fehler, ${warnings} Hinweise.`);
    return 1;
  }
  console.log(`\nKeine Fehler. ${warnings} Hinweise.`);
  return 0;
}

/** Eine Datei mit `milestones: []`. */
function hasNoOutline(text) {
  try {
    const plan = JSON.parse(text);
    return Array.isArray(plan.milestones) && plan.milestones.length === 0;
  } catch {
    return false;
  }
}

/** Liest eine planer-v2-Datei und gibt das Objekt zurück, oder null bei Fehlern (mit Meldung). */
function checkedPlan(markdown) {
  const errors = validatePlaner(markdown).filter((issue) => issue.severity === 'error');
  if (errors.length > 0) {
    const first = errors[0];
    console.error(`Datei ist ungültig, ${first.path || 'Datei'}: ${first.message}. Zuerst: mollo-planer validate`);
    return null;
  }
  return JSON.parse(markdown);
}

function runRender(argument, out, { lang, legendTranslations }) {
  if (lang !== undefined && !['de', 'en'].includes(lang)) {
    console.error(`--lang: de oder en, nicht „${lang}“.`);
    return 2;
  }
  const { path, markdown } = readInput(argument);
  if (!isPlanerFile(path)) {
    console.error('render gilt für planer-v2 (*.planer.json).');
    return 2;
  }
  const plan = checkedPlan(markdown);
  if (plan === null) {
    return 1;
  }
  let text;
  try {
    text = renderPlaner(plan, { lang, legendTranslations });
  } catch (error) {
    console.error(error.message);
    return 1;
  }
  if (out) {
    writeFileSync(resolve(out), text);
    console.log(`Geschrieben: ${resolve(out)}`);
  } else {
    process.stdout.write(text);
  }
  return 0;
}

function runTemplate(argument, out) {
  const { path, markdown } = readInput(argument);
  if (!isPlanerFile(path)) {
    console.error('template gilt für planer-v2 (*.planer.json).');
    return 2;
  }
  const plan = checkedPlan(markdown);
  if (plan === null) {
    return 1;
  }
  const template = toTemplate(plan);
  const problems = templateIssues(template);
  if (problems.length > 0) {
    console.error(`Keine Vorlage: ${problems[0].path}: ${problems[0].message}`);
    return 1;
  }
  const text = canonicalText(template);
  const errors = validatePlaner(text).filter((issue) => issue.severity === 'error');
  if (errors.length > 0) {
    console.error(`Keine Vorlage: ${errors[0].path || 'Datei'}: ${errors[0].message}`);
    return 1;
  }
  if (out) {
    writeFileSync(resolve(out), text);
    console.log(`Geschrieben: ${resolve(out)}`);
  } else {
    process.stdout.write(text);
  }
  return 0;
}

function runJson(argument) {
  const { path, markdown } = readInput(argument);
  if (isPlanerFile(path)) {
    const plan = checkedPlan(markdown);
    if (plan === null) {
      return 1;
    }
    process.stdout.write(canonicalText(plan));
    return 0;
  }
  const issues = validate(markdown).filter((issue) => issue.severity === 'error');
  if (issues.length > 0) {
    const first = issues[0];
    console.error(`Datei ist ungültig, Zeile ${first.line}: ${first.message} Zuerst: mollo-planer validate`);
    return 1;
  }
  console.log(JSON.stringify(parse(markdown), null, 2));
  return 0;
}

function optionValue(args, name) {
  const index = args.indexOf(name);
  return index === -1 ? undefined : args[index + 1];
}

async function main(argv) {
  const [command, argument, ...rest] = argv;

  if (SYNC_COMMANDS.includes(command)) {
    return runSync(command, argv.slice(1), syncContext());
  }

  if (command === 'convert') {
    const result = runConvert(argv, { cwd: process.cwd() });
    if (result.stdout) process.stdout.write(result.stdout);
    if (result.stderr) process.stderr.write(result.stderr);
    return result.status;
  }

  if (HELPERS.includes(command)) {
    const result = runPlanerCommand(argv, {
      cwd: process.cwd(),
      readStdin: () => readFileSync(0, 'utf8'),
    });
    if (result.stdout) process.stdout.write(result.stdout);
    if (result.stderr) process.stderr.write(result.stderr);
    return result.status;
  }

  try {
    switch (command) {
      case 'validate':
        return runValidate(argument, { template: rest.includes('--template') });
      case 'template':
        return runTemplate(argument, optionValue(rest, '--out'));
      case 'json':
        return runJson(argument);
      case 'render':
        return runRender(argument, optionValue(rest, '--out'), {
          lang: optionValue(rest, '--lang') ?? undefined,
          legendTranslations: rest.includes('--legend-translations'),
        });
      case undefined:
      case '-h':
      case '--help':
        console.log(USAGE);
        return command === undefined ? 1 : 0;
      default:
        console.error(`Unbekannter Befehl: ${command}\n\n${USAGE}`);
        return 1;
    }
  } catch (error) {
    console.error(error.message);
    return 2;
  }
}

/** Kontext für pull, push und sync: Ausgabe, Zeit, Strg-C. */
function syncContext() {
  let stop = false;
  const wake = [];
  process.once('SIGINT', () => {
    stop = true;
    wake.splice(0).forEach((resolveSleep) => resolveSleep());
  });
  return {
    cwd: process.cwd(),
    env: process.env,
    fetch: globalThis.fetch,
    out: (line) => console.log(line),
    err: (line) => console.error(line),
    stopped: () => stop,
    // Der Timer hält die Event-Loop am Leben (kein unref): sonst beendet Node das Programm mitten im await.
    // Strg-C weckt den Schlaf und räumt den Timer ab.
    sleep: (ms) => new Promise((resolveSleep) => {
      const timer = setTimeout(done, ms);
      function done() {
        clearTimeout(timer);
        const index = wake.indexOf(done);
        if (index !== -1) {
          wake.splice(index, 1);
        }
        resolveSleep();
      }
      wake.push(done);
    }),
  };
}

process.exitCode = await main(process.argv.slice(2));
