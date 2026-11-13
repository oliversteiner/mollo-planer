#!/usr/bin/env node
import { existsSync, readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { basename, join, resolve } from 'node:path';
import { parse } from '../../core/parse.mjs';
import { validate } from '../../core/validate.mjs';
import { validatePlaner } from '../../core/validate-planer.mjs';
import { canonicalText } from '../../core/planer-canonical.mjs';
import { renderPlaner } from '../../core/render-planer.mjs';
import { HELPERS, runPlanerCommand } from '../planer-commands.mjs';
import { runConvert } from '../planer-convert.mjs';

const USAGE = `Verwendung:
  mollo-planer validate [datei]   Prüft die Datei (Fehler brechen ab, Hinweise nicht)
                                   version-*.md zeilengenau, *.planer.json (planer/2) mit Pfad
  mollo-planer json [datei]       Schreibt die Datei als JSON nach stdout
                                   version-*.md als gelesene Struktur, planer/2 kanonisch
  mollo-planer render [datei] [--out <datei>]
                                   Erzeugt die Markdown-Ansicht einer planer/2-Datei
  mollo-planer add|move|status|list|show|revision ...
                                   Helfer für planer/2, ändern nur die Datei (Hilfe: skills/planer/SKILL.md)
  mollo-planer convert v1-zu-v2 <version-*.md> [--out <datei>] [--name <Name>] [--locale DE|EN]
                                   Führt eine v1-Datei nach planer/2 über (nur lesen, Ausgabe nach stdout)
  mollo-planer convert md2json <datei.md> [--out <datei>]
                                   Liest die Markdown-Ansicht von planer/2 zurück in die Datei

Ohne Datei sucht das Werkzeug im aktuellen Ordner nach docs/version-*.md oder docs/*.planer.json.`;

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

function runValidate(argument) {
  const { path, markdown } = readInput(argument);
  const issues = isPlanerFile(path)
    ? validatePlaner(markdown)
    : validate(markdown);
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

/** Liest eine planer/2-Datei und gibt das Objekt zurück, oder null bei Fehlern (mit Meldung). */
function checkedPlan(markdown) {
  const errors = validatePlaner(markdown).filter((issue) => issue.severity === 'error');
  if (errors.length > 0) {
    const first = errors[0];
    console.error(`Datei ist ungültig, ${first.path || 'Datei'}: ${first.message}. Zuerst: mollo-planer validate`);
    return null;
  }
  return JSON.parse(markdown);
}

function runRender(argument, out) {
  const { path, markdown } = readInput(argument);
  if (!isPlanerFile(path)) {
    console.error('render gilt für planer/2 (*.planer.json).');
    return 2;
  }
  const plan = checkedPlan(markdown);
  if (plan === null) {
    return 1;
  }
  let text;
  try {
    text = renderPlaner(plan);
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

function main(argv) {
  const [command, argument, ...rest] = argv;

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
        return runValidate(argument);
      case 'json':
        return runJson(argument);
      case 'render':
        return runRender(argument, optionValue(rest, '--out'));
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

process.exitCode = main(process.argv.slice(2));
