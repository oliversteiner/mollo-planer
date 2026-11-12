# Selbstregistrierung mit Passwort: Plan

Stand: 2026-11-12. Status: **geplant, nicht begonnen.** Entscheidung: Registrierung mit Passwort, E-Mail-Bestätigung und Freigabe durch einen Admin. Dieser Plan ersetzt den Beschluss „Keine Selbstregistrierung“ in `backend-plan.md` (Punkt 16) und `claude-desktop-kontext.md`.

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
