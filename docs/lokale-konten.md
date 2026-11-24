# Lokale Konten bleiben bei jedem Neuaufbau der Datenbank

Auftrag 45. Gilt **nur lokal** (`APP_ENV=local` und eine Datenbank auf diesem Rechner: SQLite, Socket, `127.0.0.1`, `localhost`). TEST und PROD sind über Auftrag 37 geschützt (zerstörende Befehle gesperrt, Sicherung vor jeder Migration); dort tut nichts von hier etwas.

## Was passiert automatisch

Vor `migrate:fresh`, `migrate:refresh`, `migrate:reset` und `db:wipe` werden alle Konten gesichert. Danach kommen sie zurück: Passwort, zweiter Faktor (Secret, Wiederherstellungscodes, Bestätigung), Passkeys, persönliche Zugänge und Agent-Tokens, Systemrolle, Freigabe, bestätigte E-Mail, Einstellungen der Person, offene Einladungen und OAuth-Clients.

- Lässt sich nicht sichern, bricht der Befehl ab und löscht nichts.
- Nach `migrate:reset` und `db:wipe` gibt es noch keine Tabellen: das nächste `php artisan migrate` spielt die Konten zurück (eine Notiz `.restore-pending` im Sicherungsordner merkt sich die Datei).
- Zugeordnet wird **über die E-Mail** (Gross- und Kleinschreibung egal), die Systemrolle **über ihren Schlüssel** (`admin`, …). Gibt es ein Konto mit der E-Mail schon (z. B. aus einem Seeder), wird es überschrieben, sonst neu angelegt. Fehlt eine Rolle, gilt die Standardrolle, mit Hinweis.
- Mitgliedschaften in Projekten sind Inhalt, nicht Konto: sie werden nicht gesichert.
- Abschalten: `LOCAL_ACCOUNTS_KEEP=false` in `backend/.env`. In den Tests (`APP_ENV=testing`) und den Browser-Tests ist es immer aus.

## Von Hand

```bash
php artisan app:accounts-save              # sichern
php artisan app:accounts-restore           # die neueste Sicherung zurückspielen
php artisan app:accounts-restore <datei>   # eine bestimmte
```

Der Bericht nennt je E-Mail «überschrieben» oder «neu angelegt», nie ein Passwort, Secret oder Token.

## Daten von TEST lokal einspielen

```bash
~/www/_backups/hol-sicherungen.sh          # holt die Dumps von TEST nach ~/www/_backups/planer/test/db/
php artisan app:local-load-dump            # die neueste; oder: app:local-load-dump <datei.sql.gz>
```

Ablauf: lokale Konten sichern → Dump einspielen (Client `mariadb`, `LOCAL_MARIADB_CLIENT`) → `migrate` → alles entfernen, was auf TEST Zugang gibt (Tokens, Passkeys der Domain von TEST, Sitzungen, Einladungen, Anmeldungen von Anwendungen) → lokale Konten zurückspielen.

- Ein Konto, das es lokal und auf TEST gibt, behält die ID aus dem Dump, alle Inhalte zeigen weiter darauf; es bekommt die lokalen Zugangsdaten.
- Personen nur auf TEST bleiben als Konto (Autor von Inhalten), ohne Zugangsdaten: zufälliges Passwort, kein zweiter Faktor. Niemand kann sich lokal als sie anmelden; der lokale Dev-Server ist im WLAN erreichbar.

## Wo die Sicherungen liegen

`~/www/_backups/planer/local/konten-<datum>-<zeit>-<zufall>.json` (anders mit `LOCAL_ACCOUNTS_BACKUP_DIR`), Ordner nur für dich lesbar (700), Dateien 600. Die neuesten 20 bleiben.

> **Die Dateien enthalten Passwort-Hashes, 2FA-Secrets und Token-Hashes.** Nie ins Repo, nie nach `storage/`, nie weitergeben, nicht in Chats, Tickets oder Cloud-Ordner kopieren. Wer sie hat und den `APP_KEY` kennt, kann sich mit deinen lokalen Konten anmelden.

Die 2FA-Secrets sind mit dem `APP_KEY` verschlüsselt. Mit einem anderen `APP_KEY` warnt das Zurückspielen: Passwort, Passkeys und Zugänge gelten, die Authenticator-App nicht.

## Für Entwickler

- Code: `backend/app/Core/Accounts/Local` (Basis, Starter-Kit) und die Befehle in `backend/app/Core/Accounts/Console`.
- Welche Tabellen zum Konto gehören, steht in `AccountTables`; der Test `AccountTablesTest` schlägt an, wenn eine neue Tabelle nicht eingeordnet ist.
- Tokens kommen mit ihrer ID zurück: ein Sanctum-Token lautet `<id>|<geheimnis>` und wird zuerst über die ID gesucht.
