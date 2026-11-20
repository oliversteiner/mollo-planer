import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { basename, resolve } from 'node:path';
import { planerFromV1, parseReleaseDates, ConvertError } from '../core/v1-to-planer.mjs';
import { planerFromMarkdown, MarkdownError } from '../core/planer-from-md.mjs';
import { canonicalText } from '../core/planer-canonical.mjs';
import { validatePlaner } from '../core/validate-planer.mjs';
import { parseArgs } from './planer-commands.mjs';

/**
 * Befehle `convert v1-zu-v2 <version-*.md>` und `convert md2json <datei.md>`.
 * Liefert { status, stdout, stderr }. Schreibt nur, wenn --out angegeben ist.
 */

const USAGE = `Verwendung:
  mollo-planer convert v1-zu-v2 <version-<name>.md> [--out <datei>] [--name <Projektname>] [--locale DE|EN]
                                   [--release-dates <datei.csv>]   Datum je veröffentlichter Version: Zeilen „Version,JJJJ-MM-TT“
                                   [--labels <datei.json>]         Namen, Farben, Kategorie der Labels: { "fix": { "names": {"de":"…","en":"…"}, "color": "red" } }
  mollo-planer convert md2json <datei.md> [--out <datei>]`;

export function runConvert(argv, options) {
  try {
    return convert(argv, options);
  } catch (error) {
    if (error instanceof ConvertError || error instanceof MarkdownError) {
      return { status: 1, stdout: '', stderr: `${error.message}\n` };
    }
    throw error;
  }
}

function convert(argv, options) {
  const { positional, flags } = parseArgs(argv.slice(1));
  const [mode, file] = positional;

  if (!['v1-zu-v2', 'md2json'].includes(mode) || !file) {
    return fail(`convert braucht einen Modus und eine Datei.\n\n${USAGE}`, 2);
  }
  for (const name of Object.keys(flags)) {
    if (!['out', 'name', 'locale', 'release-dates', 'labels'].includes(name)) {
      return fail(`Unbekannte Option --${name}.\n\n${USAGE}`, 2);
    }
  }

  const path = resolve(options.cwd, file);
  if (!existsSync(path)) {
    return fail(`Datei nicht gefunden: ${path}`, 2);
  }
  const markdown = readFileSync(path, 'utf8');

  let plan;
  const notes = {};
  if (mode === 'v1-zu-v2') {
    const locale = flags.locale ?? 'DE';
    if (!['DE', 'EN'].includes(locale)) {
      return fail(`--locale: DE oder EN, nicht „${locale}“.`, 2);
    }
    const name = flags.name ?? nameFromFile(basename(path));
    if (name === undefined) {
      return fail('Der Projektname lässt sich aus dem Dateinamen nicht ableiten. Mit --name angeben.', 2);
    }
    let releaseDates = new Map();
    if (flags['release-dates'] !== undefined) {
      const datesPath = resolve(options.cwd, flags['release-dates']);
      if (!existsSync(datesPath)) {
        return fail(`--release-dates: Datei nicht gefunden: ${datesPath}`, 2);
      }
      releaseDates = parseReleaseDates(readFileSync(datesPath, 'utf8'));
    }
    let labels = {};
    if (flags.labels !== undefined) {
      const labelsPath = resolve(options.cwd, flags.labels);
      if (!existsSync(labelsPath)) {
        return fail(`--labels: Datei nicht gefunden: ${labelsPath}`, 2);
      }
      try {
        labels = JSON.parse(readFileSync(labelsPath, 'utf8'));
      } catch (error) {
        return fail(`--labels: keine gültige JSON-Datei: ${error.message}`, 2);
      }
      if (labels === null || typeof labels !== 'object' || Array.isArray(labels)) {
        return fail('--labels: erwartet ein JSON-Objekt, Schlüssel → { names, color, category }.', 2);
      }
    }
    plan = planerFromV1(markdown, { name, locale, releaseDates, labels, notes });
  } else {
    plan = planerFromMarkdown(markdown);
  }

  const text = canonicalText(plan);
  const errors = validatePlaner(text).filter((issue) => issue.severity === 'error');
  if (errors.length > 0) {
    const first = errors[0];
    return fail(`Das Ergebnis ist ungültig, ${first.path || 'Datei'}: ${first.message}`, 1);
  }

  const hints = hintLines(notes);
  if (flags.out !== undefined) {
    const out = resolve(options.cwd, flags.out);
    writeFileSync(out, text);
    return { status: 0, stdout: `Geschrieben: ${out}\n`, stderr: hints };
  }
  return { status: 0, stdout: text, stderr: hints };
}

/** Die Hinweise der Umwandlung, gesammelt am Ende: jede Stelle, dann die Zahlen. */
function hintLines(notes) {
  const lines = [];
  for (const cut of notes.shortenedTitles ?? []) {
    lines.push(`Hinweis, Zeile ${cut.line}: ${cut.what}-Titel gekürzt\n  alt: ${cut.old}\n  neu: ${cut.new}`);
  }
  for (const entry of notes.multipleCommits ?? []) {
    lines.push(`Hinweis, Zeile ${entry.line}: mehrere Commits, der letzte ist der commit, die übrigen stehen in der Beschreibung („Weitere Commits: …“): ${entry.title}`);
  }
  if (notes.datesNotUsed?.length > 0) {
    lines.push(`Hinweis: ${notes.datesNotUsed.length} Daten nicht übernommen, die Versionen sind nur geplant: ${notes.datesNotUsed.map((version) => `v${version}`).join(', ')}.`);
  }
  if (notes.datesForUnknownVersions?.length > 0) {
    lines.push(`Hinweis: ${notes.datesForUnknownVersions.length} Daten für Versionen, die die Datei nicht hat: ${notes.datesForUnknownVersions.map((version) => `v${version}`).join(', ')}.`);
  }
  const summary = [];
  if (notes.shortenedTitles?.length > 0) summary.push(`${notes.shortenedTitles.length} Titel gekürzt`);
  if (notes.newLabels?.length > 0) summary.push(`${notes.newLabels.length} neue Labels (${notes.newLabels.join(', ')})`);
  if (notes.releasedWithoutDate?.length > 0) summary.push(`${notes.releasedWithoutDate.length} veröffentlichte Milestones ohne Datum (mit --release-dates setzen)`);
  if (notes.multipleCommits?.length > 0) summary.push(`${notes.multipleCommits.length} Tasks mit mehreren Commits`);
  if (summary.length > 0) lines.push(`Zusammenfassung: ${summary.join(', ')}.`);
  if (lines.length === 0) return '';
  return `${lines.join('\n')}\n`;
}

/** version-<name>.md → <name>, wenn das mindestens zwei Zeichen lang ist. */
function nameFromFile(fileName) {
  const match = fileName.match(/^version-(.+)\.md$/);
  if (!match || match[1].length < 2) return undefined;
  return match[1];
}

function fail(message, status) {
  return { status, stdout: '', stderr: `${message}\n` };
}
