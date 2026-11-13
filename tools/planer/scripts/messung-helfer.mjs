#!/usr/bin/env node
/**
 * Misst den Aufwand einer Änderung an einer planer/2-Datei, vorher und nachher.
 *
 * Vorher: der Agent liest die ganze Datei und schreibt sie komplett neu.
 * Nachher: der Agent ruft einen Helfer auf und liest dessen kurze Ausgabe.
 *
 * Gemessen werden Zeichen (Unicode-Zeichen), nicht Tokens. Die Tokenschätzung
 * (Zeichen / 4) ist eine grobe Näherung, weil hier kein Tokenizer läuft.
 *
 * Aufruf: node scripts/messung-helfer.mjs [datei]
 */
import { copyFileSync, mkdirSync, mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const root = resolve(fileURLToPath(new URL('..', import.meta.url)));
const repo = resolve(root, '..', '..');
const cli = join(root, 'cli', 'bin', 'mollo-planer.mjs');
const source = resolve(process.argv[2] ?? join(repo, 'spec', 'fixtures', 'beispiel-planer.planer.json'));
const original = readFileSync(source, 'utf8');
const chars = (text) => [...text].length;
const estimate = (count) => Math.round(count / 4);

/**
 * Jedes Szenario: Befehl, optional stdin. Der Befehl läuft auf einer Kopie der Datei.
 * `vorher` beschreibt, was der Agent ohne Helfer lesen und schreiben würde.
 */
const scenarios = [
  {
    name: 'Status setzen',
    args: ['status', 'k-004', 'review'],
    vorher: 'read+write',
  },
  {
    name: 'Task anlegen (Flags)',
    args: ['add', 'task', '--todo', 't-003', '--title', 'Neue Aufgabe', '--label', 'backend'],
    vorher: 'read+write',
  },
  {
    name: 'Task anlegen mit Beschreibung (JSON)',
    args: ['add', 'task', '--todo', 't-003', '--json'],
    stdin: JSON.stringify({
      title: 'Filter nach Status',
      description: 'Die Liste soll **Status** filtern.\n\n- nach Spalte\n- nach Zuständigem',
      userStory: { role: 'Mitglied', goal: 'Tasks nach Status filtern', benefit: 'ich den Stand schnell sehe' },
    }),
    vorher: 'read+write',
  },
  {
    name: 'Task verschieben',
    args: ['move', 'k-007', '--to', 't-003', '--position', '0'],
    vorher: 'read+write',
  },
  {
    name: 'Überblick (list statt Datei lesen)',
    args: ['list'],
    vorher: 'read',
  },
];

const dir = mkdtempSync(join(tmpdir(), 'mollo-messung-'));
const rows = [];

try {
  for (const scenario of scenarios) {
    mkdirSync(join(dir, 'docs'), { recursive: true });
    const file = join(dir, 'docs', 'messung.planer.json');
    copyFileSync(source, file);

    const result = spawnSync(process.execPath, [cli, ...scenario.args], {
      cwd: dir,
      encoding: 'utf8',
      input: scenario.stdin ?? '',
    });
    if (result.status !== 0) {
      throw new Error(`${scenario.name}: Befehl endete mit Code ${result.status}: ${result.stderr}`);
    }

    const commandChars = chars(['mollo-planer', ...scenario.args].join(' ') + (scenario.stdin ? ` <<< ${scenario.stdin}` : ''));
    const outputChars = chars(result.stdout);
    const nachher = commandChars + outputChars;

    const fileChars = chars(original);
    const vorher = scenario.vorher === 'read+write' ? fileChars * 2 : fileChars;

    rows.push({ name: scenario.name, vorher, nachher, commandChars, outputChars });
  }
} finally {
  rmSync(dir, { recursive: true, force: true });
}

const pad = (value, width) => String(value).padStart(width);
console.log(`Datei: ${source}`);
console.log(`Zeichen der Datei: ${chars(original)}\n`);
console.log('Szenario                                 vorher  nachher  Einsparung');
for (const row of rows) {
  const saved = row.vorher === 0 ? 0 : Math.round((1 - row.nachher / row.vorher) * 100);
  console.log(`${row.name.padEnd(40)} ${pad(row.vorher, 6)}  ${pad(row.nachher, 7)}  ${pad(`${saved} %`, 10)}`);
}
console.log('\nZeichen. Tokens ≈ Zeichen / 4 (grobe Näherung).');
console.log(`Vorher: Lesen (und bei Änderungen Neuschreiben) der ganzen Datei. Nachher: Befehl + Ausgabe.`);

