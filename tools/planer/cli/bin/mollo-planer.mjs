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

function runValidate(argument, { template = false } = {}) {
  const { path, markdown } = readInput(argument);
  const issues = isPlanerFile(path)
    ? validatePlaner(markdown)
    : validate(markdown);
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

process.exitCode = main(process.argv.slice(2));
