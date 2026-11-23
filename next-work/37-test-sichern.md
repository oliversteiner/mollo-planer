# 37 · TEST enthält echte Daten: Datenbank sichern, nichts überschreiben

Planer: planer.mollo.dev · Projekt 2 «Mollo Planer» · ToDo `t-379` (Status der Teile dort nachführen).

Status: Teil A umgesetzt: PR nach dev, nicht gemergt (Branch `chore/test-sichern`). Teile B bis E offen. Ursprünglich: Status: offen. Auftrag von Oliver über Stuart (2026-11-23). **Ersetzt** den früheren Auftrag «Dump auf TEST einspielen» (nicht mehr bauen). Branch von `origin/dev` (`chore/test-sichern`), PR nach `dev`, nicht mergen.

## Entscheid (Oliver, 2026-11-23)

`planer.mollo.dev` (TEST) ist ab jetzt die **einzige Quelle der Planung** für alle Projekte (Mollo Planer, Club DB, Mollo Planer iOS). Die Agenten arbeiten dort mit eigenen Konten. TEST enthält damit echte Daten, die nicht verloren gehen dürfen. Ein Dump auf TEST, `migrate:fresh` oder Seeder sind dort ab sofort tabu. PROD bleibt bis auf Weiteres ohne Planungsdaten.

Gleichzeitig wird TEST bei jedem Merge nach `dev` automatisch deployt, mit Migrationen auf diesen Daten. Heute sichert `deploy/deploy.sh` Dateien, aber **keine Datenbank**.

## A. Sicherung vor jeder Migration

* `deploy/deploy.sh`: vor `migrate --force` ein Dump der Datenbank (`mysqldump --single-transaction --no-tablespaces`, gzip) nach `DEPLOY_BACKUP_DIR/db/`, Dateiname mit Datum, Uhrzeit und Commit. `umask 077`. Zugangsdaten aus `backend/.env` über eine temporäre Optionsdatei mit Rechten 600 (`--defaults-extra-file`), die danach gelöscht wird; nie auf der Befehlszeile, nie in der Ausgabe.
* Schlägt der Dump fehl: **Abbruch** vor der Migration.
* Aufbewahren: die letzten 20 Sicherungen vor Deploys.
* Gilt für TEST und PROD gleich.

## B. Tägliche Sicherung

* Ein Skript `deploy/db-backup.sh` (gleiche Technik wie A) für einen Cronjob auf dem Server, täglich. Aufbewahren: 14 tägliche, dazu die vom 1. des Monats 6 Monate lang.
* `docs/deploy-template.md`: Cron-Eintrag für Hostpoint (Oliver richtet ihn ein), Speicherort, Platzbedarf melden.
* Optional, Vorschlag melden: Kopie ausser Haus (z. B. verschlüsselt ins private Repo oder auf einen zweiten Speicher). Nicht bauen ohne Entscheid.

## C. Zurückspielen

* `deploy/db-restore.sh <datei>` mit Rückfrage (Umgebung, Datei, Datum laut anzeigen; Eingabe des Umgebungsnamens zur Bestätigung). Vorher automatisch eine Sicherung des aktuellen Stands. Danach `optimize:clear`, `lighthouse:clear-cache`, `lighthouse:cache`.
* Anleitung in `docs/deploy-template.md`, einmal auf TEST mit Oliver geprobt.

## D. Schutz gegen Überschreiben

* `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe` und `db:seed` verweigern, wenn `APP_ENV` `staging` oder `production` ist (Laravel `prohibitDestructiveCommands` bzw. eigener Schutz), mit Test.
* Voraussetzung: TEST läuft mit `APP_ENV=staging` (Oliver stellt um; offener Punkt aus Auftrag 28). Im Abschlussbericht vermerken, ob das schon gilt.

## E. Konten-Verzeichnis (aus dem alten Auftrag übernommen, klein)

Das Verzeichnis der Verweise auf `users` (Teil A des alten Auftrags) wird trotzdem gebraucht, für das Starter-Kit und spätere Exporte. Nur das Verzeichnis und der Test, der neue Fremdschlüssel auf `users` erzwingt, unter `App\Core\…`. Kein Befehl zum Einspielen.

## Prüfen

Tests für D und E; `deploy.sh` mit einem Trockenlauf-Schalter oder in einer Testumgebung prüfen, dass der Dump vor der Migration entsteht und ein Fehlschlag abbricht. `composer test`, `test:mariadb`, `analyse`.

## Folgepunkt aus der Abnahme von A (Stuart, 23.11.)

PR #44 ist abgenommen. Offen: Schlägt die Sicherung fehl, ist der Code schon aktualisiert und läuft gegen das alte Schema. Die Sicherung vor `git reset`/Aktualisierung des Codes verschieben (oder bei Fehlschlag auf den vorherigen Commit zurücksetzen). Mit Test. Gehört zu B.
