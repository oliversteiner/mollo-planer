# Nachkontrolle nach dem Deploy: TEST und PROD

Für Stuart und Oliver. Grundlage: `docs/onboarding-leitfaden.md` §6.4 (acht Punkte nach jedem Deploy) und `docs/deploy-template.md` Abschnitt 15. Jede Prüfung gilt für **beide** Umgebungen, je einmal durchgehen. Mit **zwei Browsern** (zum Beispiel Chrome und ein privates Fenster oder Safari), wo steht «anderer Browser».

| | TEST | PROD |
|---|---|---|
| Adresse | https://planer-test.example.com | https://planer.example.com |
| API | https://api.planer-test.example.com | https://api.planer.example.com |
| Band «Testumgebung» oben links | **ja** | **nein** |
| Passkey-RP-ID | `planer-test.example.com` | `planer.example.com` |
| Daten | eigene, getrennte Datenbank | eigene, getrennte Datenbank |

## A. Unterscheidung der Umgebungen

1. Im Browser öffnen: TEST zeigt das Band «Testumgebung» (Kopfleiste, links), PROD nicht.
2. Die Mails von TEST tragen «(Test)» im Absendernamen und im Betreff-Namen, die von PROD nicht.
3. Ein Konto auf TEST ist **nicht** auf PROD vorhanden und umgekehrt (getrennte Datenbanken).

## B. Anmeldung und Konto (je Umgebung)

1. **Registrieren** mit einer neuen Adresse. Die Bestätigungsmail kommt innert einer Minute (Queue und Cron laufen).
2. Den Link **in einem anderen Browser** öffnen: Der Knopf erscheint, die Adresse ist noch nicht bestätigt. Klick bestätigt, ohne dass jemand angemeldet wird. Danach der Hinweis mit dem Knopf «Anmelden», **kein** roter Toast.
3. **Registrierung mit vergebener Adresse:** gleiche Antwortseite, die Hinweis-Mail «Du hast schon ein Konto» kommt.
4. **Freigabe** durch den Admin: Das neue Konto wartet, nach «Freigeben» kommt die Freigabe-Mail an.
5. **Passwort vergessen:** Mail kommt, ein neues Passwort ist möglich (die Prüfung gegen Datenlecks ist ein ausgehender Aufruf des Servers).
6. **Passkey** einrichten und damit anmelden. Die RP-ID passt zur Umgebung (kein Passkey der anderen Umgebung erscheint in der Auswahl).
7. **2FA** ist Pflicht und nicht abschaltbar (Profil/Administration: der Schalter fehlt oder ist gesperrt).
8. Anmeldung, Logo oben links führt zur Startseite, Abmelden.

## C. Technik (Oliver)

1. **Rauchtest** der API ohne Sitzung (lokal im Terminal):
   ```bash
   # LOKAL (auf deinem Mac, im Terminal)
   curl -s -X POST -H 'Content-Type: application/json' -H 'Accept: application/json' -d '{"query":"{ viewerRights }"}' https://api.planer-test.example.com/api | head -c 200
   curl -s -X POST -H 'Content-Type: application/json' -H 'Accept: application/json' -d '{"query":"{ viewerRights }"}' https://api.planer.example.com/api | head -c 200
   ```
   Beide antworten `Unauthenticated.`, nicht mit einem Serverfehler.
2. **Deploy-Log** (GitHub → Actions → `deploy` → Lauf → Job «Deploy (test)» bzw. «Deploy (production)» → Schritt «Upload frontend and run deployment»): «Umgebung: APP_ENV=staging, Branch dev» bzw. «production, main», `Backup: …`, «Rauchtest GraphQL: ok», **keine** Zeile `WARNUNG`.
3. **Mails und Webhooks:** Eine Aufgabe zuweisen, die Mail kommt innert zwei Minuten (Cron läuft).
4. **Browser-Konsole** (Entwicklerwerkzeuge): kein Hinweis «Download the Apollo DevTools …» (etwa 10 Sekunden warten).
5. **Versionsseite:** zeigt 1.1 als «veröffentlicht».

## D. Was nur auf TEST geprüft wird

1. Webhook auf eine `localhost`- oder interne Adresse wird abgelehnt (wie in PROD).
2. Keine Demo-Daten: auf dem Server lehnt `DemoSeeder` die Ausführung ab (`production or staging`).
3. Die API verweigert die GraphQL-Introspektion (Fehler «introspection is not allowed»), wie PROD.

## Protokoll

Je Punkt «ok» oder die Abweichung notieren, mit Datum und Umgebung. Abweichungen gehen als Befund an Bob.
