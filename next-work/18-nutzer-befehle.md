# 18 · Artisan-Befehle zur Nutzerverwaltung per SSH (Passwort, E-Mail, 2FA)

Status: erledigt (siehe Commit dieser Änderung auf `feature/user-commands`), wartet auf Oliver
Anlass: Nach dem Deploy von 1.0 fehlt ein Weg, Nutzer auf dem Server zu verwalten, wenn niemand sich anmelden kann (Passwort vergessen, E-Mail-Adresse falsch oder nicht mehr erreichbar, Gerät für 2FA verloren). Bisher geht es nur über `tinker`.
Reihenfolge: **vor Auftrag 16 abarbeiten** (Oliver). Eigener Branch `feature/user-commands` von `dev`, kleiner Commit, danach Oliver. Nichts auf `dev` pushen, solange der Tag `v1.0.0` fehlt (Oliver sagt Bescheid).

## Vorbild

`app/Console/Commands/CreateAdmin.php` (`app:create-admin`): Signatur-Attribute, `PromptsForMissingInput`, Laravel Prompts, Texte in `lang/*/console.php` (DE und EN), **kein Passwort aus der Befehlszeile oder dem Code**. Gleiche Regeln für Passwörter wie dort (`Password::default()`, Bestätigung), gleiche Prüfung der E-Mail wie `CreateUserInput` (`email`, `unique:users,email`).

## Befehle

1. **`app:user-password {user}`**: Passwort eines Nutzers ändern.
   - `user` ist die E-Mail-Adresse oder die ID. Ohne Argument fragt der Befehl danach (Prompt). Unbekannter Nutzer: Fehlermeldung, Exit-Code 1.
   - Passwort und Bestätigung nur interaktiv (versteckte Eingabe). Nicht interaktiv: Fehler `password_needs_interaction`, nichts geschrieben.
   - Danach **alle API-Zugänge des Nutzers widerrufen** (`McpTokens::revokeAll`), `remember_token` erneuern und seine Sitzungen löschen, wie es `App\GraphQL\Mutations\ResetUserTwoFactor` schon tut (Sitzungen nur, wenn `session.driver` = `database`). Der Befehl meldet, was beendet wurde.
   - 2FA bleibt unverändert (dafür `app:user-2fa`).
2. **`app:user-email {user} {email?}`**: E-Mail-Adresse und ihre Bestätigung verwalten.
   - `user` wie oben. Mit `email`: Adresse ändern. Neue Adresse mit denselben Regeln wie beim Anlegen, nicht schon vergeben (Groß-/Kleinschreibung beachten, **prüfen**, wie `unique` in der Datenbank vergleicht).
   - **Bestätigung (`email_verified_at`):** Optionen `--verified` (setzt auf jetzt, wenn noch leer) und `--unverified` (leert, die App verlangt dann die Bestätigung neu), auch ohne neue Adresse. Ohne Option bei geänderter Adresse: Standard ist **bestätigt** (Oliver handelt auf dem Server), mit `--unverified` abweichend. **Prüfen:** was die App mit unbestätigten Nutzern macht (Anmeldung, `PruneUnverifiedUsers`, Mail-Bestätigung) und es in der Ausgabe nennen („Achtung: unbestätigte Konten werden nach N Tagen gelöscht“, falls zutreffend). Ist unklar, was die App für Admin-Eingriffe vorsieht, melde die Frage an Stuart.
   - Ohne `email` und ohne Option: zeigt nur Adresse und Stand der Bestätigung (schreibt nichts).
   - Widerruft keine Zugänge (die Adresse ist nicht das Geheimnis). Schreibt alten und neuen Wert in eine Zeile der Ausgabe.
3. **`app:user-2fa {user} {action?}`**: alle Möglichkeiten des zweiten Faktors. Die App kennt drei Teile: Authenticator-App (`two_factor_*`), Wiederherstellungscodes und Passkeys. 2FA ist Pflicht, es gibt keine Ausnahme je Nutzer und keinen Schalter dafür; das bleibt so. `action` (ohne Angabe wählt ein Prompt):
   - `status`: Authenticator ja/nein (bestätigt wann), Anzahl Wiederherstellungscodes, Passkeys mit ID, Name und Datum, ob der Nutzer beim nächsten Login einrichten muss (`StrongAuthentication::needsSetup`). Schreibt nichts.
   - `reset`: alles zurücksetzen wie die Mutation `resetUserTwoFactor` (Authenticator, Wiederherstellungscodes, alle Passkeys, Zugänge für Claude, Sitzungen, `remember_token`). Für „Gerät verloren“.
   - `authenticator`: nur die Authenticator-App samt Wiederherstellungscodes entfernen. Passkeys bleiben.
   - `passkeys`: alle Passkeys entfernen, mit `--id=<ID>` nur einen. Der Authenticator bleibt.
   - `recovery-codes`: neue Wiederherstellungscodes erzeugen (die alten verfallen), nur wenn ein Authenticator bestätigt ist. Die Codes werden **einmal** am Terminal angezeigt und nirgends geschrieben, nicht ins Log. Die Erzeugung läuft über dieselbe Funktion wie in der App (**prüfen**, Fortify-Aktion).
   - Entfernt eine Aktion den **letzten** Faktor, sagt die Bestätigungsfrage das ausdrücklich („Nutzer muss beim nächsten Login einen neuen Faktor einrichten“), und es werden Zugänge, Sitzungen und `remember_token` ebenfalls beendet. Bleibt ein Faktor, wird nur der entfernte Teil geändert.
   - **Keine zweite Kopie der Logik:** Die Schritte in einen Dienst auslagern (z. B. `App\Services\UserSecurity` mit `resetTwoFactor`, `removeAuthenticator`, `removePasskeys`, `revokeAccess`), den die Mutation und die Befehle nutzen. Die Tests der Mutation bleiben grün und unverändert.
4. Die Befehle 1 bis 3: **Bestätigung vor dem Schreiben** („Passwort für <Name> <E-Mail> ändern?“), `--no-interaction` ist ein Fehler. Eine Zeile ins Log (Kanal wie die übrigen Admin-Eingriffe, **prüfen**), mit Nutzer-ID und Zeit, **ohne** Passwort, Hash oder Adresse im Klartext, wenn das Log-Muster der App das sonst auch vermeidet.

Spätere Erweiterungen (nicht jetzt): Nutzer sperren und entsperren, Rolle ändern. Die Namen `app:user-password`, `app:user-email`, `app:user-2fa` passen als Gruppe.

## Abnahme

- Tests: E-Mail ändern, `--verified`, `--unverified`, nur Anzeige, doppelte oder ungültige Adresse; 2FA je Aktion (`status` schreibt nichts, `reset`, `authenticator`, `passkeys` mit und ohne `--id`, `recovery-codes` nur mit Authenticator, letzter Faktor beendet Sitzungen); Passwort ändern (Hash geändert, alte Zugänge und Sitzungen weg, falsche Bestätigung), Nutzer nicht gefunden, nicht interaktiv.
- Prüfungen grün (Backend-Tests, Lint).
- Dokumentation: `docs/deploy-template.md` (Abschnitt „Betrieb“) und README mit allen Aufrufen.
- Oben `Status: erledigt (<Commit>)`, `docs/umsetzung.md` „Stand“, anhalten.
