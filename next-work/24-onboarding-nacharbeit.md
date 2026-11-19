# 24 · Onboarding: Nacharbeit aus der Durchsicht

Status: erledigt (N1 b1d4ca1, N2 b20fc7f auf `fix/onboarding-nacharbeit`); Prüfung durch unabhängigen Agenten bestanden (eingebaute Abweichung gefunden); wartet auf Oliver
Priorität: HOCH (Oliver, 2026-11-18): vor dem Tag `v1.0.0`. Klein, zwei Commits.
Branch: `fix/onboarding-nacharbeit` **von `test/auth-flow-e2e`** (oberster Branch des Stapels, enthält 18 bis 23 und 20). Nichts mergen und nichts auf `dev` oder `main` pushen, bis Oliver es sagt.
Anlass: Durchsicht der Aufträge 20 bis 23 durch Fable (2026-11-18, Code gelesen, nichts ausgeführt). Sonst keine Beanstandung.
Prozess (ui-consistency): N1 ändert eine Seite → `adjusting` → `implementing` → `verifying` durch einen Agenten, der die Änderung nicht geschrieben hat. N2 ist Backend ohne UI.

## N1 · Mail «Registrierung noch nicht bestätigt» führt auf eine Fehlermeldung

**Problem:** Der Knopf «Bestätigungsmail erneut senden» in dieser Mail zeigt auf `/verify-email?status=invalid` (`backend/app/Notifications/RegistrationForExistingAddress.php`, `$primary`). Die Seite zeigt dort die rote Meldung «Der Link ist ungültig oder abgelaufen» (`frontend/app/pages/verify-email.vue`, Zustand `invalid`). Für die Person ist aber nichts ungültig: Sie hat einen Knopf «erneut senden» gedrückt und liest einen Fehler.

**Soll**

- Eigener Zustand der Seite, Aufruf `/verify-email?status=resend`:

  | Teil | Inhalt |
  |---|---|
  | Titel | wie heute («E-Mail-Adresse bestätigen») |
  | Meldung | **keine** Fehlermeldung, kein rotes Kästchen. Ein Satz in normaler Schrift: «Gib deine Adresse ein. Wir schicken dir die Bestätigungsmail nochmals.» |
  | Formular | derselbe Baustein wie bei `invalid`: `AuthResendVerification`, angemeldet im Modus `session`, sonst `address` (Erkennung der Sitzung wie heute bei `invalid`) |
  | Weg zurück | Link «Zur Anmeldung» wie bei `invalid` |

- `status=invalid` bleibt unverändert (abgelaufener oder veränderter Link, Weiterleitung der API).
- Unbekannte Werte von `status` und fehlende Parameter verhalten sich weiter wie `invalid`.
- Die Mail zeigt neu auf `?status=resend` (beide Sprachen, derselbe Baustein). Der Text der Mail bleibt.
- Neuer Text unter `auth.verify.*` in `i18n` (de, en). Keine neue Komponente.
- Die Antwort auf «erneut senden» bleibt neutral wie heute; der neue Zustand verrät nichts über Konten (die Seite ist für jede Adresse gleich).

**Tests**

- Backend: `RegistrationNeutralTest::test_the_two_mails_name_the_right_links_in_both_languages` erwartet für das unbestätigte Konto `…/verify-email?status=resend`.
- Frontend (vitest, `verify-email.test.ts`): `status=resend` zeigt das Formular **ohne** die Meldung «ungültig oder abgelaufen», mit und ohne Sitzung; `status=invalid` wie bisher.
- Browser-Test (Auftrag 20, Szenario 1, Fall «unbestätigtes Konto», falls vorhanden; sonst ergänzen): Link aus der Mail öffnen, kein Fehlertext sichtbar, «erneut senden» schickt die Mail.

## N2 · Toter Code im `FortifyServiceProvider`

- Am Ende von `boot()` steht noch `Route::getRoutes()->getByName('register.store')?->middleware('throttle:register')` mit dem Kommentar «Fortify registers /register without a limiter». Die Fortify-Route gibt es seit Auftrag 22 O3 nicht mehr; die eigene Route in `routes/web.php` trägt `throttle:register` selbst.
- Zeile und Kommentar entfernen. Vorher prüfen, dass `test_registration_stays_throttled_per_ip` ohne sie grün bleibt (er muss; sonst **melden**, nicht die Zeile behalten).
- `Fortify::createUsersUsing(CreateNewUser::class)`: Bob prüft, ob Fortify den Vertrag bei ausgeschaltetem Feature `registration` noch irgendwo auflöst. Wird er nicht mehr gebraucht, entfernen; sonst lassen und im Commit sagen, wofür.

## Zusätzlich in der Abschlussmeldung (nichts zu bauen)

- **MariaDB:** Ist `composer test:mariadb` nach der Migration `users.locale` (Auftrag 22 O8) gelaufen? Ergebnis mit Testzahl nennen; falls nicht gelaufen, jetzt laufen lassen.
- **Signatur und `APP_URL`:** Der Bestätigungslink gilt nur, wenn `APP_URL` genau der Adresse entspricht, unter der die API den Aufruf sieht (Schema und Host). Die Nachkontrolle dazu steht in `docs/deploy-template.md`; sie macht Oliver auf dem Testserver. Bob baut hier nichts. Scheitert die Kontrolle, kommt ein eigener Auftrag (Signatur ohne Host).

## Abnahme

- `php artisan test`, `./vendor/bin/pint`, im Frontend `npm run lint`, `npm run typecheck`, `npm test`, `npm run test:e2e` grün.
- Zwei Commits: `Fix: … (task 24 N1)`, `Chore: … (task 24 N2)`.
- `docs/umsetzung.md` (Stand) nachführen. Oben `Status: erledigt (<Commit>)`, dann anhalten.
