# Plan: Benutzerrollen und Rechte

Stand: 2026-11-10. Status: **Rollenkatalog übernommen.** Die restlichen offenen Entscheidungen stehen unten. Noch nicht gebaut.

## Ausgangslage

Es gibt zwei Ebenen, und bisher wirkt nur eine davon als Rolle:

- **Projektrollen** (`project_user.role`): Owner, Manager, Mitglied. Ihre Rechte stehen in `App\Support\ProjectPermissions` (Tabelle `ROLES_BY_ABILITY`). Das bleibt so.
- **Globale Ebene**: nur das Flag `users.is_admin` (ja/nein). Admins dürfen alles, alle anderen nur das, was ihre Projektrolle erlaubt.

Dadurch lässt sich heute nicht ausdrücken, dass jemand im System etwas darf oder nicht darf, unabhängig von einem Projekt. Beispiel: „Projektmanager“ darf Projekte anlegen, „Designer“ nicht. Heute darf das nur ein Admin (`ProjectPolicy::create`).

Wo `is_admin` heute überall vorkommt (Stand dieses Plans):

| Bereich | Stellen |
|---|---|
| Policies | `ProjectPolicy::create`, `UserPolicy` (6 Stellen), `LabelSetPolicy` |
| Rechte | `ProjectPermissions` (`allows`, `canView`, `canChangeMembership`, `canRemoveLastOwner`, `constrainToVisibleProjects`) |
| Lesen | Scope `VisibleToViewer` (Admin sieht alle Projekte) |
| Schema | `User.isAdmin`, Eingaben `CreateUserInput` und `UpdateUserInput` (`is_admin`) |
| Anlegen | `CreateUser`, `UserInvitation`, `CreateAdmin`-Befehl, `UserFactory`, `DemoSeeder` |
| MCP | `ListUsersTool` |
| Frontend | `middleware/admin.ts`, `layouts/default.vue`, `pages/projects/index.vue`, `pages/admin/users.vue`, `components/admin/UserFormModal.vue`, `graphql/viewer.ts`, `graphql/admin.ts` |
| Tests | rund 40 Stellen in 10 Testdateien, dazu die Postman-Collection |

## Ziel

1. Jeder Benutzer hat **eine globale Berechtigungsrolle**: `admin`, `projektleitung`, `koordination`, `mitglied`. Zusätzlich kann eine **Funktion** angegeben werden (Designer, Entwicklung, Helfende …). Die Funktion dient nur der Anzeige und bestimmt keine Rechte.
2. Pro globaler Rolle ist in **einer zentralen Tabelle** festgelegt, welche **systemweiten Fähigkeiten** sie hat (zum Beispiel `projects.create`, `users.manage`, `labelSets.manage`, `projects.viewAll`).
3. Die Projektrollen bleiben unverändert. Eine Projektrolle erlaubt Dinge innerhalb eines Projekts, die globale Rolle regelt, was systemweit geht.
4. Das Frontend fragt die Fähigkeiten ab, nicht den Rollennamen. Die Oberfläche blendet damit Knöpfe nach Recht ein, das Muster ist schon bei `viewerCanManageLabelSets` vorhanden.

Beispiel mit den vorgeschlagenen Rollen:

| Fähigkeit | admin | projektleitung | koordination | mitglied | lesend |
|---|---|---|---|---|---|
| Projekt anlegen (`projects.create`) | ja | ja | ja | nein | nein |
| Alle Projekte sehen (`projects.viewAll`) | ja | nein | nein | nein | nein |
| Benutzerliste sehen und verwalten (`users.manage`) | ja | nein | nein | nein | nein |
| Label-Sets anlegen (`labelSets.manage`) | ja | ja | nein | nein | nein |
| Eigene Projekte sehen, Mitglied sein | ja | ja | ja | ja | ja |
| Ändern (Mutationen) | ja | ja | ja | ja | nein |

## Rollenkatalog (übernommen)

Quellen und Begründung: Recherche zu Scrum, GitHub-Organisationsrollen und Event-Teams (KonfHub, Race Roster, Eventeny, VenueNow). Berechtigungsrollen regeln Rechte. Funktionen sind nur Titel.

**Berechtigungsrollen** (Feld `role`, regeln die Rechte):

| Wert | Anzeige | Software-Entsprechung | Event-Entsprechung | Mögliche Fähigkeiten |
|---|---|---|---|---|
| `admin` | Administration | GitHub Owner | Gesamtleitung | alles, Benutzer verwalten, Rollen vergeben |
| `projektleitung` | Projektleitung | Product Owner | Eventmanager | Projekte anlegen, Label-Sets verwalten |
| `koordination` | Koordination | Scrum Master | Eventkoordination | Projekte anlegen, keine Benutzerverwaltung |
| `mitglied` | Mitglied | GitHub Member | Helfer, Mitarbeitende | nur in zugewiesenen Projekten arbeiten, keine Projekte anlegen |
| `lesend` | Lesend | Stakeholder | Sponsor, Gast | nur ansehen, nichts ändern |

**Funktionen** (optionales Feld `function`, nur Anzeige und Filter):

| Wert | Anzeige | Herkunft |
|---|---|---|
| `designer` | Designer | Scrum-Team, Creative-Team beim Event |
| `entwicklung` | Entwicklung | Scrum-Team, technische Crew beim Event |
| `tester` | Tests | Scrum-Team |
| `content` | Inhalte | Content Manager (KonfHub) |
| `technik` | Technik | technische Leitung beim Event |
| `volunteer` | Helfende | Volunteer-Koordination |

Nicht aufgenommen, solange die App das nicht kann: Finanz- oder Billing-Rolle. Die App hat keine Budgets oder Abrechnung.

Ob „Designer“ in einem Projekt etwas darf, entscheidet weiterhin die Projektrolle (Owner, Manager, Mitglied). Wer ein Projekt anlegt, wird nicht automatisch Mitglied (heute schon so, siehe `backend-plan.md`, Punkt 11).

## Vorschlag für den Aufbau

**Enum und Tabelle, nicht Datenbank-Editor.** Rollen und ihre Fähigkeiten stehen im Code (wie bei den Projektrollen). Änderungen laufen über Code und Tests. Ein Editor in der Oberfläche wäre später möglich, ist aber für den Anfang nicht nötig.

- `App\Enums\UserRole` mit `ADMIN`, `PROJEKTLEITUNG`, `KOORDINATION`, `MITGLIED`, `LESEND` (übernommen).
- `App\Enums\UserFunction` mit den Funktionen aus dem Katalog (optional, nur Anzeige).
- `App\Enums\SystemAbility` mit den systemweiten Fähigkeiten.
- `App\Support\SystemPermissions` mit einer Tabelle `ABILITIES_BY_ROLE`. Die Regel „Rechte nur über eine Tabelle“ aus `CLAUDE.md` gilt dann für beide Ebenen. Alternative: beides in `ProjectPermissions` ablegen. Das ist unübersichtlicher, deshalb schlage ich eine eigene Klasse vor.
- `users.role` (Spalte, Enum-Wert als String). `users.is_admin` entfällt.
- Admin bleibt ein Sonderfall im Sinn: Rolle `admin` hat alle Fähigkeiten. Der Schutz „der letzte Admin kann sich nicht selbst entmachten“ bleibt bestehen, nur über die Rolle statt das Flag.

## Migration

- Bestehende Benutzer mit `is_admin = true` werden `admin`, alle anderen `mitglied`. Danach `is_admin` entfernen.
- Demo-Daten (Vorschlag): Anna Keller wird `admin`. Chiara wird `projektleitung`, Ben `mitglied` mit Funktion `entwicklung`, David `mitglied` mit Funktion `designer`. So sieht man das Rechtesystem in der Demo.
- Die Migration muss auch in MariaDB laufen (`composer test:mariadb`).

## Schema-Änderung (vorher bestätigen)

Laut `CLAUDE.md` ändert sich das Schema nur nach Rückfrage. Vorgeschlagen:

- `User.isAdmin: Boolean!` wird `User.role: UserRole!` (Enum mit den vier Werten). Dazu `function: UserFunction` (optional, nur Anzeige) und `systemAbilities: [SystemAbility!]!` für die Oberfläche, damit das Frontend ohne Rollennamen auskommt.
- `CreateUserInput.isAdmin` und `UpdateUserInput.isAdmin` werden `role: UserRole`.
- `viewerCanManageLabelSets` wird durch die allgemeine Liste `viewerSystemAbilities` ergänzt oder ersetzt. Vorschlag: ersetzen, damit es nur einen Weg gibt.
- `composer schema:frontend`, danach `npm run codegen`.

## Bau-Reihenfolge

1. **Entscheidungen bestätigen** (siehe unten).
2. **Backend, Rechte:** `UserRole`, `SystemAbility`, `SystemPermissions` mit Tests (jede Rolle gegen jede Fähigkeit, Tabelle als Test-Vorlage).
3. **Backend, Migration:** Spalte `role`, Übernahme aus `is_admin`, `is_admin` entfernen, Demo-Seeder, Factory, `CreateAdmin`-Befehl. Tests gegen SQLite und MariaDB.
4. **Backend, Policies umstellen:** `ProjectPolicy::create` auf `projects.create`, `UserPolicy` auf `users.manage`, `LabelSetPolicy` auf `labelSets.manage`, `VisibleToViewer` und `constrainToVisibleProjects` auf `projects.viewAll`. Jede Policy bekommt Tests für alle vier Rollen.
5. **Backend, Schema und Mutationen:** `role` in Eingaben, `systemAbilities` im Viewer, `ListUsersTool` (MCP), Postman-Collection.
6. **Frontend:** `middleware/admin.ts` durch Prüfung der Fähigkeit ersetzen, Navigation und Projektliste nach Fähigkeiten, `UserFormModal` mit Auswahl der Rolle statt Häkchen. Tests in `tests/nuxt`.
7. **Doku und Stand:** `CLAUDE.md` (Regeln), `docs/backend-plan.md`, `docs/graphql-kontext.md` (Rechte-Tabelle), README (Anleitung für den ersten Admin: `app:create-admin` setzt die Rolle `admin`).
8. **Prüfung im Browser:** je Rolle einmal anmelden und die Oberfläche durchklicken.

## Offene Entscheidungen

1. **Eine Rolle pro Benutzer oder mehrere?** Vorschlag: eine. Mehrere Rollen ergäben eine Vereinigung der Rechte, sind aber schwerer zu verstehen.
2. **Rollen und Rechte:** übernommen. `projektleitung` sieht keine Benutzerliste. Die Rolle `lesend` ist übernommen.
3. **Namenskonflikt:** gelöst. Global heißt die Rolle `projektleitung`, die Projektrolle bleibt „Manager“.
4. **Standardrolle** für neue Benutzer, die ein Admin anlegt: `mitglied` (übernommen).
5. **Designer und Projekte:** Sieht ein Designer nur Projekte, in denen er Mitglied ist? Vorschlag: ja, wie heute.
6. **Ein Admin kann sich nicht selbst die Admin-Rolle entziehen.** Soll das bleiben? Vorschlag: ja.
7. **Zentrale Tabelle:** eigene Klasse `SystemPermissions` (Vorschlag) oder Erweiterung von `ProjectPermissions`.
8. **Schema-Änderung** wie oben beschrieben bestätigen.
9. **Lesend und Projektrollen:** Vorschlag: Die globale Rolle `lesend` begrenzt alles auf Lesen, auch wenn die Person in einem Projekt Owner oder Manager ist.
10. **Benutzerliste für die Mitgliederauswahl:** Heute dürfen Owner und Manager die Benutzerliste sehen (`UserPolicy::viewAny`), damit sie Mitglieder hinzufügen können. Das würde `projektleitung` in Projekten, in denen sie Manager ist, die Liste weiterhin zeigen. Vorschlag: eine schmale Suche nur mit Name für alle, die Mitglieder verwalten dürfen, ohne E-Mail und ohne Sicherheitsstatus. Die volle Liste bleibt Admins vorbehalten.

## Tests (Mindestumfang)

- Rechte-Tabelle: jede Rolle gegen jede Fähigkeit.
- Policies: je Fähigkeit mindestens eine erlaubte und eine verbotene Rolle.
- Migration: `is_admin = true` wird `admin`, andere `mitglied`.
- GraphQL: Anlegen eines Projekts als `mitglied` schlägt fehl, als `projektleitung` klappt es.
- Letzter Admin kann sich nicht selbst entmachten.
- `lesend` darf keine Mutation ausführen, auch nicht als Owner in einem Projekt (Entscheidung 9).
- `projektleitung` sieht die volle Benutzerliste nicht, auch nicht als Manager in einem Projekt (Entscheidung 10).
- Frontend: Knöpfe und Seiten erscheinen nur bei passender Fähigkeit.

## Stand

- [x] Rollenkatalog und Funktionen übernommen (Berechtigungsrollen `admin`, `projektleitung`, `koordination`, `mitglied`, `lesend`)
- [x] Entscheidungen 2, 3, 4 und Standardrolle geklärt
- [ ] Entscheidung 1: eine Rolle pro Benutzer (Vorschlag übernehmen?)
- [ ] Entscheidung 9: `lesend` begrenzt auch Projektrollen
- [ ] Entscheidung 10: schmale Namenssuche für Mitgliederauswahl
- [ ] Entscheidung 7: `SystemPermissions` als eigene Klasse
- [ ] Entscheidung 8: Schema-Änderung (`isAdmin` → `role`, `function`, `systemAbilities`) bestätigt

**Blocker:** Schritt 1 (Bau) braucht die Bestätigung der Punkte 1, 7, 8, 9 und 10. Schritt 2 (Enums und Rechte-Tabelle mit Tests) kann nach Punkt 1 und 7 beginnen, ohne Schema-Änderung.
