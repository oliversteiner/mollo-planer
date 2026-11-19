# 27 · Nacharbeit aus der Nachkontrolle 1.1 (Schema-Cache, Logo, RP-ID, Befehle, Apollo)

Status: erledigt (Commits `dc344f8` A, `d440780` B, `e71c5eb` C, `998d8bd` D, `8a56db2` E, `123cf49` F, auf `dev`); PR `dev` → `main` offen, nicht gemergt
Priorität: HOCH (Oliver, 2026-11-19). Branch: direkt auf `dev`, ein Commit je Teil, danach PR `dev` → `main` (Oliver merget). Vor dem Merge **nicht** freigeben, Stuart liest den PR.

## Befund (Nachkontrolle 1.1 in Produktion, 2026-11-19)

- Der Bestätigungslink auf dem iPad bestätigte, zeigte aber einen roten Toast «Die Anfrage ist fehlgeschlagen» statt des Hinweises «Melde dich jetzt an …» mit dem Knopf «Anmelden».
- Ursache: Die Viewer-Query lieferte ohne Sitzung «Internal server error». Im Log: `DefinitionException: Failed to find class App\GraphQL\Queries\UserSearch … for directive @field`. `UserSearch` gibt es seit `3ae8bb3` (16.11., vor 1.0) nicht mehr.
- Produktion las das Schema aus `bootstrap/cache/lighthouse-schema.php` (Schema-Cache ist ausserhalb von `APP_ENV=local` an, `config/lighthouse.php`). Die Datei ist nicht in Git, `git reset` lässt sie stehen, `deploy/deploy.sh` erneuert sie nicht. Das Schema in Produktion stammte damit aus einem Stand vor 1.0.
- Sofortmassnahme (Oliver, 17:24): `lighthouse:clear-cache` und `lighthouse:cache` auf dem Server. Danach geprüft: Viewer-Query ohne Sitzung → «Unauthenticated.», Bestätigungsseite zeigt Hinweis und «Anmelden».
- Die Browser-Tests (Auftrag 20) laufen mit `APP_ENV=local`, also ohne Schema-Cache; sie konnten den Fehler nicht finden.
- Zusätzlich: Auf den Seiten im Layout `auth` ist das Logo kein Link (`layouts/auth.vue`, Zeile 11). Wer dort ohne passenden Knopf landet, kommt nur über die Adresszeile weiter.

## A · `deploy/deploy.sh`: Schema-Cache erneuern (hoch)

- Nach `config:cache` (und vor `up`): `"$PHP" "$BACKEND/artisan" lighthouse:clear-cache` und `"$PHP" "$BACKEND/artisan" lighthouse:cache`. Prüfen, ob die Query-Cache-Einstellung (`query_cache`, Modus `store`) nach einer Schemaänderung ebenfalls geleert werden muss; wenn ja, mit erledigen und begründen, wenn nein, im Commit sagen, warum nicht.
- Rauchtest am Ende, **nach** `up`: ein POST an `$API_BASE/api` bzw. die lokale URL ohne Sitzung mit `{ viewerRights }` (über `curl` auf dem Server, mit `Accept: application/json`). Erwartet: die Antwort enthält `Unauthenticated.`. Sonst: Meldung «Rauchtest GraphQL fehlgeschlagen: <Antwort, gekürzt>» und Exit ≠ 0, damit der Lauf rot wird. Der Code ist dann schon live; die Meldung soll das sagen (kein automatisches Zurückrollen). Falls `curl` ohne CSRF-Cookie mit 419 antwortet, einen Weg wählen, der die Middleware der SPA nicht braucht (z. B. ohne `Origin`/`Referer`, damit Sanctum die Anfrage nicht als zustandsbehaftet behandelt), und erklären.
- Doku: `docs/deploy-template.md` (Ablauf, Stolpersteine: «Schema-Cache von Lighthouse liegt ausserhalb von Git»).
- Hinweis aus Auftrag 26 beachten: Der Server führt seine alte `deploy.sh` aus. Im PR-Text die Zeile für Oliver nennen: `git fetch origin main && git checkout origin/main -- deploy/deploy.sh` **vor** der Freigabe.

## B · Tests näher an Produktion (mittel)

- Die Browser-Tests (Auftrag 20, `e2e/support/environment.ts`) mit `APP_ENV=production` bzw. eingeschaltetem Schema-Cache laufen lassen, **und** vor dem Lauf `lighthouse:cache` ausführen, damit der Cache dem Code entspricht. Was dafür sonst umgestellt werden muss (z. B. `APP_DEBUG`, HTTPS-Annahmen, sichere Cookies), nennen; wenn `production` zu viele Annahmen bricht, `APP_ENV=staging` mit Schema-Cache an und begründen.
- Ein Backend-Test, der sicherstellt, dass `lighthouse:cache` mit dem aktuellen Schema ohne Fehler durchläuft (fängt fehlende Resolver-Klassen ab).

## C · Logo führt zur Startseite (niedrig, UI)

- Prozess (ui-consistency): kleine Änderung an einer Region → `adjusting` → `implementing` → `verifying` durch einen Agenten, der die Änderung nicht geschrieben hat.
- `layouts/auth.vue`: das Logo wird ein Link auf `/` (die Startseite leitet ohne Sitzung zur Anmeldung), mit zugänglichem Namen (z. B. «Mollo Planer, zur Startseite»). Wie das Logo im Haupt-Layout verlinkt ist, gilt als Vorbild.
- vitest: Logo im Layout `auth` ist ein Link auf `/`.

## Abnahme

- `composer test`, `composer analyse`, Pint, Frontend `lint`, `typecheck`, `test`, `test:e2e` (mit der Umstellung aus B) grün.
- `bash -n deploy/deploy.sh`; `shellcheck`, falls vorhanden.
- Commits `Fix: … (task 27 A)` usw., push auf `dev`, PR «Fix: Schema-Cache im Deploy, Logo-Link» öffnen, nicht mergen. Status oben setzen, anhalten.

## Ergänzungen aus der weiteren Nachkontrolle (Stuart, 2026-11-19, 18:30)

Die Nachkontrolle 1.1 in Produktion ist sonst grün: Registrierung, Mail, Link auf fremdem Gerät, Passkey und App, keine doppelte Passwortabfrage, Freigabe-Mail, Reset-Mails, neutrale Registrierung mit vergebener Adresse.

### D · Passkey-RP-ID ausdrücklich (hoch, nur Doku und Prüfung)

- **Befund:** Ein Passkey für die RP-ID `mollo.dev` (aus club-db) erschien auch beim Planer; die Anmeldung damit ergab 422. **Entscheid Oliver:** kein SSO vorerst, jede App hat ihre eigene RP-ID. Auf dem Server steht jetzt `PASSKEYS_RELYING_PARTY_ID=planer.mollo.dev`, alle Passkeys wurden zurückgesetzt.
- `backend/.env.example`: `PASSKEYS_RELYING_PARTY_ID` mit Kommentar (in Produktion **immer** setzen, gleich dem Host von `FRONTEND_URL`; nie die Hauptdomain, solange es kein SSO gibt; ein Wechsel macht alle Passkeys ungültig).
- `docs/deploy-template.md`: dasselbe als Pflichtwert und unter Stolpersteine.
- Rauchtest aus A um eine Zeile erweitern: `artisan config:show fortify.passkeys.relying_party_id` muss dem Host von `FRONTEND_URL` entsprechen, sonst Warnung im Log des Deploys (kein Abbruch).

### E · Befehl für Rolle und Freigabe (mittel)

- **Befund:** Nach `migrate:fresh` hatte sich Oliver selbst registriert; sein Konto war Mitglied ohne Freigabe. Zum Admin machen ging nur über `tinker`.
- Neuer Befehl in der Gruppe aus Auftrag 18: `app:user-role {user} {role?}` (Systemrolle setzen, ohne Argument: Auswahl; zeigt alte und neue Rolle) und `app:user-approve {user}` (Freigabe setzen, wenn sie fehlt, über dieselbe Logik wie `approveUser`/`ReviewRegistration`, inklusive Mail an die Person). Muster, Rückfrage, Terminal-Pflicht und Log wie `app:user-email` (`ManagesUserAccounts`). Der letzte Admin darf seine Admin-Rolle nicht verlieren (wie in der App, falls dort geregelt; sonst melden).
- Tests wie bei Auftrag 18. Doku in `docs/deploy-template.md` (Betrieb) und README.

### F · Apollo-Entwicklerwerkzeuge in Produktion aus (niedrig)

- **Befund:** In der Konsole von planer.mollo.dev steht «Download the Apollo DevTools…». Die Entwicklerwerkzeuge von Apollo sind im Produktions-Build an.
- Nur in der Entwicklung einschalten (`import.meta.dev` bzw. die Option des Apollo-Plugins). Prüfen im gebauten Frontend (`nuxt build` + `preview`): Meldung erscheint nicht.

Commits `… (task 27 D)` bis `(task 27 F)`, im selben PR.
