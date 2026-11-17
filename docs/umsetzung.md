# Umsetzung Planer v2 in mollo-planer: Arbeitsschritte und Meilensteine

Stand: 2026-11-13 (U0, T1, T4, S3b, S3c, S3d, S7 ergänzt). Geprüft: `mollo-planer` bis `a98662d`, `mollo-version` 1.0.0 bis `c169118`.
Rolle: Auftrag für die Umsetzungssitzung in `mollo-planer`. Ergänzt `docs/umsetzung-planer-v2.md` (Grundregeln §0, Checkliste §4 und Stopp-Punkte §5 gelten).
Rahmen (Oliver, 2026-11-13): **Breaking Changes sind erlaubt.** Es gibt keine Altlasten, nur Demoprojekt und Testdaten. `migrate:fresh` lokal ist erlaubt.

Diese Schritte hängen an keiner offenen Entscheidung. Alles, was noch offen ist, steht am Ende unter „Nicht in diesem Auftrag“ und wird in der Planung geklärt.

**Seit 2026-11-13 gibt es nur noch eine Spur.** Format und Werkzeuge aus `mollo-version` ziehen nach `mollo-planer` (`docs/planer-abgleich.md` §4). Wo ältere Dokumente „Spur A“ oder `mollo-version` nennen, gilt jetzt `tools/planer/` bzw. `spec/` in diesem Repository.

**Stand:** S1 erledigt (`d368475`, `f03d853`). U0 erledigt (`c2b1577`). T1 erledigt (`072fcbb`). T4 erledigt (einschließlich Vorlagen: `template`, `validate --template`, `spec/templates/`, Regeln §3.8). S2 erledigt (Inline-Code in Rich Text). S3: A1 (`d53db03`), A2 (`a99cc89`), A3 Teil 1 Reihenfolge der ToDos erledigt (`aa4b336`, `moveTodo`, Zeitachse). A3 Teil 2 erledigt: `Task.position` heißt `boardPosition` (Spalte `board_position`). A5-Spalten erledigt (`archived_at`, `deleted_at`, `deleted_by`, `deletion_id`, ohne Logik). A6 erledigt (`2d04b51`). Fix F4.1 erledigt (2026-11-17, `docs/fix-m1-f4.md`, nur Frontend): „Person ändern“ beschriftet das Feld „Systemrolle“ mit Hinweis „Gilt in der ganzen App. Projektrollen vergibst du im Projekt unter „Mitglieder“.“; Rollen-Badge in `/admin/users` mit Titel „Systemrolle“; `/admin/roles`, Ansicht Rollen: Hinweis über der Liste (Projekt: „Projektrollen vergibst du in einem Projekt unter „Mitglieder“.“, System: „Systemrollen vergibst du unter „Benutzer“.“ mit Link); Mitglieder (auch im Dialog „Neues Projekt“) „Projektrolle“ statt „Rolle“, Badge ohne Bearbeitungsrecht mit Titel „Projektrolle“. DE/EN. Im Browser angesehen (dunkel, DE und EN). Fix F4.2 erledigt (2026-11-17): Chrome füllte das Suchfeld in `/admin/users`, weil der Dialog „Passwort bestätigen“ in den Layouts sein Passwortfeld auch geschlossen bereithielt; das Feld gibt es jetzt nur bei offenem Dialog, in einem eigenen Formular mit verstecktem Feld `autocomplete="username"`. Fix F4.3 erledigt (2026-11-17): Seitenleiste zeigt unter der E-Mail-Adresse die Systemrolle (`UserLine` mit `detail`); Zeilen mit „…“ gekürzt, der Pfeil überdeckt nichts mehr. Fix F4.4 erledigt (2026-11-17): Rechte-Übersicht mit schmalen Rollen-Spalten (Name höchstens zwei Zeilen), Rahmen mit eigener Höhe, Kopf und erste Spalte fest, Schatten links/rechts solange Spalten verborgen sind, Satz „Nicht alle Rollen passen …“ nur bei Bedarf (`useScrollEdges`). Fix F4.5 erledigt (2026-11-17): `Task.viewerCanUpdate`/`viewerCanAssign`/`viewerCanAssignSelf`/`viewerCanDelete` aus `TaskPolicy` und `Rights` (Resolver `TaskViewerCan`, ohne Abfrage je Task); Kanban, Task-Detail, Zeitachse und `useAssigneeChoice` nutzen sie statt `can('TASK_UPDATE')`/`can('TASK_ASSIGN')`, so ändern Mitglieder ihre eigenen Tasks auch in der Oberfläche. Fix F4 damit abgeschlossen. M2 begonnen (`docs/auftrag-m2.md`). RF6 erledigt (2026-11-17): Der Fortschritt zählt die Tasks über `tasks.project_id` bzw. `tasks.todo_id` ohne Joins (Milestone über eine Unterabfrage auf `todos` per Eloquent), damit greifen die Scopes beider Modelle, auch die von `SoftDeletes`. S5 Archiv Backend erledigt (2026-11-17, `docs/auftrag-m2.md` §1): Obergrenze in `Rights` (`Right::holdsWhenArchived`: im archivierten Projekt bleiben nur `project.archive`, `trash.restore`, `planer.export`, auch für `projects.manageAny`; `rightsOf`/`Project.viewerRights` und die `Task.viewerCan*` folgen). Die Policies lehnen mit „Das Projekt ist archiviert und nur lesbar. Hole es zuerst zurück.“ ab (nur wer das Projekt sieht; `Rights::refusedByArchive`), auch die Autorin beim Ändern und Löschen eines Kommentars. Mutationen `archiveProject`, `unarchiveProject` (Recht `project.archive`, wiederholt ohne Wirkung), Feld `Project.archivedAt`, Filter `projects(archived: false)`; Dashboard und `myTasks` ohne archivierte Projekte (Scope `inActiveProjects`), `TaskNotifier` schickt dazu keine Mails; Archivieren und Zurückholen zählen `planer_revision` nicht (`ProjectObserver`). MCP: Schreibwerkzeuge antworten mit einem englischen Satz (`GraphQLFailed::ARCHIVED`), `list_projects` mit `archived`. `ArchivedProjectMutationsTest` schickt jede verbotene Mutation als Owner und Admin in ein archiviertes Projekt; `ProjectArchiveTest`, `ArchivedProjectToolsTest`. `project.archive` ist aus `PENDING`. Oberfläche folgt mit dem Frontend-Teil. S5 Papierkorb Backend erledigt (2026-11-17, §2, mit §0a.2 und §0a.3): `SoftDeletes` an Project, Milestone, ToDo, Task, Kommentar; Elternbeziehungen lesen auch Gelöschtes (`withTrashed`). `App\Services\Deletion`: `delete` gibt dem Element und allen Kindern, die noch nicht im Papierkorb liegen, eine neue `deletion_id` und `deleted_by` (Kinder in einer Abfrage, ohne Ereignisse; vorher einzeln Gelöschtes behält seine eigene), `restore(deletionId, actor)` holt genau diesen Vorgang zurück und lehnt ab, solange ein Elternteil im Papierkorb liegt („Zuerst muss der Milestone „…“ aus dem Papierkorb zurück.“, Feld `deletionId`), `purge` endgültig über die Kaskaden, `emptyProjectTrash` (Anzahl der Vorgänge), `topOf`/`deletionIdsIn`. Observer mit drei Fällen: `deleted` nur wenn nicht `isForceDeleting()` (Lücke schließen, auch in den Kanban-Spalten über `KanbanOrdering::close`, Status, Revision), `restoring` ans Ende von Projekt, Milestone bzw. Spalte, `restored` (Status, Revision), `forceDeleted` ohne Wirkung; mitgekommene Tasks ans Ende ihrer Spalten (`KanbanOrdering::append`). Revision: Löschen +1, Wiederherstellen +1, endgültig 0. Labels und Mitgliedschaften bleiben. Grundregel §7.3: Projekte im Papierkorb zählen nicht (`projectsOnlyHeldBy` über Eloquent, `projectIdsWithHolder` mit `projects.deleted_at`); ein wiederhergestelltes Projekt ohne Person mit „Projekt löschen“ bekommt die wiederherstellende Person mit der Ersteller-Rolle. Policies `restore`/`forceDelete` (Milestone, ToDo, Task: `trash.restore`/`trash.purge`; Kommentar: Autorin oder `comment.moderate` bzw. `trash.purge`; Projekt: `project.delete` bzw. `trash.purge`, beides auch mit `projects.manageAny`). Abfragen: alle über Eloquent bzw. `toBase()` mit Scopes, ein Test prüft Kanban, Zeitachse, Fortschritt, Dashboard, `myTasks`, `task`. `PENDING` in `RightUsageTest` ohne Rechte aus S5. Test `TrashTest`. S5 Papierkorb GraphQL erledigt (2026-11-17, §3): Enum `TrashItemType`, Typ `TrashItem` (ein Eintrag je Vorgang, älteste zuerst, `childCount` und zusätzlich `childCounts` je Art für „mit 3 ToDos, 12 Tasks“, Kommentare als Auszug höchstens 80 Zeichen über `RichTextDocument::excerpt`), Abfrage `trash(projectId)` (mit `trash.restore` oder `trash.purge`, sonst leer; ohne projectId die gelöschten Projekte, die man wiederherstellen darf), Mutationen `restoreFromTrash` (gibt den Eintrag zurück, wie er im Papierkorb stand), `purgeFromTrash`, `emptyProjectTrash` (`@can emptyTrash`), Feld `deletionId` an Project, Milestone, ToDo, Task, Kommentar. Dienst `App\Services\TrashListing` (eine Abfrage je Art, Test: gleich viele Abfragen bei 2 und 15 Einträgen). `restoreFromTrash` ist im archivierten Projekt erlaubt, die anderen beiden nicht; `MutationCoverageTest` über `OutsiderAccessTest`/`MutationTest`. Rechte-Tests mit Standardrollen und eigenen Rollen, Lesend, Kommentar-Autorin und Moderation, gelöschte Projekte (`TrashGraphQLTest`). S5 Frontend erledigt (2026-11-17, §4 und §1.6, Browser-Prüfung folgt mit den frischen Demo-Daten am Ende von M2): `useDelete` löscht ohne Rückfrage und zeigt „Gelöscht“ mit „Rückgängig“ (8 Sekunden, `useToast` mit `action`; `wa-toast-item` hält bei Maus und Fokus an; lag der Fokus auf dem gelöschten Element, geht er auf „Rückgängig“), für Milestone, ToDo, Task, Kommentar; Projekt löschen und endgültig löschen fragen weiter (`useConfirmedDelete`). `useTrashRestore` holt zurück (über `useProjectMutation`), zeigt die Ablehnung „Zuerst muss … zurück“ als Meldung und leert nach Projekten die Listen im Cache (`evictProjectLists`, auch nach Archivieren, Zurückholen, Löschen). Reiter „Papierkorb“ (`/projects/:id/trash`, mit `TRASH_RESTORE` oder `TRASH_PURGE`, sonst Umleitung) mit `TrashList` (Typ, Titel, „mit 3 ToDos, 12 Tasks“, gelöscht von, wann), „Endgültig löschen“ und „Papierkorb leeren“ mit Bestätigung samt Anzahl; Seite `/trash` für gelöschte Projekte, Menüpunkt nur mit Einträgen oder `PROJECTS_MANAGE_ANY`. Archiv: Karte in den Einstellungen („Archivieren“ mit Bestätigung, „Zurückholen“), Hinweisleiste „Archiviert, nur lesbar“ im Projekt, Bereich „Archiv“ (eingeklappt) in der Projektliste (`ProjectGrid`). Texte DE/EN; die alten Bestätigungstexte für Milestone, ToDo, Task und Kommentar entfallen. `db-schema.ts` beschrieb die Spalten schon. Tests: `use-delete`, `toast`, `trash` (Vitest); für `wa-button` im Dokument ergänzt `tests/nuxt/support/element-internals.ts` happy-dom um `attachInternals`. S6 C2 erledigt (2026-11-17): `Project.description` ist `RichText` (Migration `2026_11_17_100000`: Klartext wird zu Absätzen, Leerzeilen trennen sie, zurück wieder Klartext; Cast `RichTextCast`; Eingaben mit `RichTextRule`). Der Plan des Assistenten und `create_project` (MCP) liefern weiter Klartext, `PlainTextDocument::from` macht daraus das Dokument; `ProjectPlanInput.description` bleibt Klartext bis I3/B5a. Demo- und Entwicklungs-Seeder, Factory angepasst. Frontend: Editor wie bei Milestones in den Projekteinstellungen und im Dialog „Neues Projekt“, Kopf und Karte zeigen den Text auf einer Zeile (`documentText`). Test `ProjectDescriptionTest`, Migration hin und zurück an einer Wegwerf-Datenbank geprüft. S6 C3 erledigt (2026-11-17): `updateMyNotifications(notifyByEmail: Boolean!)` statt `emailEnabled` (Frontend, Postman), `rejectUser` gibt wie `deleteUser` den `User` zurück (vor dem Löschen gelesen); `MemberCandidate` stand schon in `types.graphql` (F2), der Kopfkommentar in `schema.graphql` passt. S6 damit erledigt. M2 bereit zur Abnahme (2026-11-17, Demo-Daten frisch, `docs/abnahme-m2.md`); von Oliver bestätigte Abweichungen vom Auftrag: `TrashItem.childCounts` zusätzlich zu `childCount`; im archivierten Projekt holt die Autorin ihren gelöschten Kommentar nicht selbst zurück (Kommentare nur über Autorin bzw. `comment.moderate`, nicht `trash.restore`); lag der Fokus auf dem gelöschten Element, geht er auf „Rückgängig“. Fix F3 erledigt (2026-11-16, `docs/fix-m1-abnahme.md`, ein Commit je Punkt, `7855e49` bis `64ed18b`): F3.1 Mitglieder legen Tasks ohne Zuständige an (`Rights::mayAssignTask`: unveränderte Zuweisung braucht kein Zuweisungsrecht; der MCP-Test prüft die Ablehnung jetzt mit einem Gast). F3.2 Ersteller-Rolle bietet nur Projektrollen mit `PROJECT_DELETE` an (`utils/role-rights.ts`, `rolesQuery` liefert `rights`). F3.3 Die Erstellerin wird immer Mitglied mit der Ersteller-Rolle, auch als Admin; im Dialog fest oben, nicht in der Personenauswahl. F3.4/F3.5 Grundregel §7.3 gilt auch für Admins: `canLeaveProjectWithoutHolder` entfällt; `updateUser` und `deleteUser` prüfen über die Regel `KeepsProjectHolders` (`Rights::projectsOnlyHeldBy`) und nennen die Projekte („Ben Müller ist in Website-Relaunch die einzige Person mit „Projekt löschen“ …“, Meldung am Feld Rolle bzw. als Hinweis beim Löschen); Tests je Weg in `ProjectHolderRuleTest`. F3.6 „Meine Tasks“ blendet Ideen aus, Schalter „Ideen anzeigen“ im Browser gespeichert (`useStoredSwitch`, auch vom Kanban genutzt); Statusfilter „Idee“ zeigt sie weiterhin. F3.7 bleibt wie es ist. F3.8 Die Ablehnung beim Veröffentlichen nennt die Tasks je Grund (höchstens 5, dann „und 3 weitere“, Ideen und Geplantes mit Status); der Plan schreibt „ohne“, der Text sagt „none“ wie das Commit-Feld. Formulare zeigen alle Meldungen eines Felds. F3.9 „Zugewiesen an“ zeigt mit nur `task.assignSelf` nur die eigene Person; Mitglieder › Rolle bietet Rollen mit „Projekt löschen“/`owner.manage` nur mit `owner.manage` an. F3.10 Rechte-Übersicht mit Schatten am rechten Rand (bei 1024 und 1280 px passen mit den Demo-Rollen alle Spalten, bei 620 px scrollt die Tabelle im Rahmen, erste Spalte fest); Hinweise unter der System-Übersicht nennen die Rollen aus dem Backend; leere Datumsfelder mit Beschriftung oben; Suchfeld `/admin/users` mit `name="people-filter"`. Im Browser angesehen (dunkel, DE): Rechte-Übersicht, Hinweise, Datumsfeld, Suchfeld, Ablehnung der Systemrolle „Lesend“ für Ben. Fix F2 Teil 3 erledigt (2026-11-16, §1.3, §2.3): `ProjectMembership::ensureMember` ersetzt `ensureAssignable`: beim Zuweisen (`createTask`, `assignTask`) nimmt jemand mit `members.manage` eine freigegebene Person mit der Standardrolle des Projekts auf, in derselben Transaktion (Revision +1), sonst Ablehnung wie bisher; `task.assignSelf` unverändert; `updateTask` hat keine Zuweisung. Task-Formular und Task-Detail wählen „Zuständig“ mit `PersonSelect` (Mitglieder, darunter „Weitere Personen (werden Mitglied)“ nur mit `MEMBERS_MANAGE`, sonst Hinweis „Nur Projektmitglieder lassen sich zuweisen.“), Meldung „… ist jetzt Mitglied (Rolle …)“ (`composables/useAssigneeChoice.ts`). Fix F2 Teil 2 erledigt (2026-11-16, §1.2, §2.2): `createProject` nimmt `members: [ProjectMemberInput!]` (Schlüssel der Projektrolle); jedes Mitglied über `ProjectMembership::set` in derselben Transaktion, so gelten die Grundregeln (Rolle mit „Projekt löschen“ nur mit `owner.manage`); die Person, die anlegt, behält die Ersteller-Rolle; doppelte, unbekannte oder nicht freigegebene Personen und abgelehnte Rollen ergeben einen Fehler an `input.members.N.userId` bzw. `.role`; Anlegen samt Mitgliedern zählt als eine Änderung (Revision 1). Dialog „Neues Projekt“ mit Abschnitt „Mitglieder (optional)“ (`PersonSelect`, Rolle, Liste mit Entfernen, die anlegende Person fest oben, Fehler an der Zeile). `PersonSelect` leert sich nach dem Hinzufügen. Fix F2 Teil 1 erledigt (2026-11-16, `docs/fix-m1-mitglieder.md` §1.1, §2.1, §2.4): Abfrage `memberCandidates(projectId)` (alle freigegebenen Personen nach Name mit `isMember`; mit Projekt Recht `members.manage` in diesem Projekt, ohne Projekt `projects.create`) ersetzt `userSearch`; `MemberCandidate` steht in `types.graphql` (C3 dazu erledigt); `Rights::allowsInAnyProject` entfällt. Neue Komponente `form/PersonSelect.vue` (ein durchsuchbares Feld, Suche ohne Akzente über `utils/text-search.ts`, Mitglieder sichtbar, aber gesperrt mit „bereits Mitglied“); `MembersCard` nutzt sie. Fix F1 erledigt (2026-11-16, `docs/fix-m1-rollenseite.md`, nur Frontend): `/admin/roles` mit Reitern System | Projekt (Standard Projekt) und Ansichten Rollen / Funktionen / Übersicht; kompakte Zeilen mit Aufklappen (Rechte nach Bereich gruppiert, bei Rollen mit „über Funktion“); Filter Suche (ohne Akzente, 200 ms Verzögerung, Treffer markiert, Zeile klappt bei Treffer in den Rechten auf), Herkunft, „Hat Recht …“ (mehrfach, gruppiert), Gebrauch; Zähler und „Filter zurücksetzen“; Zustand in der URL (`scope`, `view`, `q`, `origin`, `right`, `usage`); Knopf „Rolle anlegen“ bzw. „Funktion anlegen“ mit vorbelegtem Bereich; Übersicht aus `/system/rechte` als `RightsMatrix` (erste Spalte fest, seitliches Scrollen im Rahmen); `/system/rechte` leitet auf `/admin/roles?view=matrix` um, Menüpunkt „Rechte“ entfällt. Formulare wählen Rechte gruppiert (`RightGroups`). Filterlogik als reine Funktionen in `composables/useRoleFilters.ts` (Vitest). Im Browser angesehen: hell und dunkel, DE und EN, Tastatur (Reiter mit Pfeiltasten, Aufklappen mit Enter, Fokus sichtbar), Telefonbreite (390 px im iframe, kein seitliches Scrollen). RF1 erledigt (2026-11-16): Lebenszyklus von Milestone, ToDo und Task nur noch in Observern (`MilestoneObserver` neu; Position, Lücke beim Löschen und Release-Regeln aus den `booted()`-Closures in die Observer). RF3 erledigt (2026-11-16): `TaskLabels::set(Task, labelIds)` prüft und schreibt die Labels eines Tasks; `SetTaskLabels` und `CreateTask` nur darüber. S3c mit RF2 erledigt (2026-11-16): `App\Planer\PlanerRevision::touch(projectId)` zählt `projects.planer_revision` einmal je Projekt und Transaktion nach dem Commit (`DB::afterCommit`, Rollback vergisst), außerhalb einer Transaktion sofort; Aufrufe aus den Observern von Project (neu), Milestone, ToDo und Task, aus `TaskLabels` und `ProjectMembership`; nicht aus `ItemStatusSync`, Kommentaren, Archiv, Vorlagen. Tests `PlanerRevisionTest` (jede Art Änderung genau +1, Milestone mit Kindern +1, Kommentar 0, Lesen 0, abgelehnte Änderung 0, mehrere Änderungen in einer Transaktion +1). RF4 erledigt (2026-11-16): `useProjectMutation` (Composable) ist der eine Weg für Mutationen im Projekt (Zeitstrahl, Kanban, ToDos, Task-Formular, Task-Detail, Kommentare, Mitglieder, Einstellungen, Projekt-Labels) und lädt danach Inhalt und Projektkopf neu; `useProjectRefresh` liefert `refreshProject(projectId)`; der Projektkopf liefert `planerRevision`; `projectContentQueries` entfällt. Außerhalb eines Projekts (neues Projekt, Projekt löschen, Label-Sets, Benutzer, Rollen, Profil) bleibt es wie es war. S3f erledigt (2026-11-16): Zeitachse und Kanban fragen alle 30 Sekunden nur `planerRevision` ab (`ProjectRevision`, nur bei sichtbarem Tab, sofort beim Wiedersichtbarwerden), `useProjectRevisionPolling`; das Frontend merkt sich je Projekt die geladene Revision (`useProjectRevision`); eine eigene Mutation setzt sie nach dem Neuladen, mehr als +1 dabei oder jede neue Revision beim Abfragen zeigt „Das Projekt wurde inzwischen geändert“ und lädt über `refreshProject` neu; während einer eigenen Änderung pausiert die Abfrage. Entscheidung in `utils/planer-revision.ts` (Unit-Test). Im Browser noch nicht angesehen. S3b Teil 1 erledigt (2026-11-16, Backend): `milestones.release_version` und `release_status`, `tasks.commit`, `projects.planer_revision` (Migration `2026_11_16_100000`, Zählung erst S3c); Regeln aus `spec/format-v2.md` §5.3 in `App\Services\ReleaseRules`, beim Speichern von Milestone und Task geprüft (Tests `ReleaseRulesTest`); GraphQL `Release`, `ReleaseStatus`, `Milestone.release`, `Task.commit`, `Project.planerRevision`, Eingaben `releaseVersion`, `releaseStatus`, `commit`. S3b Teil 2 (2026-11-16, Oberfläche, Teilstand): Version und Freigabe im Milestone-Formular (`item/FormModal.vue`), Abfragen liefern `release`. Die Serverfehler lauten `input.releaseVersion`/`input.releaseStatus`/`input.commit`. S3b Teil 3 erledigt (2026-11-16): Commit im Task-Detail (Speichern beim Verlassen des Feldes) und im Task-Formular (beim Anlegen und Ändern; ohne Angabe bleibt der Commit unverändert), Version und Freigabe im Zeitstrahl, Demo-Daten (Sommerfest: Version 1.0 veröffentlicht mit Commit und „none“; Website-Relaunch: Version 1.1 geplant). S3b ist damit umgesetzt. Offen: Browser-Prüfung (hell/dunkel, DE/EN, Tastatur) zusammen mit M1. S3 Rest erledigt: Task-Status `idea` und `planned` (Backend, GraphQL, Kanban mit sechs Spalten und Schalter „Ideen und Geplantes anzeigen“, Fortschritt und Status ohne Ideen, Dashboard, Zuweisungs-Mail erst nach Idee, MCP-Werkzeug). Vergleichstest `SchemaAgreementTest` prüft `TaskStatus` und Rollen gegen das Schema. Noch offen aus S3 Rest: `LabelColor`, Locale, `release.status` (mit S3b). T0 erledigt (`19fb404`, `planer-v2`). T5 erledigt: IDs `m-<Zahl>` bzw. `m-new-<Zahl>`, kein `appId` mehr (nur Werkzeug und Format). T6 erledigt: kanonisches Markdown in `tools/planer` (`core/richtext.mjs`, Fehler nach §9.6, Hinweise nach §9.5, Fixtures `spec/fixtures/richtext/`, Spezifikation Abschnitt 6.1). Die PHP-Whitelist (`RichTextDocument.php`) ist noch nicht gegen den Konverter geprüft, das folgt mit S3e. R1 Stufe 1 erledigt: ein Dienst `App\Support\Rights` ersetzt `ProjectPermissions` und `SystemPermissions`, die Standardmatrix steht in `config/permissions.php` (verhaltensgleich, 588 Tests grün). R1 Stufe 2 erledigt: Tabellen `roles`, `functions`, `function_rights`, `role_functions` (Migration `2026_11_13_160000`), Standardzeilen aus `config/permissions.php` über `App\Support\RightsDefaults`, `Rights` liest die Tabellen (einmal je Anfrage geladen), Obergrenze der Systemrolle als `project_cap` (Lesend = keine Projektrechte, ohne Sonderregel). Die Spalten `users.role` und `project_user.role` bleiben vorerst Schlüssel-Texte (kein `role_id`). R1 Stufe 3a erledigt: Rechte `task.updateOwn`, `task.assignSelf`, `owner.manage`, Projektrolle Gast (ohne Rechte), Mitglieder dürfen Aufgaben anlegen, eigene ändern und sich selbst zuweisen (`Rights::mayAssignTask`), Befehl `permissions:sync-defaults`. R1 Stufe 3b Teil 1 erledigt (2026-11-15): Enum `Right` (alle Rechte aus §4, Bereich System/Projekt, Kurztexte in `lang/{de,en}/rights.php`) ersetzt `ProjectAbility`/`SystemAbility` in PHP, GraphQL und Frontend; `Rights::allows(User, Right, ?Project)` und `Rights::rightsOf(User, ?Project)`; `Project.viewerRights`, `viewerRights` (System), `permissionMatrix` mit `right`; Funktionsschlüssel englisch, Standardbelegung nach §5 (Matrix-Tests darauf ausgerichtet); `updateAppSettings` prüft `settings.manage`; `RightUsageTest` (jedes Recht wird geprüft oder steht mit seinem Schritt als ausstehend in der Liste); Kopfkommentar im Schema gekürzt. `labelSets.manage` und `labelSets.manageAny` bleiben bis S7 als Rechte bestehen und hängen an den Funktionen „Vorlagen“ bzw. „Alle Vorlagen“ (stehen nicht in §4, dort ersetzt durch `templates.*`). R1 Stufe 3b Teil 2 erledigt (2026-11-15): Grundregeln §7.1 bis §7.4 folgen Rechten statt Rollennamen (`Rights::isOwnershipRole`, `holdsProject`, `countHolders`, `countUsersWith`; `ProjectMembership` schützt die letzte Person mit `project.delete`; `UserPolicy` schützt `users.manage` und `roles.manage` vor Selbstentzug und vor dem Verlust der letzten Person), Ersteller-Rolle aus den Einstellungen (`AppSettings::projectCreatorRole`, Standard Owner, noch ohne Oberfläche), `Rights::clearCache()` vor jedem Queue-Job (`JobProcessing`), Tests in `RightsGroundRulesTest` (verschieben Funktionen zwischen Rollen statt eigener Rollen, weil eigene Rollen erst mit `role_id` möglich sind). R1 Stufe 3b Teil 3 erledigt (2026-11-15): Tätigkeit statt Funktion (`users.activity`, Migration `2026_11_15_100000`, Enum `UserActivity`, GraphQL `User.activity`, Texte „Tätigkeit“/„Activity“). R1 Stufe 3b Teil 4 erledigt (2026-11-15): `users.role_id` und `project_user.role_id` (Migration `2026_11_15_110000`, `roles.is_default`), Modell `App\Models\Role` (Registry je Anfrage), Attribut `role` über `HasRole`; GraphQL-Typ `Role` (`id`, `scope`, `key`, `names`, `isDefault`, `isStandard`, `rights`), `User.role`, `ProjectMember.role`, `Project.viewerRole` und `permissionMatrix.roles` als `Role`, Abfrage `roles(scope)`, Eingaben nehmen den Rollenschlüssel als String (`updateUser`, `createUser`, `setProjectMember`); eigene Rollen funktionieren in allen Prüfungen (Tests mit eigener Rolle je Grundregel in `RightsGroundRulesTest`); Frontend liest Rollen und ihre Namen vom Backend (`useRoles`, `useRoleName`), Rechte-Tabelle mit den Rollen als Spalten. Die PHP-Enums `UserRole` und `ProjectRole` sind nur noch Schlüssel der mitgelieferten Rollen (Seeder, Factories, Tests); das GraphQL-Enum `ProjectRole` bleibt bis I1 bis I3 im alten Projektplan (`plan.graphql`, MCP-Werkzeuge). R1 Stufe 3b Teil 5 erledigt (2026-11-15): Typ `RoleFunction` (Modell `App\Models\RoleFunction`, Tabelle `functions`), `Role.functions`, `Role.projectCap`, `Role.usageCount`, Abfrage `roleFunctions(scope)`; Mutationen `createRole`, `updateRole`, `duplicateRole`, `deleteRole`, `setDefaultRole`, `resetRole`, `createRoleFunction`, `updateRoleFunction`, `duplicateRoleFunction`, `deleteRoleFunction`, `resetRoleFunction` (Recht `roles.manage`, Policy `manageRoles`); Dienst `App\Services\RoleAdministration` prüft nach jeder Änderung die Grundregeln §7.1 bis §7.3 gegen den Zustand davor (jemand behält `users.manage` und `roles.manage`, niemand nimmt sie sich selbst, kein Projekt verliert die letzte Person mit `project.delete`, auch nicht über die Obergrenze) und macht sonst alles rückgängig; Schlüssel eigener Rollen und Funktionen entstehen aus dem englischen Namen und ändern sich nie; Mitgeliefertes lässt sich ändern und zurücksetzen, aber nicht löschen; eine Rolle lässt sich nur löschen, wenn sie niemand hat und sie weder Standardrolle noch Ersteller-Rolle ist, eine Funktion nur, wenn keine Rolle sie verwendet; Texte in `lang/{de,en}/roles.php`; Tests in `RoleAdministrationTest`. R1 Stufe 3b Teil 6 erledigt (2026-11-15): Seite `/admin/roles` (Middleware `roles`, Recht `roles.manage`, Menüpunkt „Rollen“): Rollen und Funktionen je Bereich als Karten, anlegen, ändern, duplizieren, löschen, Standardrolle setzen, Standard wiederherstellen (`RoleFormModal`, `RoleFunctionFormModal`, `useRoleSettings`); Obergrenze als „alle / keine / ausgewählte“; Ablehnungen des Servers (Grundregeln, Rolle vergeben) erscheinen als Meldung; Abfrage `rights(scope)` liefert die Rechte eines Bereichs für die Auswahl. Die Übersicht Rolle × Recht bleibt vorerst die Seite `/system/rechte`, `/admin/roles` verlinkt sie. Im Browser noch nicht angesehen. RF7 erledigt (2026-11-16): `Rights` nimmt nur noch `Role` an; `UserInvitation` nimmt `?Role`, `CreateAdmin` löst den Schlüssel `admin` über `Role::resolveOrFail`. Die Tests übergeben Enum-Schlüssel über `projectRole()`/`systemRole()` aus `Tests\TestCase`. RF5 erledigt (2026-11-16): Seiten und Menü lesen dieselbe Liste `frontend/app/utils/route-rights.ts` (Pfad → Recht, längster Präfix gewinnt); die globale Middleware `rights.global.ts` ersetzt `admin.ts` und `roles.ts`; `/system/rechte` verlangt `ROLES_MANAGE`, `/admin/settings` `SETTINGS_MANAGE`. Im Browser noch nicht angesehen. Ersteller-Rolle in den Einstellungen erledigt (2026-11-16): `Query.appSettings.projectCreatorRole`, Mutation `setProjectCreatorRole(roleKey)` (Recht `settings.manage`; die Rolle muss `project.delete` haben), Karte auf `/admin/settings`. Im Browser noch nicht angesehen. Offen in R1 (Stufe 3b): Existenzprüfung von `members[].role` im Import (I2). Datenbank-Doku erledigt (2026-11-16): die vier Rechte-Tabellen und `role_id` in `frontend/app/utils/db-schema.ts`, Texte in Deutsch und Englisch; die Migration `2026_11_15_110000` nennt jetzt je Tabelle den Namen, damit der Test die Spalten lesen kann; `tasks.status` nennt alle sechs Werte.


**D1 erledigt (2026-11-13, Planung):** Dokumente bereinigt, Einstiegsdokument `docs/README.md`, dieses Dokument von `spur-b-sichere-schritte.md` in `umsetzung.md` umbenannt, historische Dokumente markiert. Offen aus D1 für die Umsetzung: Kopfkommentar in `backend/graphql/schema.graphql` (in R1 Stufe 3b).

## Meilensteine (entschieden 2026-11-13, `docs/neubewertung-2026-11-13.md` §5)

Nach jedem Meilenstein: alle Prüfungen grün, `migrate:fresh --seed --seeder=DemoSeeder`, Browser-Prüfung (hell/dunkel, DE/EN, Tastatur), **Abnahme durch Oliver**, erst dann der nächste Meilenstein.

| Meilenstein | Schritte | Ergebnis |
|---|---|---|
| **M1 Fundament** | S3 Rest (Status `idea`/`planned`, Vergleichstest App ↔ Schema), R1 Stufe 3b, S3b, S3c, S3f | Rechte einstellbar, Release/Commit, neue Status, gezieltes Neuladen |
| **M2 Sicher löschen** | S5, S6 | Archiv, Papierkorb mit Rückgängig |
| **M3 Vorlagen und Labels** | S3e, S7 | Projekte aus Vorlagen, Labels je Projekt mit Sprachen |
| **M4 Datei-Abgleich** | I1, I2, I3, I4, I5 | Export/Import, Vorschau, Downloads, neuer Connector |
| **M5 Synchronisation** | T2, T3, W1 | `pull`/`push`, GitHub Action, Webhook |
| **M6 Assistent und Ideen** | B5a, B6 | Assistent für alle, Ideen-Seite, KI-Kontext |

**Abhängigkeiten, die nicht verloren gehen dürfen:** I1 braucht S7 (Labels je Projekt mit `names`) und S3e (Konverter). I2 im Modus `full` braucht S5 (Papierkorb). B5a braucht I2, I3, I5.

## Ergänzungen aus der Neubewertung (verbindlich)

- **S3 Rest:** zusätzlich ein Test, der die Werte der App mit dem Schema vergleicht: `TaskStatus`, Rollenschlüssel der Projektrollen, `LabelColor`, Locale, `release.status` (sobald S3b steht). Er darf vorübergehend für Teile, die erst S7 bringt (Labels je Projekt), als bekannte Lücke markiert sein, aber nicht fehlen.
- **R1 Stufe 3b:**
  - Funktionsschlüssel in `config/permissions.php` **englisch** (z. B. `create-projects`, `plan`, `contribute`, `manage-tasks`, `manage-project`, `own-project`), Namen bleiben DE/EN.
  - Standardbelegung genau nach `docs/rollen-funktionen-rechte.md` §5, Matrix-Test darauf ausrichten.
  - **Grundregeln §7 auf Rechte statt Rollennamen:** `Rights::canChangeMembership`, letzter „Owner“ in `ProjectMembership` (= letzte Person mit `project.delete`), Selbstschutz in `UserPolicy` (`users.manage`/`roles.manage` statt `UserRole::ADMIN`), Ersteller eines Projekts bekommt die **in den Einstellungen festgelegte Ersteller-Rolle** (Standard: Owner). Je Regel ein Test mit einer eigenen Rolle, die die gleichen Rechte hat.
  - **Cache:** `Rights::clearCache()` vor jedem Queue-Job (Listener auf `JobProcessing`) und nach jeder Änderung an Rollen oder Funktionen. Test.
  - Kopfkommentar in `backend/graphql/schema.graphql` auf einen Verweis zu `docs/rollen-funktionen-rechte.md` und `permissionMatrix` kürzen.
- **I1:** `PlanSchema` liest die Grenzwerte aus `spec/planer-v2.schema.json`; Test „PlanSchema = Schema“ statt „PlanSchema = plan.graphql“. Namensraum einheitlich **`App\Planer`** (heute `App\Plan` verschieben).
- **I4:** auch `docs/entwicklung.md` (heute Seed für `DevelopmentHistorySeeder` im alten v1-Format) nach Planer v2 umstellen: `docs/entwicklung.planer.json` mit `mollo-planer convert v1-zu-v2`, Seeder importiert die Datei über den Importer. Die Markdown-Ansicht entsteht mit `render`.

---

## U0. Umzug der Werkzeuge aus `mollo-version` (zuerst)

Details: `docs/planer-abgleich.md` §4.4 und §4.7. Quelle: `~/www/mollo-version`, Stand `c169118`.

- **Übernehmen:**
  - `spec/format-v2.md`, `spec/planer-v2.schema.json`, `spec/messung-helfer.md` → `spec/`. `spec/format.md` (v1) → `spec/format-v1.md`, nur als Grundlage für `convert`.
  - `src/`, `bin/` → `tools/planer/core/` (Prüfung, kanonisches JSON, `render`, `md2json`, Whitelist, v1-Parser, Konvertierung) und `tools/planer/cli/` (Befehle). Aufteilung: Alles, was Dateien liest oder schreibt oder Argumente auswertet, gehört in `cli/`; reine Funktionen in `core/`.
  - `test/` → `tools/planer/test/`, `fixtures/` → `spec/fixtures/`, `skills/planer/` → `tools/planer/skills/planer/`, `scripts/messung-helfer.mjs` → `tools/planer/scripts/`.
- **Nicht übernehmen:** `Backend/` (Laravel-Paket, `app_versions`), `frontend/`, `demo/`, `templates/vue-webawesome/`, Teile von `src/` und `test/`, die nur der v1-Versionsseite dienen (Auswertung für `app_versions`, Vorlagen-Test der Vue-Seite). Der v1-**Parser** und `convert v1-zu-v2` bleiben.
- Befehl umbenennen: `mollo-planer` (`tools/planer/cli/bin/mollo-planer.mjs`), `package.json` in `tools/planer/` mit `"bin"` und `"test": "node --test test/*.test.mjs"`, keine Abhängigkeiten, Node ≥ 20.
- Texte und Kommentare: `mollo-version` → `mollo-planer` (Werkzeug), Format bleibt `planer-v2`.
- `deploy/deploy.sh`: `tools/` und `spec/fixtures/` nicht auf den Server, `spec/planer-v2.schema.json` schon (die App liest es).
- CI bzw. `composer test`-Umfeld: `npm test` in `tools/planer/` als eigener Schritt in den Prüfungen vor jedem Commit (in `CLAUDE.md`/`AGENTS.md` ergänzen).
- Commit: `Chore: move planer/2 format and tools from mollo-version (c169118)`. Danach alle Tests grün (`tools/planer`: `npm test`).
- In `~/www/mollo-version`: README oben mit Hinweis „Archiviert. planer/2 wird in mollo-planer weiterentwickelt (spec/, tools/planer/).“ Ein Commit `Docs: archived, moved to mollo-planer`. Sonst nichts ändern.

## T0. Umbenennung `planer/2` → `planer-v2` (entschieden 2026-11-13)

Details: `docs/planer-abgleich.md` §11. Reines Ersetzen, keine Logikänderung, vor T5.
- Technischer Wert überall `planer-v2`: `"format": "planer-v2"` (Schema, Validator, Fixtures, Golden Files, Tests, PHP, Frontend).
- Schema-Datei `spec/planer-2.schema.json` → `spec/planer-v2.schema.json` (`git mv`), `$id` `urn:mollo-planer:planer-v2`, alle Verweise darauf.
- Im Fließtext von Dokumenten, Kommentaren, Meldungen und Oberfläche „Planer v2“.
- Dateiendung `<projekt>.planer.json` bleibt.
- Auch alle Dokumente in `docs/` (inkl. dieser Datei und `planer-abgleich.md`) und `spec/format-v2.md`.
- Test: `grep -r "planer/2"` findet nichts mehr (außer in der Git-Geschichte und in §11 von `planer-abgleich.md`, wo die Umbenennung beschrieben ist).
- Commit: `Refactor: rename format planer/2 to planer-v2`.

## T1. Revision nur durch die App, `changed`, Werte klein und englisch (früher A-S1)

`docs/planer-abgleich.md` §1a und §5.1. In `spec/` und `tools/planer/`: `revision` erhöht nur die App, Helfer setzen `"changed": true`. Alle Werte klein und englisch: `locale` `de`/`en`, `release.status` `planned`/`released`, Commit `none`. `render`/`md2json` übersetzen die festen Wörter. `convert` bildet v1 darauf ab. Schema, Spezifikation, Tests und Golden Files nachführen.

## T4. Labels in der Datei, Vorlagen (früher A-S4)

`docs/planer-abgleich.md` §3.3, §3.6, §3.7. In `spec/` und `tools/planer/`: `project.labels` mit mehrsprachigen `names` (§5.2), Task-Feld `labels` (statt `labelNames`), Verweisprüfung, Legende in `render`/`md2json`, `render --lang de|en` und `--legend-translations` (§5.3), `validate --template`, Befehl `template`, `convert` erzeugt Labels der Vorlage „Softwareentwicklung“ in `de` und `en`. Fixtures und `docs/beispiel-planer.md` nachführen.

**T4 Rest (nach T5):** Eine Vorlage ist ein gültiges `planer-v2`-Dokument mit festen Werten nach `docs/planer-abgleich.md` §3.8 (`revision: 0`, nur `-new-`-IDs, `members: []`, keine Zuweisungen, Fälligkeiten, Commits, `release.status` nur `planned`, Task-Status `planned`, mindestens ein Label). `validate --template` = normale Prüfung plus diese Regeln. `template <datei>` setzt genau diese Werte. Kein eigenes Vorlagen-Format. Die Dateien in `spec/templates/` müssen `validate --template` bestehen.

**Zusätzlich in T4:** Task-Status `idea` und `planned` (`docs/planer-abgleich.md` §8), Markierungen `[i]` (idea) und `[p]` (planned).

## T5. IDs aus der Datenbank (V1, entschieden 2026-11-13)

`docs/planer-abgleich.md` §7 V1. In `spec/` und `tools/planer/`:
- `id` hat die Form `m-<Zahl>`, `t-<Zahl>`, `k-<Zahl>` (Datenbank-ID) oder `m-new-<Zahl>`, `t-new-<Zahl>`, `k-new-<Zahl>` (lokal neu, noch nicht importiert). Eindeutig je Datei.
- Feld `appId` entfällt in Schema, Spezifikation, Validator, kanonischer Form, `render` (Kommentar nur noch `<!-- id: k-88 -->`) und `md2json`.
- Helfer vergeben `-new-`-Nummern (nächste freie je Präfix in der Datei). `convert v1-zu-v2` vergibt nur `-new-`-IDs.
- Regel „nie wiederverwenden“ im Validator entfällt (die Datenbank sorgt dafür).
- Fixtures, Golden Files, `docs/beispiel-planer.md` nachführen.

**S3d entfällt** (ersetzt durch T5).

T1 und T4 vor S3, damit die App gleich gegen das neue Format gebaut wird. T2 (`pull`/`push`/`sync`) und T3 (GitHub Action) folgen nach dem Export und Import in der App (I1, I2).

## S1. `useDelete` und Liste für archivierte Projekte (B1)

- `useDelete` als Composable im Frontend. Vorerst mit Bestätigung, also gleiches Verhalten wie `useConfirmedDelete`. Alle 5 Verwendungen umstellen, `useConfirmedDelete` danach entfernen.
- Backend: Liste „Mutationen, die in archivierten Projekten verboten sind“ als Konstante, mit Test gegen das Schema (wie `MutationCoverageTest`). Noch ohne Archiv-Logik.
- Ein Commit je Teil.

## S2. Inline-Code in Rich Text

Entschieden in Spur A (`spec/format-v2.md` §10 Nr. 6): Beschreibungen dürfen Inline-Code enthalten (Mark `code`).

- `backend/app/Support/RichTextDocument.php`: Mark `code` in `MARKS`. Kein Code-Block.
- `frontend/app/utils/rich-text.ts`: gleiche Whitelist.
- TipTap: Mark `Code` im Editor aktivieren, Knopf in der Werkzeugleiste (Icon „code“, Duotone Light), Tastenkürzel wie TipTap-Standard. Texte in `de.json` und `en.json`.
- Tests: `RichTextDocumentTest` und Frontend-Test mit `code`, und weiterhin Ablehnung von `codeBlock`.
- Ein Test, der die Whitelist von Backend und Frontend vergleicht, falls noch keiner besteht.

## S3. Datenstruktur (Schema-Review Schritt 1, ohne Release/Commit/Revision)

Eine Migration bzw. Umbau der bestehenden Migrationen, danach `migrate:fresh --seed --seeder=DemoSeeder` lokal.

- **A1** `project_id` an `todos` und `tasks` (Fremdschlüssel, `cascadeOnDelete`, Index `tasks(project_id, status, board_position)`). Trait `DerivesProject` setzt den Wert im Model-Hook aus dem Elternteil, nie aus der Eingabe. `projectPath()` von Todo und Task wird `project`, `scopeInProject` ein einfaches `where`. GraphQL `Todo.project`, `Task.project`.
- **A2** `created_by` an `milestones`, `todos`, `tasks` (nullable, `nullOnDelete`). Trait `RecordsCreator` setzt den Wert aus dem Login. GraphQL `createdBy: User`.
- **A3** `todos.position` je Milestone über `Ordering` (R1), Relation sortiert, `CreateTodoInput.position` optional, Mutation `moveTodo(id, milestoneId, position)`. Umbenennung `Task.position` → `boardPosition` (Spalte `board_position`). Frontend: Zeitachse sortiert nicht mehr selbst (`Timeline.vue`), Kanban nutzt `boardPosition`.
- **A5 (nur Spalten)** `projects.archived_at`; `deleted_at`, `deleted_by`, `deletion_id` an `projects`, `milestones`, `todos`, `tasks`, `comments`. Noch ohne Logik, `SoftDeletes` noch nicht aktivieren.
- **A6** `tasks.user_story` (JSON, nullable) mit `role` (max. 80), `goal`, `benefit` (je max. 160), Klartext, alle drei Pflicht wenn gesetzt. GraphQL-Typ `UserStory`, Feld `Task.userStory`, Eingabe in `createTask` und `updateTask`. Task-Detail zeigt den Satz „Als … möchte ich …, damit …“ über der Beschreibung, Kanban-Karte nicht. Formular im Task-Detail mit drei Feldern.
- **Task-Status `idea` und `planned`** (`docs/planer-abgleich.md` §8): `TaskStatus` in PHP, GraphQL und Frontend, `ItemStatusSync` und Fortschritt ohne `idea`, Kanban mit sechs Spalten und Schalter für `idea`/`planned`, „offene Tasks“ ohne `idea`, Zuweisungs-Mail erst ab `planned`.
- Seeder: Demoprojekte mit Positionen der ToDos, einigen User-Stories, `created_by`.
- `composer schema:frontend`, `npm run codegen`, alle Prüfungen.
- Commits: je Punkt (A1, A2, A3, A5-Spalten, A6) einer.

## S3b. Release, Commit und Revision (Schema-Review A7, entschieden 2026-11-13)

Details in `docs/planer-abgleich.md`, Abschnitt 1. Eigene Migration nach S3.

- `milestones.release_version` (`M.m`) und `milestones.release_status` (`planned`, `released`), beide gesetzt oder beide leer. Oberfläche zeigt „geplant“/„veröffentlicht“ bzw. „planned“/„released“.
- `tasks.commit`: Hash (7 bis 40 Kleinbuchstaben und Ziffern) oder `none` (Oberfläche: „ohne“).
- `projects.planer_revision` (Standard 0). Gezählt wird in S3c.
- GraphQL: `Release`, `ReleaseStatus`, `Milestone.release`, `Task.commit`, `Project.planerRevision`, Eingaben in Milestone- und Task-Mutationen.
- Regeln wie `mollo-version` `spec/format-v2.md` §5.3, mit Tests: veröffentlichte Version nur mit `done`-Tasks, `done`-Task in veröffentlichter Version braucht Commit oder `none`, `none` nur bei `done`.
- Oberfläche: Version und Freigabe am Milestone (Zeitstrahl, Formular), Commit im Task-Detail.
- Seeder: Demoprojekt mit einer veröffentlichten und einer geplanten Version.

## S3c. Zählung der Planer-Revision (entschieden 2026-11-13)

**Vorher bzw. dabei: `docs/refactoring-m1-m3.md` RF1 bis RF3 (Dienst `PlanerRevision`). Mitglieder (`project_user`) zählen auch, weil `project.members` in der Datei steht.**

Details in `docs/planer-abgleich.md`, Abschnitt 1a.

- `projects.planer_revision` +1 bei jeder Änderung an Projekt, Milestone, ToDo oder Task: Felder, Status, Reihenfolge, Zuweisung, Labels, Release, Commit, User-Story, Papierkorb und Wiederherstellen (sobald S5 steht). Kommentare zählen nicht.
- Eine Mutation (oder ein Import) ergibt genau +1, auch wenn sie mehrere Zeilen ändert. Vorschlag: Zähler pro Request bzw. Transaktion merken und am Ende einmal erhöhen (z. B. über einen `afterCommit`-Callback je Projekt).
- Lesen über `Project.planerRevision`.
- Tests: jede Art von Änderung erhöht um genau 1, Kommentar nicht, Lesen nicht.

## S3f. Neuladen über `planerRevision` (ersetzt C1, entschieden 2026-11-13)

Nach S3c. Details: `docs/planer-abgleich.md` §10.
- Jede Mutation, die ein Projekt ändert, liefert die neue `planerRevision` mit (Feld am Rückgabetyp oder über `project { planerRevision }`).
- Frontend merkt sich je Projekt die zuletzt geladene Revision. +1 nach eigener Mutation: nur Projektbaum und Fortschritt neu laden. Mehr als +1: alles neu laden, Hinweis „Das Projekt wurde inzwischen geändert“.
- Zeitachse und Kanban fragen alle 30 Sekunden nur `planerRevision` ab (kleine Abfrage) und laden bei Änderung neu. Keine Abfrage, wenn der Tab nicht sichtbar ist.
- Ersetzt das heutige Neuladen aller aktiven Abfragen (`projectContentQueries`). C1 (Payloads) entfällt.

## S3d. Datei-IDs (entfällt, ersetzt durch T5)

Details in `docs/planer-abgleich.md`, Abschnitt 2.3.

- Spalte `file_id` (String, nullable) an `milestones`, `todos`, `tasks`, eindeutig je Projekt.
- `projects.planer_next_ids` (JSON, Zähler je Präfix `m`, `t`, `k`), nie zurücksetzen.
- Service zum Vergeben der nächsten ID je Präfix, mit Sperre auf dem Projekt (wie `Ordering`). Wird erst vom Export benutzt, hier nur mit Tests: eindeutig, steigend, keine Wiederverwendung nach Löschen.
- Kein GraphQL-Feld nötig (nur für Import und Export). Optional `fileId` zum Lesen für die Fehlersuche.

## S3e. Rich-Text-Konverter Markdown ↔ TipTap-JSON (entschieden 2026-11-13)

**Präzisiert in `docs/auftrag-m3.md` (2026-11-15), dort gilt der Stand für S3e und S7.**

Details: `docs/planer-abgleich.md` §9. Nur PHP, keine neue Abhängigkeit.

- `App\Planer\RichText\MarkdownToTiptap` auf Basis von `league/commonmark` (AST, kein HTML), `App\Planer\RichText\TiptapToMarkdown` als eigener Serialisierer in kanonischer Form (§9.3).
- Umfang: §9.4 verlustfrei, §9.5 vereinheitlicht, §9.6 als Klartext mit Warnungsliste (Rückgabe `{ doc, warnings }`).
- Ergebnis von `MarkdownToTiptap` immer gültig nach `RichTextRule` (gleiche Whitelist, inkl. Mark `code` aus S2).
- Testfälle `spec/fixtures/richtext/*.md` und `*.json`: beide Richtungen, Roundtrip byte-gleich für §9.4, Warnungen für §9.6.
- `PlainTextDocument` wird erst beim Umbau des Assistenten ersetzt (B5a), hier nicht anfassen.

## T6. Kanonisches Markdown in `tools/planer` (entschieden 2026-11-13)

Details: `docs/planer-abgleich.md` §9.3 und §9.7. Kanonische Markdown-Form in `spec/format-v2.md`. `validate`: Konstrukte aus §9.6 sind Fehler (wie bisher), Schreibweisen aus §9.5 ein Hinweis. `json` schreibt Beschreibungen kanonisch. Testfälle aus `spec/fixtures/richtext/` gegen die Whitelist und die kanonische Form.

## R1. Rollen, Funktionen, Rechte (ersetzt S4, entschieden 2026-11-13)

Details: `docs/rollen-funktionen-rechte.md` (verbindlich). Breaking Change, `migrate:fresh` lokal.

- **Rechte** als feste Liste im Code (Enum `Right`, §4) mit Bereich (System/Projekt) und Kurztext DE/EN. Test: jedes Recht wird verwendet, jede Prüfung nutzt ein Recht aus der Liste.
- **Tabellen** `roles`, `functions`, `function_rights`, `role_functions` (§8). `users.role` → `users.role_id`, `project_user.role` → `project_user.role_id`.
- **Standardbelegung** (§5) als Datei im Code, wird beim Seeden und mit `php artisan permissions:sync-defaults` eingespielt; „Standard wiederherstellen“ je Rolle/Funktion. Projektrolle **Gast** (nur lesen) dabei. Test: Standardbelegung ergibt die erwartete Matrix.
- **Dienst `App\Support\Rights`** ersetzt `ProjectPermissions` und `SystemPermissions` (`allows`, `rightsOf`, Cache je Request, Cache-Leeren bei Änderung). Obergrenze der Systemrolle (§3), `projects.manageAny`.
- **Policies** fragen nur `Rights::allows`. Grundregeln §7 bleiben im Code (Policies, `ProjectMembership`), mit Tests je Regel.
- **GraphQL:** Enum `Right` statt `ProjectAbility`/`SystemAbility`, `Project.viewerRights`, `viewerRights` (System), Typen `Role`, `RoleFunction`, Mutationen zum Verwalten (Recht `roles.manage`). `permissionMatrix` wird zur berechneten Übersicht.
- **Neue Rechte** aus der bisherigen Planung sind darin enthalten: `task.updateOwn`, `task.assignSelf`, `project.archive`, `trash.restore`, `trash.purge`, `owner.manage`, `assistant.use`, `ideas.create`, `ideas.viewAny`, `templates.manage`, `templates.manageAny`, `planer.export`, `planer.import`, `aiContext.update`, `roles.manage`, `settings.manage`, `system.view`, `database.view`.
- **Tätigkeit:** `job_function` → `activity` (Spalte, GraphQL, Frontend, Texte „Tätigkeit“/„Activity“).
- **Oberfläche:** Seite `/admin/roles` (Rollen je Bereich, Funktionen, Übersicht Rolle × Recht), Rollenauswahl in Benutzerverwaltung und Mitgliederseite aus der Liste, Knöpfe nach `viewerRights`.
- **`planer-v2`:** `members[].role` ist der Schlüssel einer Projektrolle (`owner`, `manager`, `member`, `guest` oder eigene). Validator prüft nur die Form (`spec/`, `tools/planer`), der Import prüft die Existenz.
- `docs/rollen-plan.md` als ersetzt markieren (Verweis auf `docs/rollen-funktionen-rechte.md`).
- `OutsiderAccessTest`, `MutationTest`, `PolicyTest` auf Rechte statt Rollen umstellen.

**Reihenfolge:** R1 direkt nach S3 (vor S3b), damit alle weiteren Schritte schon gegen `Rights` bauen. Bis dahin tragen Schritte neue Rechte nicht mehr in die alten Tabellen ein.

## S5. Archiv und Papierkorb (Schema-Review Schritt 3, A5)

**Präzisiert in `docs/auftrag-m2.md` (2026-11-15), dort gilt der Stand für S5 und S6.**

Vollständig nach `docs/schema-review.md` A5:

- Archiv: zentral in `ProjectPermissions::allows`, Ausnahmen `project.archive` und Wiederherstellen. `archiveProject`, `unarchiveProject`, Filter `projects(archived:)`. Ausblenden in Dashboard, Seitenleiste, `myTasks`. Test mit der Liste aus S1.
- Papierkorb: `SoftDeletes` aktivieren, `App\Services\Deletion` weich löschen mit `deletion_id` für alle Kinder, `restore()`, `purge()`, `emptyProjectTrash`. `ItemStatusSync` und `Ordering::close` bei Löschen und Wiederherstellen. Keine Frist, kein Scheduler.
- GraphQL `TrashItem`, `trash(projectId)`, `restoreFromTrash`, `purgeFromTrash`, `emptyProjectTrash`. `delete*` geben `deletionId` zurück.
- Frontend: `useDelete` ohne Bestätigung, mit Toast „Rückgängig“ (Milestone, ToDo, Task, Kommentar). Projekt löschen behält die Bestätigung. Seiten `/projects/:id/trash` und `/trash`, Bereich „Archiv“ in der Projektliste.
- MCP: Schreibwerkzeuge lehnen archivierte Projekte ab, `list_projects` mit optionalem Filter. Weiterhin kein Löschen über MCP.
- Ideen sind noch nicht gebaut. `TrashItemType.IDEA` erst mit B6.

## S6. Kleinere Schema-Bereinigungen (Schema-Review C2, C3)

- C2: `Project.description` als RichText (Migration, Input, Frontend-Editor). `ProjectPlanInput.description` bleibt Klartext bis zum Assistenten-Umbau.
- C3: `updateMyNotifications(notifyByEmail:)`, einheitliche Rückgabe von `rejectUser`/`deleteUser`, `MemberCandidate` nach `types.graphql`, Kopfkommentar in `schema.graphql` auf Verweis zu `permissionMatrix` kürzen.

## S7. Vorlagen und Projekt-Labels (entschieden 2026-11-13)

**Präzisiert in `docs/auftrag-m3.md` (2026-11-15).**

Details in `docs/planer-abgleich.md`, Abschnitt 3. Ersetzt das heutige Label-Set-Modell. Breaking Change, `migrate:fresh` lokal.

**Datenmodell:**
- `labels.project_id` statt `labels.label_set_id`: Jedes Projekt hat eigene Labels (Schlüssel `key`, `names` als JSON Sprache → Name, Kategorie, Farbe, Position), Schlüssel und Name je Sprache eindeutig je Projekt, höchstens 30. Name in der Sprache des Projekts Pflicht (`docs/planer-abgleich.md` §5.2).
- `projects.label_set_id` entfällt. Optional `projects.template_id` (nullable, `nullOnDelete`) als Herkunft.
- Tabelle `templates`: `key` (nur mitgelieferte), `name`, `description`, `content` (JSON, `planer-v2`-Vorlage), `created_by`, Zeitstempel.
- **Mitgelieferte Vorlagen als Dateien** (V3): `spec/templates/software-development.planer.json`, `event.planer.json`, `podcast.planer.json` mit `names` in `de` und `en`. Ein Befehl `php artisan planer:sync-templates` (auch im Seeder und nach dem Deployment) schreibt sie in `templates`, unveränderlich. `BuiltinLabelSets.php` und die Label-Übersetzungen in `lang/` entfallen. Die Dateien liegen in `spec/templates/` und gehen mit aufs Deployment.
- `label_sets` entfällt.

**Funktionen (Backend und GraphQL):**
- Vorlagen auflisten, ansehen, anlegen, duplizieren, ändern, löschen. Inhalt: Labels und optional Plan-Gerüst (Milestones, ToDos, Tasks mit Titel, Beschreibung, User-Story, Labels, Reihenfolge, `release.version`, Task-Status `planned`). Keine Fälligkeiten, Personen, Commits. **Der Editor bearbeitet nur Name, Beschreibung und Labels** (V3). Das Gerüst wird angezeigt, nicht bearbeitet.
- Projekt aus Vorlage anlegen (`createProject` mit optionalem `templateId`): Labels mit allen Sprachen kopieren, Gerüst anlegen. Solange der Importer (I2) fehlt, legt ein kleiner Dienst das Gerüst an. Mit I2 wird das durch `import` (`full`, leeres Projekt) ersetzt.
- Anzeige der Label-Namen in der Sprache der Oberfläche, sonst der Sprache des Projekts. Editor mit einem Feld je Sprache. Übersetzungen der mitgelieferten Vorlagen aus `lang/` in den Inhalt der Vorlage.
- Labels eines Projekts bearbeiten (`project.update`), Labels aus einer Vorlage übernehmen (ersetzen oder ergänzen; Labels an Tasks bleiben beim Ersetzen erhalten).
- Rechte: `labelSets.manage` → `templates.manage`, `labelSets.manageAny` → `templates.manageAny` (gleiche Rollen). Sichtbarkeit: alle Vorlagen für alle Angemeldeten lesbar, auch Rolle `lesend` (entschieden, `docs/planer-abgleich.md` 3.6 Nr. 4).
- MCP: `list_templates`, `create_project` mit optionalem `template_id`.

**Oberfläche:**
- Seite `/templates` ersetzt `/label-sets` (Liste, Editor für Labels, Ansicht des Gerüsts als Baum, Duplizieren, Löschen).
- Neues Projekt: Auswahl der Vorlage.
- Projekteinstellungen: Labels bearbeiten, „Labels aus Vorlage übernehmen“, „Als Vorlage speichern“.

**Noch nicht in S7:** „Als Vorlage speichern“ aus einem Projekt, Upload und Download einer `.planer.json`. Diese kommen mit Export und Import (I1, I2, I5). Bis dahin wird `content` in PHP mit den Regeln aus `docs/planer-abgleich.md` 3.4 und 3.6 geprüft, mit Tests.

**Tests:** Projekt aus Vorlage (Labels und Gerüst), Bearbeiten der Projekt-Labels verändert keine anderen Projekte, mitgelieferte Vorlagen unveränderlich, Rechte, `OutsiderAccessTest`/`MutationTest` ergänzen.

## I1. Export (V2, nach S7)

Details: `docs/planer-abgleich.md` §1a, §2, §5, §7 V1 und V2, §9.
- `App\Planer\Export`: Projekt → kanonisches `planer-v2` (IDs `m-<id>` …, Werte nach §5.1, Labels mit `names`, Beschreibungen über `TiptapToMarkdown`, Reihenfolge, Release, Commit, User-Story, `revision`).
- PHP: kanonisches JSON wie `tools/planer` (Test: byte-gleich für alle gültigen Fixtures).
- PHP-Prüfung: Schema (`opis/json-schema` gegen `spec/planer-v2.schema.json`) und Semantik, gegen dieselben Fixtures mit erwarteten Fehlern wie `tools/planer`.
- MCP `export_plan(project_id)`.

## I2. Import mit Trockenlauf (V2)

- `App\Planer\Import` mit `full` und `partial` nach §2.1 und §2.2, `partial` = ergänzen und ändern, nie löschen (§7 V2). Ruft die Dienste der App direkt auf, prüft jede Änderung über Policies, eine Transaktion, `planer_revision` +1.
- Neues Projekt ohne `project_id` (mit `projects.create`), aus Vorlage mit `template_id`.
- `dry_run`: Liste der geplanten Änderungen (anlegen, ändern, verschieben, löschen, je mit Pfad) und Fehler, ohne zu schreiben.
- Beschreibungen über `MarkdownToTiptap` (S3e), Warnungen in der Antwort.
- MCP `import_plan(project_id?, plan, mode, dry_run, notify_assignees, template_id?)`.
- Projekt aus Vorlage (S7) auf den Importer umstellen.
- Roundtrip-Tests: `export(import(datei)) = datei` (bis auf `-new-`-IDs), `import(export(db))` ändert nichts.

## I3. Aufräumen: ein Schreibweg (V2)

- Entfernen: MCP `create_project_plan`, `add_to_plan`, `update_plan_item`, `move_milestone`, `set_task_status`, `create_project`, `set_project_member`; `ProjectPlanImporter`, `ProjectPlanReader`, `PlanRules`, MCP-Teile von `PlanSchema`, nicht mehr genutzte Dateien in `graphql/operations/` (und `GraphQLRunner::runNamed`, falls ungenutzt).
- MCP danach: `list_projects`, `export_plan`, `import_plan`, `list_templates`, `list_users` (eingeschränkt, siehe Assistent).
- `docs/claude-connector.md` neu schreiben. Assistent (`draftProjectPlan`, `importProjectPlan`, `plan.graphql`) bleibt bis B5a unverändert.

## I4. Demo-Daten als Dateien (V3)

- `database/seeders/demo/website-relaunch.planer.json`, `sommerfest-2027.planer.json`. `DemoSeeder` legt Personen an und importiert die Dateien. Test: Seeder läuft, Export ergibt die Datei (bis auf IDs).

## I5. Baum-Ansicht, Downloads, Vorlagen aus Dateien (V3, V4, §5.3)

- Baustein `PlanerTree` (V4): zeigt ein `planer-v2`-Dokument, optional mit Markierungen aus dem Trockenlauf. Typen aus `spec/planer-v2.schema.json`.
- Projekteinstellungen: Download „Als planer.json“ und „Als Markdown“ (Sprache wählbar, Schalter „Übersetzungen in der Legende“, über `tools/planer/core` im Frontend).
- Datei-Import mit Vorschau (`dry_run` in `PlanerTree`), dann Eintragen.
- Vorlagen: „Als Vorlage speichern“ (Export + `template`), Upload einer `.planer.json` als Vorlage, Download einer Vorlage, Ansicht des Gerüsts mit `PlanerTree`.

## W1. Webhook für GitHub Actions (entschieden 2026-11-13)

Nach T3. Details: `docs/planer-abgleich.md` §1a und §10.
- Projekteinstellung „Export-Webhook“: URL und Geheimnis, einrichten mit Recht `project.update`. Geheimnis verschlüsselt gespeichert (`encrypted` cast), nach dem Speichern nie wieder angezeigt, nur ersetzbar.
- Queue-Job nach Änderungen (`planer_revision` gestiegen), gebündelt höchstens einmal pro 5 Minuten je Projekt, POST mit Projekt-ID und Revision, signiert mit dem Geheimnis (HMAC-SHA256 im Header). Format passend für GitHub `repository_dispatch`.
- Fehler: drei weitere Versuche mit wachsendem Abstand, danach Hinweis „Letzte Benachrichtigung fehlgeschlagen“ mit Datum in den Projekteinstellungen. Keine Mail.
- Tests mit gefälschtem HTTP-Client.

## B5a. KI-Assistent auf `planer-v2` (entschieden 2026-11-13)

Details: `docs/assistent-ideen-konzept.md` (Fassung 3, verbindlich). Nach I2, I3 und I5 (`PlanerTree`).
- GraphQL `PlanerDocument`, `PlanDraft`, `PlanPreview`, `draftPlan`, `importPlan` (§9.1). Entfernen: `plan.graphql`-Typen, `draftProjectPlan`, `importProjectPlan`, `ReadsProjectPlanInput`, `PlainTextDocument`, `ProjectPlanAssistant::clean()`.
- `ProjectPlanAssistant`: Prompt nach §8 (Personen nach Rechten, kompakter Baum, Labels, Vorlagen, KI-Kontext als Datenblock), Structured-Output-Schema aus `spec/planer-v2.schema.json` erzeugt, Entwurf als Ausschnitt nach §6, `-new-`-IDs vom Server, Vorschau über den Trockenlauf des Importers.
- `/assistant` öffnen für `assistant.use` (bisher nur `projects.create`).
- Oberfläche: Projekt- und Milestone-Auswahl mit Buttons (§4, §5), Vorschlag einer Vorlage bei neuem Projekt, Vorschau mit `PlanerTree`.
- MCP `list_users` nach §8 einschränken.
- Tests nach §14.

## B6. Ideen und KI-Kontext (entschieden 2026-11-13)

Details: `docs/assistent-ideen-konzept.md` §10 und §11, `docs/schema-review.md` B1 und B2.
- Ideen: Tabelle mit `draft` (`planer-v2`-Ausschnitt), Status `open`/`adopted`/`discarded`, `todos.idea_id`, Papierkorb, Seite `/ideas`, „Einplanen“ über den Assistenten oder `importPlan`. Rechte `ideas.create`, `ideas.viewAny`.
- KI-Kontext: Tabelle `project_ai_contexts`, Upload `.txt`/`.md`, 20 000 Zeichen, Freigabe für externe Tools, Recht `aiContext.update`. Einbindung in den Prompt (B5a).
- MCP `create_idea`, `list_ideas`, `get_project_ai_context`.

## T2, T3. `pull`/`push`/`sync` und GitHub Action (nach I2)

Wie `docs/planer-abgleich.md` §1a (A-S2, A-S3), in `tools/planer/`. Tests gegen den echten MCP-Server der App (lokal), nicht gegen eine Nachbildung.

---

## Reihenfolge

U0 → T1 → T0 → T5 → T4 (Rest) → T6 → S2 → S3 → R1 → S3b → S3c → S3f → S3e → S5 → S6 → S7 → I1 → I2 → I3 → I4 → I5 → T2 → T3 → W1 → B5a → B6. (S1 erledigt.) S3c braucht die Zählung beim Papierkorb erst mit S5. S2 und S6 können auch dazwischen laufen.

## Nicht in diesem Auftrag (wird in der Planung geklärt)

- Keine offenen Punkte.
