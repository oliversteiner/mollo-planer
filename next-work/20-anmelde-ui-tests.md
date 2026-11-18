# 20 · Anmeldeablauf lokal mit UI-Tests durchspielen

Status: offen
Reihenfolge: nach den Aufträgen 19, 21, 22 und 23 (sonst testen die Szenarien den alten Stand), vor Auftrag 16. Branch `test/auth-flow-e2e` **von `fix/onboarding-frontend`** (Auftrag 23; die Aufträge sind gestapelt). Nichts mergen und nichts auf `dev` oder `main` pushen, bis Oliver es sagt; der Tag `v1.0.0` wird nach der Umsetzung des Onboardings gesetzt (Oliver).
Anlass (Oliver, 2026-11-18): Auf dem Testserver ist der Anmeldeablauf nicht verlässlich (Bestätigungslink nur mit Sitzung, Seite blieb nach dem Passkey stehen). Die Szenarien 10 bis 14 und die Anpassung von Szenario 1 stammen aus dem Onboarding-Gutachten (Fable).

## Ziel

Der komplette Weg vom Konto bis zur Arbeitsfläche läuft lokal als automatisierter Browser-Test und deckt auf, wo der Ablauf hängt oder Meldungen fehlen. Gefundene Fehler werden **nicht** gleich mitrepariert, sondern als Liste an die Planung gemeldet (ausser Auftrag 19).

## Werkzeug (Entscheidung nötig, Vorschlag)

- Playwright (Chromium) als Dev-Abhängigkeit im Frontend, eigene Konfiguration `playwright.config.ts`, Skript `npm run test:e2e`. Vitest bleibt unberührt.
- Passkeys: Chromium-«Virtual Authenticator» über CDP (WebAuthn), kein echtes Gerät.
- Authenticator-App: Code im Test aus dem angezeigten Schlüssel berechnen (TOTP-Bibliothek).
- Mails: lokal `MAIL_MAILER=log` oder Mailpit; der Test liest den Link aus Log bzw. Mailpit-API. Nie echte SMTP-Zugangsdaten.
- Backend läuft lokal gegen eine **eigene Test-Datenbank**, die vor jedem Lauf per `migrate:fresh` geleert wird. Nie gegen Testserver oder Produktion.
- **Queue:** Alle Konto-Mails laufen über die Queue. In der Umgebung der Browser-Tests gilt `QUEUE_CONNECTION=sync`, damit die Mail da ist, wenn der Test sie liest. (Dass die Mails wirklich eingereiht werden, prüfen die Backend-Tests.)
- **Passwort-Abgleich:** In der Umgebung der Browser-Tests ist der Abgleich mit bekannten Passwörtern abgeschaltet (Schalter aus Auftrag 22 O4). Kein Test ruft den externen Dienst auf. Dass ein bekanntes Passwort abgelehnt wird, prüft der Backend-Test.
- Falls Playwright nicht installierbar ist: melden, nicht auf ein anderes Werkzeug ausweichen.

## Szenarien (jeweils DE; ein Durchlauf EN genügt)

1. Registrierung: Pflichtfelder, zu kurzes Passwort (unter 12 Zeichen). **E-Mail schon vergeben (Entscheid Oliver: neutral): gleiche Seite und gleicher Text wie bei einer neuen Adresse, kein Feldfehler, keine Sitzung, die Mail «Du hast schon ein Konto» geht raus.** Auch in Gross-/Kleinschreibung und mit Leerzeichen am Rand.
2. Nach Registrierung: Seite «E-Mail bestätigen» (`account-pending`). Bestätigungslink aus der Mail öffnen: Die Seite zeigt den Knopf «E-Mail-Adresse bestätigen», die Adresse ist noch nicht bestätigt (Auftrag 21). Nach dem Klick: bestätigt; weiter zu «Konto absichern», nach dem zweiten Faktor «Freigabe ausstehend».
3. Admin gibt frei (`ApproveUser`): Die Person bekommt die Mail; «Erneut prüfen» bzw. die nächste Anmeldung führt auf die Arbeitsfläche (der zweite Faktor ist nach der Reihenfolge E-Mail → zweiter Faktor → Freigabe schon eingerichtet).
4. «Konto absichern» mit **Passkey** (virtueller Authenticator) → Abschlussschritt mit Hinweis auf einen zweiten Faktor → «Weiter» führt weiter (Auftrag 19 und Auftrag 23 F5): bei selbst registrierten Konten zu «Freigabe ausstehend», bei freigegebenen auf die Arbeitsfläche. Die Seite bleibt nie ohne Meldung stehen.
5. Dasselbe mit **Authenticator-App** und Wiederherstellungscodes.
6. Abmelden, Anmelden mit Passwort + zweitem Faktor (Passkey, App-Code, Wiederherstellungscode).
7. Passwort vergessen: Mail, Link, neues Passwort, Link nach Gebrauch und nach Ablauf ungültig; Meldung ist verständlich.
8. Zurückgesetzte 2FA (`app:user-2fa reset`): Anmeldung führt wieder durch «Konto absichern», alter Passkey bleibt ohne Wirkung.
9. Abgelehnter Nutzer (`RejectUser`) und nicht bestätigtes Konto: richtige Zielseite, kein Absturz.

10. **Konto nur mit Passkey, Anmeldung mit Passwort:** nach korrektem Passwort kommt der Hinweis «Melde dich mit dem Passkey an», der Passkey-Knopf ist hervorgehoben (Gutachten K8).
11. **Link im zweiten Browserprofil** (ohne Sitzung): zeigt den Knopf, bestätigt erst nach dem Klick, meldet nicht an, zeigt «Melde dich jetzt an und richte deinen zweiten Faktor ein». Abgelaufener oder veränderter Link: Fehlerseite mit «erneut senden».
12. **Freigabe und Ablehnung:** Mail geht raus (Mail-Log), in der Sprache der Person, bei Ablehnung trotz gelöschtem Konto.
13. **Passkey-Einrichtung:** Erklärung vor dem Knopf, nach Abbruch die neue Meldung, nach Erfolg der Abschlussschritt mit dem Hinweis auf einen zweiten Faktor (Auftrag 23 F5), «Weiter» führt in die App bzw. zu «Freigabe ausstehend».
14. **Passwort vergessen:** bekannte und unbekannte Adresse zeigen denselben Text.
15. **Admin setzt den zweiten Faktor zurück:** Der Dialog stellt die Rückfrage zur Identität (Auftrag 23 F8); die betroffene Person bekommt die Mail (Auftrag 22 O7).
16. **Keine doppelte Passwortabfrage:** Nach der Anmeldung mit Passwort fragt «Konto absichern» das Passwort nicht nochmals ab (Auftrag 22 O6).

Je Szenario: erwartete Zielseite, sichtbare Meldung (Toast oder Feldfehler), keine Fehler in der Browser-Konsole, keine unerwarteten 4xx/5xx im Netzwerk.

## Abnahme

- `npm run test:e2e` läuft lokal grün durch; Anleitung (Start von Backend, Mail, Test-DB) steht in `docs/` (kurzer Abschnitt, kein neues Dokument, falls es passt).
- Bericht an die Planung: Liste der Abweichungen mit Szenario-Nummer, Ist/Soll.
- Ein Commit für die Testinfrastruktur, die Szenarien in einem weiteren. Die Prüfung durch einen Agenten, der die Tests nicht geschrieben hat (ui-consistency:verifying), entfällt nicht.
