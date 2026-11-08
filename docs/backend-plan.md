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
10. **Owner-Rolle:** Die Rolle OWNER vergeben, einen Owner herabstufen oder entfernen dürfen nur Owner und Admins (zusätzlich zu „Mitglieder verwalten“). Sonst könnte sich ein Manager selbst zum Owner machen. Geregelt in `ProjectPermissions::canChangeMembership`.
11. **Letzter Owner:** Der letzte Owner eines Projekts kann nicht entfernt oder herabgestuft werden, ausser durch einen Admin. Projekte, die ein Admin ohne Owner anlegt, bleiben erlaubt. Wer ein Projekt anlegt (Admin), wird nicht automatisch Mitglied.
12. **Mitglied entfernen:** Die Tasks der Person in diesem Projekt werden unzugewiesen.
13. **Korrekturen am Schema** (Schritt 8, nötig, damit Lighthouse es ausführt): `@whereKey` an `id` bei allen `delete…`-Mutationen; `@can(find: …)` bei den Create-Mutationen nutzt die Namen nach `@spread`/`@rename` (`project_id`, `milestone_id`, `todo_id`, `task_id`); `integer` vor `min`/`max` bei `Dashboard.upcomingTodos(limit)` und `moveTask(position)`, sonst prüft Laravel die Textlänge; `updateUser` hat `injectArgs: true` und `@validator` (E-Mail eindeutig).
14. **Einladung:** `createUser` schickt eine Mail mit Link zum Frontend (`/invitation?token=…&email=…`), gültig 7 Tage (eigener Broker `invitations`), einmal verwendbar. Das Frontend schickt Token und Passwort an `POST /invitation`. Sprache der Einladung = Sprache der Anfrage des Admins.
15. **Sprache der API:** Header `Accept-Language` (de oder en), sonst `APP_LOCALE` (in `.env.example` jetzt `de`). Mails zu einem Projekt nutzen weiterhin die Projektsprache.
16. **Weitere Absicherung:** Keine Selbstregistrierung, keine REST-Route `/api/user` (sie hätte die Sperre umgangen), CORS nur für `FRONTEND_URL` mit Cookies, GraphQL höchstens 120 Anfragen pro Minute und Person.

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
- Umsetzung (Schritt 9, geprüft am installierten Fortify 1.41 und `laravel/passkeys` 0.2):
  - Die Passkey-Routen registriert Fortify (`/passkeys/login`, `/passkeys/confirm`, `/user/passkeys`). Registrierungs- und Login-Optionen verlangen `userVerification: required`, die Prüfung der Antwort läuft gegen diese Optionen.
  - Das Merkmal „starke Anmeldung“ (`App\Support\StrongAuthentication`) speichert die User-ID in der Sitzung und gilt nur für diese Person. Gesetzt wird es bei einem gültigen 2FA-Code oder Wiederherstellungscode, bei einem geprüften Passkey (Login oder `/passkeys/confirm`) und bei der Einrichtung des **ersten** Faktors. Logout löscht die Sitzung, das Entfernen des letzten Faktors entfernt das Merkmal.
  - Wer schon einen Faktor hat, kann ohne starke Sitzung keine Faktoren hinzufügen oder entfernen und weder Passwort noch Profil ändern (`ProtectAccountSecurityRoutes`). Sonst könnte jemand mit dem Passwort eines Passkey-Kontos eine eigene 2FA-App hinzufügen.
  - Erste Einrichtung: Nach der Einladung genügt das Passwort, um den ersten Faktor einzurichten. Die Einladung per E-Mail ist dabei der Nachweis.
  - Antwort bei gesperrter Sitzung: `SECOND_FACTOR_SETUP_REQUIRED` (zur Einrichtung) oder `STRONG_AUTHENTICATION_REQUIRED` (mit Passkey bestätigen über `/passkeys/confirm` oder neu anmelden).
  - `resetUserTwoFactor` beendet auch die Sitzungen und „angemeldet bleiben“ (neues `remember_token`), etwa auf dem verlorenen Gerät.

## Mail-Benachrichtigungen (Umsetzung Schritt 10)
- `App\Services\TaskNotifier` wählt die Empfänger: bei neuem Task mit Zuweisung und bei `assignTask` die zugewiesene Person, bei `addComment` die zugewiesene Person und alle, die den Task schon kommentiert haben. Nie die handelnde Person, nie wer Mails abgeschaltet hat, nie wer das Projekt nicht sehen darf (`ProjectPermissions::canView`, also auch Admins ohne Mitgliedschaft, wenn sie kommentiert haben). Aufheben der Zuweisung schickt keine Mail.
- `TaskAssigned` und `TaskCommented` erben von `TaskNotification`: `ShouldQueue`, `afterCommit`, Sprache = `project->locale`. `shouldSend()` prüft beim Versand aus der Queue nochmals Mail-Einstellung und Sichtbarkeit. Ist der Task inzwischen gelöscht, wird der Job verworfen (`deleteWhenMissingModels`).
- Der Name der handelnden Person wird als Text mitgegeben, nicht als Model.
- Link in der Mail: `FRONTEND_URL/projects/{id}/kanban?task={id}`. Die Route ist nur in `TaskNotification::taskUrl()` definiert und beim Bau des Frontends dort anzupassen.
- `Markdown::withSecuredEncoding()` (AppServiceProvider): Titel wie `[Klick](https://…)` erscheinen in Mails als Text, nicht als Link.

## Erster Admin (Umsetzung Schritt 11)
- `php artisan app:create-admin "Name" mail@example.com` legt einen Admin an und schickt die Einladung (gleicher Weg wie `createUser`, gemeinsam in `App\Services\UserInvitation`). Fehlen Name oder E-Mail, fragt der Befehl danach.
- `--password` fragt das Passwort verdeckt und zweimal ab (Regeln wie bei Fortify), es geht keine Mail raus. Ohne Terminal (`--no-interaction`) bricht der Befehl ab. Ein Passwort als Argument gibt es absichtlich nicht (Shell-History).
- `--locale=de|en` bestimmt die Sprache der Einladung, sonst `APP_LOCALE`. Die Einladung läuft über die Queue, auf dem Server also erst, wenn der Cron-Worker läuft.
- Name und E-Mail werden wie in `CreateUserInput` geprüft. Eine vergebene E-Mail bricht ab, ein bestehender Benutzer wird nicht zum Admin befördert (das geht über `updateUser`).
- Auch der erste Admin richtet beim ersten Login einen zweiten Faktor oder Passkey ein. Texte des Befehls stehen in `lang/{de,en}/console.php`.
- Abgelaufene Einladung: Es gibt bewusst keine Mutation zum erneuten Senden. Die Person setzt ihr Passwort über „Passwort vergessen“ (bestätigt 2026-11-08).

## Avatarbild (bestätigt 2026-11-08)
- Jede Person lädt ihr eigenes Bild hoch: `updateMyAvatar(avatar: Upload!)` (GraphQL multipart request, im Frontend zum Beispiel `apollo-upload-client`). Entfernen mit `deleteUserAvatar(id)`: die Person selbst oder ein Admin (`UserPolicy::deleteAvatar`). Admins laden kein Bild für andere hoch. Anzeige über `User.avatarUrl`, null ohne Bild.
- Erlaubt: JPG, JPEG, PNG (Inhalt und Dateiendung geprüft), höchstens 5 MB, höchstens 6000 x 6000 Pixel (Schutz vor Bildern, die beim Entpacken den Speicher sprengen). Regeln in `UpdateMyAvatarValidator`, Grenzen als Konstanten in `App\Services\UserAvatar`.
- Verarbeitung mit der Bild-API von Laravel (`intervention/image` ^4, Treiber GD): nach EXIF aufrichten, mittig quadratisch auf 500 x 500 Pixel zuschneiden, als WebP (Qualität 85) speichern. Das neue Kodieren entfernt alle Metadaten (etwa die GPS-Position eines Handyfotos).
- Ablage auf der Disk `public` unter `avatars/<40 zufällige Zeichen>.webp`, nicht erratbar. Die alte Datei wird erst nach dem Commit gelöscht, die neue bei einem Rollback wieder entfernt. Wird eine Person gelöscht, löscht `UserObserver` ihr Bild.
- Für später (Hosting): `php artisan storage:link` auf dem Server, GD muss aktiv sein, `upload_max_filesize` und `post_max_size` mindestens 6M (PHP-Standard ist 2M bzw. 8M).

## Abfragen ohne N+1 (Prüfung mit Laravel Boost, 2026-11-08)
- `Model::preventLazyLoading()` ist ausserhalb der Produktion aktiv: Wird eine Relation pro Zeile nachgeladen, ist das ein Fehler statt einer stillen Zusatz-Query. `Model::shouldBeStrict()` passt nicht, weil Felder wie `status` absichtlich nicht fillable sind und still verworfen werden.
- Felder, die pro Objekt die Rechte prüfen, laden die nötigen Relationen gebündelt mit `@with`: `Project.members`, `viewerRole`, `viewerAbilities` (`members`) und `Comment.viewerCanEdit`/`viewerCanDelete` (`task.todo.milestone.project.members`). Nur Server-Direktiven, der Vertrag mit dem Frontend bleibt gleich.
- `progress` (Projekt, Milestone, ToDo) zählt alle Objekte einer Liste in einer gruppierten Query (`ProgressBatchLoader`).
- `QueryTest::test_list_queries_do_not_grow_with_the_number_of_rows` stellt sicher, dass die Anzahl Queries nicht mit der Anzahl Projekte wächst.
- Indizes (geprüft per `database-schema`): Alle Fremdschlüssel sind indiziert, dazu `tasks(todo_id, status)` und eindeutig `project_user(project_id, user_id)`. Kein weiterer Index nötig.

## Transaktionen und Sperren (2026-11-08)
- `transactional_mutations` von Lighthouse gilt nur für `@create`, `@update` und `@delete`. Jede Mutation mit eigenem Resolver (`App\GraphQL\Mutations`) packt ihre Arbeit deshalb selbst in `DB::transaction()`. Neue Resolver ebenso. `MutationTest::test_custom_mutations_write_inside_a_transaction` prüft das für jede dieser Mutationen; eine neue gehört in die Liste `customMutations()`.
- `KanbanOrdering` (Verschieben, neue Position) und `ProjectMembership` (Mitglied setzen, entfernen) sperren zuerst die Projektzeile (`lockForUpdate`). So laufen gleichzeitige Änderungen am selben Projekt nacheinander: keine doppelten Positionen und kein Projekt ohne Owner, wenn sich zwei Owner gleichzeitig herabstufen. SQLite (Tests) kennt diese Sperre nicht, sie wirkt nur auf MariaDB.

## Tests (Umsetzung Schritt 12)
- Die Suite läuft auf SQLite im Speicher (`php artisan test`, schnell) und mit `composer test:mariadb` gegen die lokale MariaDB-Datenbank `projektverwaltung_test`. Nur dort wirken die Sperren (`lockForUpdate`) und die JSON-Prüfungen der Datenbank wie produktiv.
- `OutsiderAccessTest` schickt jede Mutation als Person ausserhalb des Projekts (bzw. Admin-Mutationen als Owner ohne Admin-Rolle) und prüft: abgelehnt, keine Tabelle verändert. Jede Abfrage zeigt einer fremden Person nichts. Ein vergessenes `@can` oder ein fehlender Scope im Schema fällt so auf. Neue Mutationen und Abfragen gehören in diese Listen.
- Die Rechte-Matrix selbst prüfen `PolicyTest` und `ProjectPermissionsTableTest`.
- HTML in Titeln und Namen wird in allen Mails maskiert (Task-, Einladungs- und Reset-Mail). Nach abgelaufener Einladung setzt „Passwort vergessen“ das Passwort. Introspection ist nur in Produktion aus.

## Demo-Daten (2026-11-08)
- `Database\Seeders\DemoSeeder` enthält die Daten der GraphQL-Demo: Anna Keller (Admin), Ben Müller, Chiara Rossi, David Frei (`…@example.ch`), die Projekte „Website-Relaunch“ und „Sommerfest 2027“ mit Milestones, ToDos, Tasks (mit Icons, Beschreibungen, Kanban-Positionen) und Kommentaren. Status von ToDo und Milestone berechnen die Observer.
- Läuft nur ausserhalb der Produktion und nur in eine leere Datenbank. Kein Passwort im Code: Der Seeder fragt ein gemeinsames Passwort ab (ohne Terminal wird eines erzeugt) und richtet für alle eine bestätigte 2FA-App ein. Passwort, 2FA-Secrets (mit `otpauth://`-Adresse für die Authenticator-App) und Wiederherstellungscodes werden einmal ausgegeben.
- `DatabaseSeeder` legt bewusst nichts an, damit `migrate --seed` nie einen Benutzer mit bekanntem Passwort erzeugt. Er verweist auf `app:create-admin` und `DemoSeeder`.

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
