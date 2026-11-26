# Betrieb: auf dem Server

Was man auf dem laufenden Server tut, per SSH im Ordner `backend/`. Erste Einrichtung: `installation.md`. Updates: `deployment.md`. Agent-Konten, OAuth-Server und Datenbank-Sicherung: `deploy-template.md`, Abschnitt 13.

## Nutzer auf dem Server verwalten

Wenn sich niemand anmelden kann (Passwort vergessen, E-Mail-Adresse falsch oder nicht mehr erreichbar, Gerät für den zweiten Faktor verloren) oder ein Konto auf die Freigabe wartet, helfen diese Befehle. Sie laufen im Ordner `backend/` auf dem Server, per SSH. `<Nutzer>` ist die E-Mail-Adresse (Gross-/Kleinschreibung egal) oder die ID.

```bash
# AUF DEM SERVER, im Ordner backend/
php artisan app:user-password <Nutzer>          # neues Passwort (fragt es zweimal, versteckt)
php artisan app:user-email <Nutzer>             # zeigt Adresse und Stand der Bestätigung
php artisan app:user-email <Nutzer> <neue-E-Mail> [--unverified]
php artisan app:user-email <Nutzer> --verified  # oder --unverified, ohne neue Adresse
php artisan app:user-role <Nutzer> [<Rolle>]     # Systemrolle setzen (ohne Rolle: Auswahl; der letzte Admin bleibt Admin)
php artisan app:user-approve <Nutzer>            # wartendes Konto freigeben (mit Mail an die Person)
php artisan app:user-2fa <Nutzer>               # wählt die Aktion per Auswahl
php artisan app:user-2fa <Nutzer> status        # zeigt alles, schreibt nichts
php artisan app:user-2fa <Nutzer> reset         # Gerät verloren: alles zurücksetzen
php artisan app:user-2fa <Nutzer> authenticator # nur die Authenticator-App samt Wiederherstellungscodes
php artisan app:user-2fa <Nutzer> passkeys [--id=<ID>]  # alle Passkeys oder einen
php artisan app:user-2fa <Nutzer> recovery-codes        # neue Wiederherstellungscodes (einmal angezeigt)
```

Was die Befehle tun und lassen:

- **Passwort** nie als Parameter, nur im Terminal. Danach sind alle Zugänge des Nutzers beendet: Zugänge für Claude, „angemeldet bleiben“ und, wenn die Sitzungen in der Datenbank liegen (Produktion), die Sitzungen. Der zweite Faktor bleibt.
- **E-Mail:** Eine geänderte Adresse gilt als bestätigt (auf dem Server handelt ein Admin); `--unverified` verlangt die Bestätigung neu. Solange die Adresse nicht bestätigt ist, bleibt die API für das Konto gesperrt, und nicht freigegebene, unbestätigte Konten löscht `app:prune-unverified-users` täglich nach 7 Tagen. Der Befehl verschickt keine Mail und beendet keine Zugänge.
- **Zweiter Faktor:** Zweifaktor-Anmeldung ist Pflicht, es gibt keine Ausnahme je Nutzer. Wird der letzte Faktor entfernt, muss die Person beim nächsten Login einen neuen einrichten, und ihre Zugänge und Sitzungen enden. Bleibt ein Faktor, ändert sich nur der entfernte Teil.
- Jeder Befehl, der schreibt, fragt vorher nach. Mit `--no-interaction` wird nichts geschrieben (nur `status` und die Anzeige von `app:user-email` gehen ohne Terminal).
- Je Eingriff steht eine Zeile im Log (`storage/logs`), mit Nutzer-ID, Befehl und Aktion, ohne Passwort, Hash oder Adresse.

