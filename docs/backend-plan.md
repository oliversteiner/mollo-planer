# Backend-Plan und bestätigte Entscheidungen

Stand: 2026-11-07. Ergänzt `graphql-kontext.md`. Wo beide sich widersprechen, gilt dieses Dokument.

## Bestätigt vom Auftraggeber
1. **Rechte-Tabelle** wie im Kontextdokument. Die dortige Annahme („beim Einbau bestätigen“) ist damit bestätigt: Task anlegen/ändern/zuweisen/löschen, Milestone und ToDo löschen, Projekt ändern/löschen, Mitglieder verwalten und fremde Kommentare löschen sind auf Owner und Manager beschränkt (Projekt löschen nur Owner).
2. **Erster Admin** über einen Artisan-Befehl (zum Beispiel `php artisan app:create-admin`), nicht über einen Seeder. Kein Passwort im Code oder Repository. Der Befehl fragt Name und E-Mail ab und schickt den Link zum Setzen des Passworts, oder fragt das Passwort interaktiv ab.
3. **Passkeys werden eingebaut**, zusätzlich zur Pflicht-2FA. **Ein Login mit Passkey braucht keine zusätzliche 2FA.**
4. **Fremde Kommentare ändert niemand**, auch kein Admin. Löschen dürfen Autor, Owner/Manager und Admins.
5. **Font Awesome:** Die Pro-Lizenz enthält „Duotone Light“. Der feste Icon-Stil bleibt „Duotone Light“. (Die Hinweise „zu prüfen“ im Kontextdokument sind damit erledigt.)
6. **Benutzerliste** (`users`): Admins und alle, die in mindestens einem Projekt „Mitglieder verwalten“ dürfen (heute Owner und Manager). Geprüft über `ProjectPermissions`. Benutzer anlegen, ändern und löschen bleibt nur für Admins.
7. **Selbstschutz der Admins:** Ein Admin kann sich nicht selbst löschen und sich die Admin-Rolle nicht selbst entziehen. Für Letzteres bekommt `updateUser` in Schritt 8 `@can(..., injectArgs: true)`, damit die Policy die Eingabe sieht.
8. **Kommentare nach Entfernen aus dem Projekt:** „Eigene Kommentare darf der Autor immer ändern und löschen“ gilt nur, solange er das Projekt sehen darf. Wer entfernt wurde, kann seine Kommentare nicht mehr ändern oder löschen. Owner, Manager und Admins können sie weiterhin löschen.
9. **Enum-Anbindung:** Die PHP-Enums (`App\Enums`) heissen in ihren Cases genau wie die GraphQL-Werte (`TaskStatus::IN_PROGRESS = 'in_progress'`), weil graphql-php ein PHP-Enum unter seinem Case-Namen ausgibt. Die Models casten Status, Rolle und Sprache auf diese Enums. Das Schema bleibt unverändert. `tests/Unit/GraphqlEnumBindingTest.php` prüft Namen und Werte gegen das Schema.

Nicht Teil dieses Plans: alles zum Produktiv-Hosting bei Hostpoint (Domains, PHP-Version per SSH, SMTP-Zugangsdaten, Cron, Deployment). Das kommt später.

## Anmeldung mit Passkeys und 2FA
- Login, Passkeys und 2FA laufen über Fortify (REST), nicht über GraphQL.
- Es gibt zwei gleichwertige Wege:
  - **Passkey:** ein Schritt, mit Benutzerprüfung (Biometrie oder PIN). Danach ist die Sitzung vollständig angemeldet, kein Code nötig.
  - **Passwort plus 2FA-App (TOTP):** zwei Schritte wie bisher.
- Ein Konto braucht mindestens **einen zweiten Faktor oder einen Passkey**. Bis einer eingerichtet ist, sperrt die Middleware die GraphQL-Route und leitet im Frontend zur Einrichtung.
- Passkeys müssen Benutzerprüfung verlangen (`userVerification: required`), sonst wäre ein Passkey allein nur ein Faktor. Das beim Einbau in der Passkey-Konfiguration sicherstellen.
- Die Sperr-Middleware prüft nicht „TOTP bestätigt“, sondern „Sitzung ist stark authentifiziert“: entweder Passwort und bestätigte 2FA, oder Passkey-Login. Dafür beim Login das Merkmal in der Sitzung setzen.
- Wer Passkeys nutzt, soll mindestens zwei haben oder zusätzlich die 2FA-App einrichten (Hinweis bei Geräteverlust). Wiederherstellungscodes der 2FA bleiben bestehen.
- `resetUserTwoFactor` (Admin) entfernt **TOTP und alle Passkeys** der Person, zum Beispiel bei verlorenem Gerät. Beim nächsten Login wird ein neuer zweiter Faktor oder Passkey eingerichtet.
- Noch zu prüfen im vorhandenen Code: Wie die von `fortify:install` angelegte Tabelle `passkeys` und die zugehörigen Routen im installierten Fortify genau funktionieren und wie sich das Merkmal „starke Anmeldung“ beim Passkey-Login setzen lässt.

## Reihenfolge beim Bauen
1. Migrationen: `tasks.due_date`, `tasks.description` (json), `milestones.description` und `todos.description` auf json, `icon` bei `milestones`/`todos`/`tasks`, Tabelle `comments`, `users.notify_by_email`, `projects.locale`.
2. Enum `ProjectAbility`, zentrale Rechte-Tabelle `ProjectPermissions`.
3. Policies für Projekt, Milestone, ToDo, Task, Kommentar, Benutzer.
4. Scopes `visibleToViewer`, `assignedToViewer`, Builder für die ToDo-Liste.
5. Observer für den Status von ToDo und Milestone.
6. Schema `backend/graphql/schema.graphql` einbinden, Lighthouse konfigurieren (Sanctum, Introspection in Produktion aus, Limits für Tiefe und Komplexität, 2FA-Sperre). Dabei die Enum-Anbindung klären.
7. Skalar `RichText`, Regeln `RichTextRule` und `FontAwesomeIconRule`.
8. Resolver und Mutationen laut Schema.
9. Authentifizierung: Fortify ohne Registrierung, Passkeys, Sperre für „starke Anmeldung“, Einladung per Passwort-Link, CORS, Session-Domain, Rate-Limits.
10. Notifications `TaskAssigned` und `TaskCommented` (Queue, Sprache des Projekts).
11. Artisan-Befehl für den ersten Admin.
12. Tests: Rechte, Auth-Sperre (beide Anmeldewege), Status-Automatik, Rich-Text-Prüfung, Mail-Empfänger.

## Offene Fragen
Keine mehr, die vor dem Start des Backends beantwortet werden müssen.
