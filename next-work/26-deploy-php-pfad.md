# 26 · Deploy: PHP-Pfad ausdrücklich statt `php`

Status: erledigt (Commit siehe Abschlussmeldung, auf `dev`); PR `dev` → `main` offen, nicht gemergt
Priorität: HOCH (Oliver, 2026-11-19, 01:10): blockiert den Deploy von Release 1.1.
Branch: direkt auf `dev`, ein Commit, danach PR `dev` → `main` (Oliver merget). Ausnahme von der Regel «nichts pushen»: Oliver hat es so angewiesen.

## Problem

Der Deploy von PR #17 (Lauf 44421224689) brach in `deploy/deploy.sh` bei der Prüfung der PHP-Version ab: `php` liefert auf dem Server ohne Terminal **8.3.36**, das Backend verlangt 8.5. Es wurde nichts geändert (Prüfung vor Backup, Wartungsmodus und Code-Update). Das Skript ruft fest `PHP="php"` auf; welches `php` eine SSH-Sitzung ohne Terminal findet, hängt von der Umgebung des Servers ab und hat sich seit dem Deploy von 1.0 geändert.

## Soll

- `deploy.sh` nimmt PHP aus der Zeile `DEPLOY_PHP=` in `backend/.env` (voller Pfad, z. B. `/usr/local/php85/bin/php`). Fehlt sie oder ist sie leer, gilt wie bisher `php`.
- Die Funktion `env_value` (liest einen Wert aus `.env`, ohne die Datei zu laden) steht **vor** der Prüfung der Version und wird dafür benutzt. Fehlt `backend/.env`, darf das Skript nicht abbrechen, bevor es die Meldung zur PHP-Version zeigen kann (`grep` ohne Treffer und `set -e`/`pipefail` beachten).
- Ist der Pfad gesetzt, aber nicht ausführbar: Abbruch mit Meldung «DEPLOY_PHP zeigt auf <Pfad>, die Datei ist nicht ausführbar. Nichts wurde geändert.»
- Die Meldung bei zu alter Version nennt den benutzten Pfad und den Ausweg: «… gefunden: 8.3.36 (php). DEPLOY_PHP in backend/.env auf PHP 8.5 setzen, z. B. /usr/local/php85/bin/php. Nichts wurde geändert.»
- Alle Aufrufe (`artisan`, `composer`) laufen weiter über `"$PHP"`; nichts anderes ändert sich.
- **Reihenfolge im Skript beachten:** Die Prüfung bleibt vor jeder Änderung.

## Doku

- `backend/.env.example`: `DEPLOY_PHP=` mit Kommentar (nur auf dem Server nötig, leer = `php`).
- `docs/deploy-template.md`: im Abschnitt zur PHP-Version und bei «In `deploy.sh` die Variablen oben anpassen» `DEPLOY_PHP` nennen; den Hinweis ergänzen, dass `deploy.sh` die **auf dem Server liegende** Fassung ausführt, bevor es den neuen Code holt (eine Änderung an `deploy.sh` wirkt erst beim übernächsten Deploy, ausser man holt die Datei vorher von Hand: `git fetch origin main && git checkout origin/main -- deploy/deploy.sh`).

## Prüfung

- `bash -n deploy/deploy.sh`; `shellcheck deploy/deploy.sh`, falls vorhanden (sonst sagen).
- Lokaler Trockentest nur der Prüfung: eine Kopie des Skripts mit `APP` auf einen Testordner mit einer `backend/.env`, die `DEPLOY_PHP` (a) auf ein PHP 8.5, (b) auf einen nicht vorhandenen Pfad, (c) gar nicht setzt; jeweils die Ausgabe in die Abschlussmeldung. Das Skript **nicht** gegen echte Ordner laufen lassen; nach der Prüfung abbrechen (z. B. leere stdin, dann bricht `tar` vor jeder Änderung ab).
- `composer test` muss nicht laufen (keine PHP-Änderung), Lint/Tests der CI laufen im PR.

## Abschluss

Ein Commit `Fix: deploy reads the PHP path from DEPLOY_PHP (task 26)` auf `dev`, pushen, PR `dev` → `main` mit Titel «Fix: PHP-Pfad im Deploy» öffnen. Status oben setzen, anhalten. **Nicht** mergen.
