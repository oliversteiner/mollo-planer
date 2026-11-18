import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

/**
 * Prüfung der Vorlage für die GitHub Action (docs/auftrag-m5.md §3). Ohne YAML-Bibliothek: Aufbau und die
 * Zusagen der Vorlage als Text; mit `ruby` (falls vorhanden) zusätzlich ein echter YAML-Lauf.
 */
const path = fileURLToPath(new URL('../templates/github-action/planer-export.yml', import.meta.url));
const readme = readFileSync(fileURLToPath(new URL('../templates/github-action/README.md', import.meta.url)), 'utf8');
const yaml = readFileSync(path, 'utf8');

test('Vorlage: Auslöser, Rechte und Nebenläufigkeit', () => {
  assert.match(yaml, /^on:\n {2}repository_dispatch:\n {4}types: \[planer-export\]\n {2}schedule:\n {4}- cron: '17 \* \* \* \*'\n {2}workflow_dispatch:/m);
  assert.match(yaml, /^permissions:\n {2}contents: write$/m);
  assert.match(yaml, /^concurrency:\n {2}group: planer-export-\$\{\{ vars\.PLANER_FILE \}\}\n {2}cancel-in-progress: false$/m);
});

test('Vorlage: nur Leerzeichen, keine Tabs, keine Zugangsdaten im Text', () => {
  assert.doesNotMatch(yaml, /\t/);
  assert.doesNotMatch(yaml, /ghp_|github_pat_|\d+\|[A-Za-z0-9]{20,}/);
});

test('Vorlage: Werkzeug ohne Token ausgecheckt, nur tools/planer und spec, aus Git ausgeschlossen', () => {
  assert.match(yaml, /repository: \$\{\{ vars\.MOLLO_PLANER_REPOSITORY \|\| '[\w-]+\/mollo-planer' \}\}/);
  assert.match(yaml, /ref: \$\{\{ vars\.MOLLO_PLANER_REF \|\| 'main' \}\}/);
  assert.match(yaml, /sparse-checkout: \|\n\s+tools\/planer\n\s+spec/);
  assert.match(yaml, /path: \.planer-tool/);
  assert.match(yaml, /\/\.planer-tool\/' >> \.git\/info\/exclude/);
  const toolCheckout = yaml.split('Checkout von mollo-planer')[1].split('- name:')[0];
  assert.doesNotMatch(toolCheckout, /token:/);
});

test('Vorlage: pull mit Zugang aus Secrets, Datei und Projekt aus Variablen, Exit 3 und 6 mit Hinweis', () => {
  assert.match(yaml, /MOLLO_PLANER_URL: \$\{\{ secrets\.MOLLO_PLANER_URL \}\}/);
  assert.match(yaml, /MOLLO_PLANER_TOKEN: \$\{\{ secrets\.MOLLO_PLANER_TOKEN \}\}/);
  assert.match(yaml, /MOLLO_PLANER_PROJECT: \$\{\{ vars\.PLANER_PROJECT \}\}/);
  assert.match(yaml, /mollo-planer\.mjs pull "\$PLANER_FILE"/);
  assert.match(yaml, /"\$code" -eq 3/);
  assert.match(yaml, /push/);
  assert.match(yaml, /"\$code" -eq 6/);
  assert.match(yaml, /exit "\$code"/);
});

test('Vorlage: committet nur bei Änderung, mit der festen Nachricht und als github-actions[bot]', () => {
  assert.match(yaml, /git commit -m "Docs: planer export rev \$revision"/);
  assert.match(yaml, /user\.name "github-actions\[bot\]"/);
  assert.match(yaml, /Keine Änderung, nichts zu committen\./);
});

test('README: vier Schritte, 90 Tage, Webhook, Exit-Codes', () => {
  for (const heading of ['### 1. Zugang erzeugen', '### 2. Secrets und Variablen im Repository setzen', '### 3. Datei kopieren', '### 4. Webhook in der App einrichten']) {
    assert.ok(readme.includes(heading), heading);
  }
  assert.match(readme, /90 Tage/);
  assert.match(readme, /Exit-Code 6/);
  assert.match(readme, /Exit 3/);
  for (const name of ['MOLLO_PLANER_URL', 'MOLLO_PLANER_TOKEN', 'PLANER_PROJECT', 'PLANER_FILE', 'MOLLO_PLANER_REF']) {
    assert.ok(readme.includes(name) && yaml.includes(name), name);
  }
});

test('Vorlage ist gültiges YAML (mit ruby, falls vorhanden)', (t) => {
  const check = spawnSync('ruby', ['-ryaml', '-e', 'y = YAML.load_file(ARGV[0]); abort("keine Schritte") unless y["jobs"]["export"]["steps"].size >= 5', path], { encoding: 'utf8' });
  if (check.error) {
    t.skip('ruby nicht vorhanden');
    return;
  }
  assert.equal(check.status, 0, check.stderr);
});
