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

Die Anmeldung braucht immer Passwort **und** 2FA-Code (oder einen Wiederherstellungscode), sonst sperrt das Backend die GraphQL-API.

Ablauf in Postman, Header `Origin: http://localhost:3000` bei jeder Anfrage:

1. `GET /sanctum/csrf-cookie`, danach bei jeder Anfrage den Header `X-XSRF-TOKEN` mit dem Wert des Cookies `XSRF-TOKEN` mitschicken (URL-dekodiert, also `%3D` als `=`; sonst antwortet Laravel mit 419)
2. `POST /login` mit `email` und `password`
3. `POST /two-factor-challenge` mit `code` (aus der App) oder `recovery_code`
4. `POST /graphql` mit der Abfrage, zum Beispiel `{ "query": "{ me { name } projects { name viewerRole } }" }`
