# Projektverwaltung (FCA DB)

Web-App zur Projektverwaltung: Milestones → ToDos → Tasks → Kanban, dazu ein Dashboard.

- `backend/`: Laravel mit GraphQL-API (Lighthouse), Anmeldung über Fortify
- `frontend/`: Nuxt (folgt)
- `docs/`: Entscheidungen und Bauplan (`backend-plan.md`, `graphql-kontext.md`)

## Demo-Daten

Zum Ausprobieren der API (zum Beispiel mit Postman) gibt es Demo-Daten wie in der GraphQL-Demo:

| Person | E-Mail | Rolle |
|---|---|---|
| Anna Keller | anna.keller@example.ch | Admin |
| Ben Müller | ben.mueller@example.ch | Owner „Website-Relaunch“ |
| Chiara Rossi | chiara.rossi@example.ch | Managerin „Website-Relaunch“, Owner „Sommerfest 2027“ |
| David Frei | david.frei@example.ch | Mitglied in beiden Projekten |

Dazu zwei Projekte mit Milestones, ToDos, Tasks und Kommentaren.

Die Demo-Daten laufen nur lokal, nie in Produktion. Alle Befehle in WSL im Ordner `backend/` ausführen.

### Einspielen (leere Datenbank)

```bash
php artisan migrate
php artisan db:seed --class=DemoSeeder
```

Der Befehl fragt nach einem Passwort, das für alle vier Personen gilt. Leer lassen, dann wird eines erzeugt. Ist die Datenbank nicht leer, bricht er ab und ändert nichts.

### Zurücksetzen

```bash
php artisan migrate:fresh --seed --seeder=DemoSeeder
```

**Achtung:** Das löscht alle Tabellen und damit alle Daten, auch eigene Einträge. Danach ist der Stand wieder wie nach dem ersten Einspielen, mit neuem Passwort und neuen 2FA-Secrets.

### Anmelden

Am Ende zeigt der Befehl einmalig an (danach nirgends mehr gespeichert, also gleich notieren):

- das Passwort für alle Demo-Personen
- je Person das 2FA-Secret und eine `otpauth://`-Adresse, zum Eintragen in eine Authenticator-App
- je Person 8 Wiederherstellungscodes, jeder nur einmal gültig

**Die Demo-Daten starten mit ausgeschalteter 2FA:** Für die Anmeldung genügt das Passwort, auch in Postman.

**2FA wieder einschalten** (zum Beispiel um den Login mit Code zu testen): als Admin (Anna Keller) in Postman „4 Admin / 2FA ein- oder ausschalten“ mit `true` ausführen. Dann braucht die Anmeldung Passwort **und** 2FA-Code (oder einen Wiederherstellungscode), mit den oben ausgegebenen 2FA-Secrets. Mit `false` wieder ausschalten. Das geht nur ausserhalb Produktion; auf dem Produktivserver ist die 2FA immer Pflicht.

### Postman-Collection

Die Collection liegt im Format Postman Collection v3 (YAML, eine Datei pro Anfrage) in `docs/postman/`, dem lokalen Ordner des Postman-Workspaces (`.postman/resources.yaml`). In Postman den Workspace mit diesem Ordner verbinden (Local View), dann erscheint die Collection „Projektverwaltung (FCA DB)“. Änderungen in Postman landen direkt in den YAML-Dateien und können committet werden.

Sie enthält die Anmeldung und Beispiel-Anfragen für alle wichtigen Abfragen und Änderungen, vorbelegt mit den IDs frisch eingespielter Demo-Daten.

1. Backend starten: `php artisan serve` (Adresse `http://localhost:8000`, Variable `baseUrl`)
2. In den Collection-Variablen `password` (und bei Bedarf `email`, Standard Ben Müller) als **Current value** eintragen, nie als Initial value
3. Im Ordner „1 Anmelden“ nacheinander: „CSRF-Cookie holen“, „Login (Passwort)“. Nur wenn die 2FA eingeschaltet ist: den aktuellen Code aus der Authenticator-App in `totpCode` eintragen und „2FA-Code bestätigen“
4. Danach laufen alle Anfragen in „2 Abfragen“ und „3 Änderungen“

Die Collection setzt `Origin`, `Referer` und `X-XSRF-TOKEN` selbst und meldet GraphQL-Fehler als fehlgeschlagenen Test. Ein Test im Backend (`PostmanCollectionTest`) prüft, dass alle Anfragen zum aktuellen Schema passen.

Ablauf von Hand (ohne Collection), Header `Origin: http://localhost:3000` bei jeder Anfrage:

1. `GET /sanctum/csrf-cookie`, danach bei jeder Anfrage den Header `X-XSRF-TOKEN` mit dem Wert des Cookies `XSRF-TOKEN` mitschicken (URL-dekodiert, also `%3D` als `=`; sonst antwortet Laravel mit 419)
2. `POST /login` mit `email` und `password`
3. `POST /two-factor-challenge` mit `code` (aus der App) oder `recovery_code`
4. `POST /graphql` mit der Abfrage, zum Beispiel `{ "query": "{ me { name } projects { name viewerRole } }" }`

## Claude anbinden (Inhalte per natürlicher Sprache)

Das Backend bietet einen MCP-Server unter `http://localhost:8000/mcp` (später auf dem API-Server). Claude handelt dort mit deinen Rechten: Projekte lesen, Projekte anlegen (Admins), Mitglieder setzen und ganze Projektpläne mit Milestones, ToDos und Tasks anlegen und zuweisen. Löschen kann Claude nichts.

1. In der App unter **Profil → Zugang für Claude** einen Zugang erzeugen und kopieren (wird nur einmal angezeigt, gilt 90 Tage).
2. **Claude Code in diesem Projekt:** Den Server kennt die `.mcp.json` schon. Den Zugang als Umgebungsvariable setzen und Claude Code neu starten:
   ```bash
   export PROJEKTVERWALTUNG_TOKEN="<Zugang>"
   ```
   **Woanders:** den in der App angezeigten Befehl ausführen (`claude mcp add --transport http projektverwaltung http://localhost:8000/mcp --header "Authorization: Bearer <Zugang>"`).
3. Dann einfach schreiben, zum Beispiel: „Erstelle ein Projekt für einen Podcast mit Milestones, ToDos und Tasks; Oliver macht Technik und Schnitt, Lindita moderiert.“

Die Personen müssen in der App schon ein Konto haben (Verwaltung → Benutzer). Zuweisungsmails gehen nur auf Wunsch raus.
