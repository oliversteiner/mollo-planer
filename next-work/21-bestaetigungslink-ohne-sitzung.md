# 21 · Bestätigungslink der Registrierungsmail ohne Anmeldung

Status: Backend und Frontend erledigt (Commits siehe `git log fix/verify-email-public`), Prüfung durch Agenten und Doku-Commit folgen
Priorität: HOCH (Oliver, 2026-11-18). Reihenfolge: nach Auftrag 19, vor den Aufträgen 22, 23 und 20, vor Auftrag 16.
Branch: `fix/verify-email-public` **von `fix/setup-security-redirect`** (Auftrag 19 liegt noch nicht in `dev`; die Aufträge 21, 22, 23 und 20 werden gestapelt, jeder auf dem vorigen). Nichts mergen und nichts auf `dev` oder `main` pushen, bis Oliver es sagt. Der Tag `v1.0.0` wird heute **nach** der Umsetzung des Onboardings gesetzt (Oliver); im Repo gibt es schon einen Tag dieses Namens, Bob fasst ihn nicht an und meldet es nur.
Stand der Vorgaben: überarbeitet 2026-11-18 (Fable, von Oliver bestätigt): Bestätigung erst per Klick, Link aus fester Konfiguration, Text nach der Bestätigung. Hintergrund: `docs/onboarding-gutachten.md`.
Prozess (ui-consistency): Backend-Teil ohne UI. Für die Frontend-Teile (`verify-email.vue`, `account-pending.vue`) zuerst `finding-patterns` (zwei Seiten, neue Meldungen), dann `implementing`, dann `verifying` durch einen Agenten, der die Änderung nicht geschrieben hat.

## Problem (Oliver, 2026-11-18, Testserver)

Der Link aus der Bestätigungsmail (`https://api.planer.mollo.dev/email/verify/{id}/{hash}?expires=…&signature=…`) funktioniert in Chrome, in Safari bricht er mit einem Fehler ab. Der Unterschied ist die Sitzung: Chrome ist noch von der Registrierung angemeldet, Safari nicht.

Ursache: Die Standardroute `verification.verify` von Fortify (`vendor/laravel/fortify/routes/routes.php`) trägt die Middleware `auth:web`, `signed`, `throttle`. Wer nicht angemeldet ist, wird abgewiesen. Das trifft jeden Nutzer, dessen Mailprogramm den Link in einem anderen Browser öffnet als dem der Registrierung (auf dem Handy der Normalfall). `config/fortify.php` und `FortifyServiceProvider` ändern daran nichts. Die Frontend-Seite `verify-email.vue` kennt den Fall «nicht angemeldet» schon (Hinweis «Melde dich an»), sie kommt nur nie dran.

## Ziel

Der Link bestätigt die Adresse in **jedem** Browser, ohne Sitzung, und führt auf eine verständliche Seite. Jeder Fehlerfall (abgelaufen, verändert, Konto weg, schon bestätigt) endet auf einer Seite mit klarer Meldung und einem Weg weiter, nie auf einem JSON-Fehler der API.

## Teil A · Backend: Link ohne Anmeldung, Bestätigung erst per Klick

Entscheid Oliver (2026-11-18): Der Aufruf des Links bestätigt **nicht**. Mail-Scanner und Link-Vorschauen rufen Links vorab auf; sonst könnte eine von fremder Hand registrierte Adresse ohne Zutun der Besitzerin «bestätigt» bei den Admins landen. Bestätigt wird erst, wenn die Person auf der Seite den Knopf drückt.

Zwei Routen, beide **ohne** `auth`, beide mit `throttle`:

| Route | Zweck | Antwort |
|---|---|---|
| `GET /email/verify/{id}/{hash}` (Name `verification.verify`; die Mail baut den Link weiter über diesen Namen) | prüft nur die Signatur; liest und schreibt nichts in der Datenbank | Weiterleitung auf `config('app.frontend_url').'/verify-email'`. Signatur gültig: `id`, `hash`, `expires`, `signature` unverändert als Query. Ungültig oder abgelaufen: `?status=invalid`. |
| `POST /email/verify/{id}/{hash}?expires=…&signature=…` | bestätigt | JSON `{ "status": "ok" }`, `"already"` oder `"invalid"`; immer HTTP 200, auch bei ungültiger Signatur; `429` bei Drossel |

- Laravels Signatur gilt für Pfad und Query, nicht für die Methode: Der POST auf dieselbe Adresse mit denselben Parametern besteht dieselbe Prüfung. Bob hält das in einem Test fest und **meldet**, falls es anders ist (keine zweite Signatur erfinden).
- Ablauf des POST: Signatur prüfen; Benutzer per `id` suchen; `hash_equals(sha1($user->getEmailForVerification()), $hash)`; schon bestätigt → nichts ändern, `already`; sonst `markEmailAsVerified()`, Ereignis `Verified` auslösen (Listener bleiben wirksam, z. B. die Meldung an die Admins), `ok`.
- `invalid` für alles andere: Signatur falsch oder abgelaufen, Hash passt nicht, Konto existiert nicht (z. B. nach `app:prune-unverified-users`). **Keine Auskunft über Konten:** Die Antwort verrät nicht, ob eine ID vergeben ist.
- `InvalidSignatureException` (403) wird **nur für diese zwei Routen** in die Weiterleitung bzw. in `invalid` übersetzt. Andere Routen behalten ihr Verhalten.
- **Niemand wird angemeldet.** Nach beiden Aufrufen ist `Auth::check()` falsch. (Ein Sitzungs-Cookie für den CSRF-Schutz darf entstehen; es meldet niemanden an.)
- Der POST kommt aus der SPA: CSRF wie bei den anderen Fortify-POSTs (Sanctum-Cookie), CORS-Pfad `email/*` (siehe Ergänzungen unten).
- Die zwei Routen **ersetzen** die Route von Fortify (`auth:web`, `signed`, `throttle`); sie dürfen von ihr nicht verdeckt werden (Test 4).
- Der Link bleibt 60 Minuten gültig (`auth.verification.expire`).
- Gilt unverändert für die Bestätigung nach einer E-Mail-Änderung im Profil (`UpdateUserProfileInformation` setzt `email_verified_at` zurück und verschickt dieselbe Mail). Das ist ein Testfall.

## Teil B · Frontend: `verify-email.vue`

| Aufruf der Seite | Anzeige |
|---|---|
| mit `id`, `hash`, `expires`, `signature` | Titel «E-Mail-Adresse bestätigen», ein Satz («Bestätige, dass diese Adresse dir gehört.»), Hauptknopf **«E-Mail-Adresse bestätigen»**. Der Knopf schickt den POST. **Kein** automatischer POST beim Laden der Seite. |
| Antwort `ok` | Erfolgsmeldung «Deine E-Mail-Adresse ist bestätigt.» |
| Antwort `already` | «Diese Adresse ist schon bestätigt.» |
| `status=invalid` in der Adresse, Antwort `invalid`, fehlende Parameter, alles andere | Fehlermeldung «Der Link ist ungültig oder abgelaufen» mit zwei Wegen: **«Bestätigungsmail erneut senden»** (Teil C) und «Zur Anmeldung» |

- **Nach `ok` und `already`, nicht angemeldet:** «Melde dich jetzt an und richte deinen zweiten Faktor ein. Ein Admin gibt dein Konto frei; du bekommst eine Mail.» Hauptknopf «Anmelden». Der heutige Text «Melde dich an, sobald ein Admin dein Konto freigegeben hat» ist für die Reihenfolge E-Mail → zweiter Faktor → Freigabe falsch und entfällt: Die Person soll **nicht** warten, sondern den Faktor gleich einrichten.
- **Nach `ok` und `already`, angemeldet:** weiter über den Zustand des Kontos (heute `load()`; nach Auftrag 23 F1 die gemeinsame Funktion), also zu «Konto absichern» oder «Freigabe ausstehend».
- Die heutige Seite zeigt immer «bestätigt», auch wenn nichts bestätigt wurde. Das darf nach dem Auftrag nicht mehr vorkommen.
- Neue Texte unter `auth.verify.*` in `i18n` (de, en). Keine fest eingebauten Texte.

## Teil C · Erneut senden

Heute gibt es dafür keinen Knopf (festgestellt auf `account-pending.vue`). Wer die Mail verpasst oder der Link abläuft, kommt nicht weiter, ausser über den Admin per SSH.

- **Angemeldet** (z. B. auf `account-pending.vue`, Zustand «unverified»): Knopf «Bestätigungsmail erneut senden» ruft die vorhandene Fortify-Route `POST /email/verification-notification` auf. Rückmeldung per Toast («Wir haben dir eine neue Mail geschickt»), Knopf danach für 60 Sekunden gesperrt (Drossel der API beachten, ihre Meldung verständlich anzeigen).
- **Nicht angemeldet** (nach `status=invalid`): neue, öffentliche Route, z. B. `POST /email/verification-notification/guest` mit der E-Mail-Adresse. Strenge Drossel (z. B. 3 pro Stunde je Adresse und je IP). Antwort **immer gleich neutral** (Status 202, «Wenn ein Konto mit dieser Adresse auf Bestätigung wartet, haben wir eine Mail geschickt»), egal ob die Adresse existiert, schon bestätigt ist oder nicht. Verschickt wird nur an unbestätigte Konten. Kein Hinweis, ob die Adresse bekannt ist.
- Der Versand läuft wie alle Mails über die Queue.
- Eingabe der Adresse: vorausgefüllt, wenn sie bekannt ist (die Seite weiss sie nach `invalid` nicht, also ein Feld mit Beschriftung, Validierung wie auf den anderen Auth-Seiten).

## Tests

Backend (Feature-Tests, ohne Sitzung aufgerufen, `Notification::fake()`, `Queue::fake()` wo nötig):
1. `GET` mit gültigem Link, ohne Sitzung: Weiterleitung auf `/verify-email` mit den vier Parametern; **nichts** bestätigt, kein Ereignis `Verified` (das ist der Schutz vor Mail-Scannern).
2. `POST` mit denselben Parametern, ohne Sitzung: `ok`, Adresse bestätigt, Ereignis ausgelöst, niemand angemeldet. Zweiter `POST`: `already`, Zeitstempel unverändert.
3. Abgelaufen, Signatur verändert, falscher Hash, unbekannte ID: `GET` leitet auf `status=invalid`, `POST` antwortet `invalid` mit HTTP 200; nichts bestätigt, kein 403/404/500 sichtbar.
4. Die Route von Fortify verdeckt die neuen nicht (`GET` und `POST` ohne Sitzung liefern Weiterleitung bzw. JSON, nicht 401/302 auf eine Anmeldeseite, nicht 500). Der `POST` mit dem `Origin` des Frontends bekommt die CORS-Köpfe.
5. Bestätigung nach E-Mail-Änderung im Profil.
6. Erneut senden (angemeldet): Mail geht raus, zweite Anfrage innerhalb der Drossel wird abgewiesen.
7. Erneut senden (öffentlich): bekannte unbestätigte Adresse, bestätigte Adresse und unbekannte Adresse geben **dieselbe** Antwort; nur im ersten Fall geht eine Mail raus; Drossel greift.
8. Das Ereignis `Verified` löst die Meldung an die Admins aus (wie bisher).

Frontend (vitest): `verify-email.vue` zeigt mit den vier Parametern den Knopf und schickt **erst nach dem Klick** den POST; je Antwort (`ok`, `already`, `invalid`) der richtige Zustand, angemeldet und nicht angemeldet; der Knopf «erneut senden» auf beiden Seiten.

## Prüfen im Browser

Bob prüft lokal mit zwei Browserprofilen (oder einem privaten Fenster): Registrieren in Profil 1, Link aus dem Mail-Log in Profil 2 öffnen: Die Seite zeigt den Knopf, die Adresse ist noch **nicht** bestätigt; nach dem Klick ist sie es. Dazu: Link ein zweites Mal, Link mit verändertem Zeichen in der Signatur, Link nach `app:prune-unverified-users --days=0`. Jeweils Zielseite und Text nennen. Die Browser-Konsole bleibt ohne Fehler.

## Abnahme

- Alle Tests grün (`php artisan test`, `npm test`), Lint und Typprüfung sauber.
- Drei Commits: Backend (A), Frontend (B und C), Doku.
- `docs/deploy-template.md` (Nachkontrolle nach dem Deploy) und `docs/umsetzung.md`: Punkt «Bestätigungslink in einem zweiten Browser ohne Sitzung öffnen» ergänzen. Status oben auf `erledigt (<Commit>)`.
- Auftrag 20 übernimmt die Szenarien 2 und 3 als Browser-Test.

## Ergänzungen aus dem Onboarding-Gutachten (Fable, 2026-11-18)

Gehören in diesen Auftrag, weil sie denselben Weg betreffen.

- **CORS:** `email/*` fehlt in `config/cors.php` (`paths`). Ohne den Eintrag scheitert der Aufruf von «erneut senden» (Teil C) im Browser, auch wenn Route und Knopf stimmen. Eintrag ergänzen und einen Test schreiben, der die Route mit dem `Origin` des Frontends aufruft und den CORS-Kopf prüft. (Die Route von Fortify ohne Sitzung führt vermutlich zu einem 500, weil bei `views => false` die Route `login` fehlt. Bob prüft das und nennt es im Bericht.)
- **Bestätigungsmail in die Queue:** `VerifyRegistrationEmail` erbt von `VerifyEmail` ohne `ShouldQueue` (Kommentar in `User.php` und `docs/registrierung-plan.md` verlangen die Queue). `ShouldQueue` und `afterCommit` ergänzen, wie bei `ResetPasswordNotification`. Test: die Registrierung antwortet, ohne auf den Mailversand zu warten (`Queue::fake()`, Benachrichtigung ist eingereiht).
- **Text der Mail:** Name aus der Registrierung nennen, die Frist («60 Minuten», aus `auth.verification.expire` lesen, nicht von Hand schreiben) und den Satz «Wenn du dich nicht registriert hast, klick nicht auf den Link». `lang/de` und `lang/en`, `mail.verify_email`.
- **Link aus fester Konfiguration (Pflicht, nicht mehr optional):** In der Queue gibt es keine laufende Anfrage; der Link entsteht dann aus `APP_URL`, nicht aus dem Host der Anfrage. Die Basis des Links kommt deshalb fest aus `config('app.url')`. Bob prüft lokal **mit laufendem Queue-Worker**, dass die Signatur beim Aufruf über die API-Adresse stimmt (auch hinter einem Proxy mit HTTPS: Schema und Host müssen dieselben sein wie beim Signieren), und nennt das Ergebnis im Commit. Vertrauenswürdige Hosts zusätzlich setzen, wenn es klein ist.
- **«Erneut senden», angemeldet:** `POST /email/verification-notification` antwortet mit `204`, wenn die Adresse inzwischen bestätigt ist, sonst `202`. Bei `204` prüft die Seite den Zustand des Kontos neu und geht weiter, statt «Mail geschickt» zu melden.
- **Mails brauchen bis zu einer Minute:** Auf dem Server arbeitet der Zeitplan die Queue jede Minute ab (`routes/console.php`). Die Texte nach der Registrierung und nach «erneut senden» sagen das («Das kann eine Minute dauern.»), siehe Auftrag 23 F2.
- Die Seiten «E-Mail bestätigen» und «Freigabe ausstehend» werden in **Auftrag 23 (F2)** ausgebaut. Hier genügen der Knopf und die Zustände aus Teil B und C; Auftrag 23 baut darauf auf.
