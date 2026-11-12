# Selbstregistrierung mit Passwort: Plan

Stand: 2026-11-12. Status: **Backend und Frontend umgesetzt, Browserprüfung offen.** Entscheidung: Registrierung mit Passwort, E-Mail-Bestätigung und Freigabe durch einen Admin. Dieser Plan ersetzt den Beschluss „Keine Selbstregistrierung“ in `backend-plan.md` (Punkt 16) und `claude-desktop-kontext.md`.

## Ziel
Eine Person registriert sich selbst mit Name, E-Mail-Adresse und Passwort. Sie hat erst Zugriff, wenn ein Admin das Konto freigibt. Die Einladung durch Admins bleibt bestehen.

## Entscheidungen
1. **Freigabe durch Admin:** Neue Konten warten, bis ein Admin sie freigibt. Bis dahin sehen sie keine Daten.
2. **E-Mail-Bestätigung:** Pflicht, damit niemand fremde Adressen belegt.
3. **Rolle:** Neue Konten bekommen die Systemrolle `mitglied` und keine Projektzugehörigkeit.
4. **Pflicht-2FA bleibt:** Nach der Bestätigung führt der Weg zu `/setup-security`, wie bei der Einladung.
5. **Passwort-Regeln** wie bei der Einladung (`updatePasswords`).
6. **Sprache:** Die Sprache der Anfrage bestimmt die Mails. Der Hinweis an Admins folgt der Sprache des Admins.

## Backend (`backend/`)
1. **Fortify:** Feature `registration` aktivieren, Route `POST /register` (REST, wie Login). Der Kommentar in `config/fortify.php` wird angepasst.
2. **E-Mail-Bestätigung:** Feature `emailVerification`, Verifikationslink mit Frist.
3. **Freigabe:** Migration für `approved_at` (nullable) auf `users`. Ohne Freigabe liefert `visibleToViewer` nichts, und Policies verweigern Schreibzugriffe. Die Authentifizierung läuft weiter, damit die Seite „wartet auf Freigabe“ erreichbar ist.
4. **Mutationen:** `approveUser(id)` und `rejectUser(id)`, nur für Admins. Die Policy prüft über `SystemPermissions`. Ablehnen löscht das unbestätigte Konto.
5. **Rate-Limit** auf `POST /register`, ähnlich dem Login.
6. **Notifications** (`ShouldQueue`, `afterCommit`): Bestätigungsmail und Hinweis an Admins, dass ein Konto wartet.
7. **Tests:** Registrierung, Bestätigung, Freigabe und Ablehnung, kein Zugriff vor Freigabe, Rate-Limit, Policy für `approveUser`. Danach `composer test:mariadb`, weil sich Migrationen ändern.
8. **Doku:** `backend-plan.md` (Punkt 16 und Stand), `claude-desktop-kontext.md` (Abschnitt Anmeldung), `CLAUDE.md` (Stand nach jedem abgeschlossenen Schritt).

## Frontend (`frontend/`)
1. **`/register`** (öffentlich, Layout `auth`): Name, E-Mail, Passwort, Bestätigung. Hinweis, dass ein Admin freigibt.
2. **`/verify-email`:** Bestätigung des Links. Danach Hinweis „wartet auf Freigabe“.
3. **`/login`:** Link „Konto anlegen“. Konten ohne Freigabe sehen eine Wartesseite statt der App.
4. **`/admin/users`:** Abschnitt „Wartet auf Freigabe“ mit den Knöpfen Freigeben und Ablehnen. Ablehnen mit Rückfrage.
5. **Oberfläche:** `wa-*`-Bausteine und die eigenen `FormInput` und `AppDialog`. Texte in `de` und `en`.
6. **Tests:** Vitest für das Formular und die Wartesseite. Sichtprüfung im Browser (hell und dunkel, de und en).

## Offen (vor dem Bau klären)
- **Bot-Schutz:** Soll die Registrierung ein Captcha-Verfahren bekommen? Das kommt erst, wenn Missbrauch auftritt. Das Rate-Limit reicht vorerst.
- **Benachrichtigung der Admins:** Eine Mail je Registrierung oder eine tägliche Zusammenfassung?
- **Aufbewahrung:** Wie lange bleiben unbestätigte Konten bestehen? Vorschlag: 7 Tage, dann löscht ein Befehl sie.

## Reihenfolge
1. Backend: Migration, Fortify, Freigabe, Mutationen, Tests.
2. Frontend: Registrierung, Verifikation, Wartesseite, Admin-Liste, Tests.
3. Browserprüfung, Doku, Stand nachführen.

## Stand
- [x] Backend: Migration `approved_at` (bestehende Konten gelten als freigegeben), Fortify-Registrierung und E-Mail-Bestätigung, `CreateNewUser`, Drosselung `register` (5 pro Minute und IP), Middleware `EnsureAccountReady` (Operation `Viewer` bleibt offen, Fehlercode `ACCOUNT_NOT_READY` mit `reason` email|approval), Mutationen `approveUser` und `rejectUser` mit Policy, Benachrichtigung der Admins nach der Bestätigung, Texte de/en, Tests (`SelfRegistrationTest`, `GraphQL/AccountApprovalTest`), Schema und Frontend-Schema aktualisiert. Suite: 497 Tests grün.
- [ ] `composer test:mariadb`: Der lokale Datenbankbenutzer hat keinen Zugriff auf `molloplaner_test` (SQLSTATE 1044). Rechte bitte lokal setzen, dann laufen die Tests gegen MariaDB.
- [x] Frontend: `/register` (Layout `auth`, Felder Name, E-Mail, Passwort, Wiederholung), `/verify-email` (Ziel des Bestätigungslinks), `/account-pending` (Wartesseite mit Grund `email` oder `approval`, „Erneut prüfen“, Abmelden), Weiterleitung der Sperre in Middleware, Login und Einrichtung, Freigabeliste mit „Freigeben“ und „Ablehnen“ in `/admin/users`, Texte de/en, Tests (245 grün).
- [x] Backend nachgetragen: `User.approved` und `User.emailVerified` (GraphQL), CORS für `/register` (vorher blockierte der Browser die Anfrage), Verifikations-Redirect ohne doppeltes `?verified=1`.
- [x] Anmeldung im Browser geprüft: unbestätigtes Konto landet auf der Wartesseite „E-Mail bestätigen“, bestätigtes Konto ohne Freigabe auf „Konto wartet“. Fehler behoben: Ein wartendes Konto kam bis aufs Dashboard, weil `Viewer` offen ist. Das Frontend prüft jetzt `approved` und `emailVerified`.
- [ ] Offen: Freigabeliste in `/admin/users` und die Freigabe selbst im Browser.
- [ ] Browserprüfung (Rest): Die Browser-Erweiterung hat die Bedienung nach zwei Fehlversuchen abgebrochen. Ablauf deshalb per curl geprüft: Registrierung (201), Sperre ohne Bestätigung (`ACCOUNT_NOT_READY`, `email`), Bestätigungslink (302 nach `/verify-email`). Freigabe und Oberfläche noch im Browser prüfen.
- [ ] Testkonto `testperson-neu@example.test` in der lokalen Datenbank (Dev) entfernen oder ablehnen.
- [ ] Doku in `backend-plan.md` und `CLAUDE.md` nach Abschluss.

## Festgelegte Standardwerte
- **Admin-Hinweis:** eine Mail je bestätigte Registrierung, an alle mit `users.manage`.
- **Captcha:** nicht eingebaut. Die Drosselung reicht vorerst.
- **Aufbewahrung unbestätigter Konten:** noch nicht umgesetzt (Vorschlag 7 Tage, offen).
- **Freigabe-Mail an die Person:** nicht vorgesehen, die Person sieht die Freigabe beim nächsten Login.
- **Hinweis:** Fortify meldet nach `/register` sofort an, die Sperre greift danach. So bleibt die Anmeldung mit 2FA-Einrichtung wie bisher.
- **Reihenfolge der Sperren (entschieden 2026-11-12):** Anmeldung, dann E-Mail-Bestätigung, dann zweiter Faktor, dann Freigabe durch einen Admin.
  - `EnsureEmailVerified` läuft vor `EnsureStrongAuthentication`. Ein unbestätigtes Konto bekommt auch `Viewer` nicht, sondern `ACCOUNT_NOT_READY` mit `reason: email`.
  - `EnsureStrongAuthentication` wie bisher.
  - `EnsureApproved` läuft danach. Nur `Viewer` bleibt offen, sonst `ACCOUNT_NOT_READY` mit `reason: approval`.
  - Gemeinsame Antwort in `App\Support\AccountReadiness`. Die Middleware `EnsureAccountReady` ist entfernt.
  - Geprüft: unbestätigt liefert `reason: email`, bestätigt und ohne Freigabe liefert `Viewer` die Daten mit `approved: false`. Tests: 501 grün.

