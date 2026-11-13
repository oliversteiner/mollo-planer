# Rollen, Funktionen, Rechte

Stand: 2026-11-13, geprüft bis `7348f28`. Rolle: Analyse und Plan. Status: **entschieden am 2026-11-13** (§9). Umsetzung als Schritt R1 in `docs/spur-b-sichere-schritte.md`.
Ersetzt `docs/rollen-plan.md` und die festen Rechte-Tabellen `App\Support\ProjectPermissions` und `App\Support\SystemPermissions`.

**Auftrag (Oliver, 2026-11-13):** Rechte nicht mehr in der Logik festlegen. In den Einstellungen werden Rollen definiert, Rollen haben Funktionen, aus den Funktionen entstehen die Rechte.

---

## 1. Heute

- Zwei feste Tabellen im Code: `ProjectPermissions` (Projektrolle → Fähigkeiten) und `SystemPermissions` (globale Rolle → Fähigkeiten).
- Feste Rollen: global `admin`, `projektleitung`, `koordination`, `mitglied`, `lesend`; im Projekt `owner`, `manager`, `member`.
- Feld `users.job_function` (Designer, Entwicklung, …) heißt in der Oberfläche „Funktion“, gibt aber keine Rechte.
- Jede neue Regel (z. B. `task.updateOwn`) ist eine Code-Änderung.

## 2. Begriffe

| Begriff | Was | Wer legt fest |
|---|---|---|
| **Recht** | eine einzelne Erlaubnis, die der Code prüft, z. B. `task.update`, `projects.create` | **der Code** (feste Liste). Ein neues Recht entsteht nur mit einer neuen Funktion der App |
| **Funktion** | ein benanntes Bündel von Rechten, z. B. „Aufgaben verwalten“ = `task.update`, `task.assign`, `task.delete` | **Einstellungen** (mit mitgelieferten Funktionen) |
| **Rolle** | ein benanntes Bündel von Funktionen, das einer Person gegeben wird, z. B. „Manager“ | **Einstellungen** (mit mitgelieferten Rollen) |

Der Code fragt **nur** nach Rechten: „Darf Person P im Projekt X das Recht R?“. Er kennt keine Rollennamen und keine Funktionsnamen. Das gilt heute schon für Policies und Frontend (`viewerAbilities`), neu auch für die Zuordnung.

**Namenskonflikt:** Das heutige Feld „Funktion“ (`job_function`, Designer usw.) wird umbenannt in **„Tätigkeit“** (`users.activity`), damit „Funktion“ frei ist für die Rechte-Bündel. Es bleibt eine reine Anzeige.

## 3. Zwei Geltungsbereiche

| Bereich | Rolle gilt | Beispiele für Rechte |
|---|---|---|
| **System** | für die Person in der ganzen App (eine Systemrolle je Person) | `projects.create`, `projects.viewAll`, `users.manage`, `templates.manage`, `assistant.use`, `ideas.create`, `settings.manage` |
| **Projekt** | für die Person in einem Projekt (eine Projektrolle je Mitgliedschaft) | `task.update`, `members.manage`, `project.archive`, `trash.purge` |

- Jedes Recht gehört zu genau einem Bereich. Eine Funktion enthält nur Rechte eines Bereichs. Eine Rolle hat einen Bereich und nur Funktionen dieses Bereichs.
- **Obergrenze aus der Systemrolle:** Eine Systemrolle kann festlegen, welche Projektfunktionen für die Person überhaupt wirken dürfen (Standard: alle). So wird „lesend“ abgebildet: Systemrolle „Lesend“ mit Obergrenze „keine“. Auch als Owner eines Projekts hat die Person dann nur Leserechte. Das ersetzt die heutige Sonderregel im Code.
- **Projekte für alle Projekte:** Das Systemrecht `projects.manageAny` gibt in jedem Projekt alle Projektrechte (heute: Admin). Es ist ein normales Recht und damit einer Funktion zuordenbar.

**Wirksame Projektrechte** einer Person in Projekt X =
(Rechte der Projektrolle in X, begrenzt durch die Obergrenze der Systemrolle) ∪ (alle Projektrechte, falls Systemrecht `projects.manageAny`).

**Sichtbarkeit eines Projekts** = Mitgliedschaft oder Systemrecht `projects.viewAll`.

## 4. Feste Rechte (vom Code)

Vollständige Liste, inklusive der bisher geplanten neuen Rechte. Namen wie heute (`bereich.aktion`).

**Projekt:**
`project.update`, `project.delete`, `project.archive`, `members.manage`, `owner.manage` (Rolle mit `project.delete` vergeben oder entziehen, heute „nur Owner“), `milestone.create`, `milestone.update`, `milestone.delete`, `todo.create`, `todo.update`, `todo.delete`, `task.create`, `task.update`, `task.updateOwn`, `task.assign`, `task.assignSelf`, `task.move`, `task.delete`, `comment.create`, `comment.moderate`, `trash.restore`, `trash.purge`, `planer.export`, `planer.import`, `aiContext.update`.

**System:**
`projects.create`, `projects.viewAll`, `projects.manageAny`, `users.manage`, `roles.manage` (diese Einstellungen), `templates.manage`, `templates.manageAny`, `assistant.use`, `ideas.create`, `ideas.viewAny`, `settings.manage` (App-Einstellungen, 2FA-Schalter), `system.view` (Status-Seite), `database.view` (Datenbank-Doku).

Jedes Recht hat im Code einen kurzen Text (DE/EN) für die Einstellungsseite. Ein Test prüft: jedes Recht wird in mindestens einer Policy oder Prüfung verwendet, und jede Prüfung nutzt ein Recht aus der Liste.

## 5. Mitgelieferte Funktionen und Rollen (Standard)

Die Standardbelegung bildet **genau das heutige Verhalten plus die bereits entschiedenen Änderungen** ab (Mitglieder dürfen Tasks anlegen, eigene ändern, sich selbst zuweisen; Archiv durch Owner und Manager; endgültig löschen nur Owner).

**Projektfunktionen:**

| Funktion | Rechte |
|---|---|
| Planen | `milestone.create`, `milestone.update`, `todo.create`, `todo.update` |
| Mitarbeiten | `task.create`, `task.updateOwn`, `task.assignSelf`, `task.move`, `comment.create`, `planer.export` |
| Aufgaben verwalten | `task.update`, `task.assign`, `task.delete`, `milestone.delete`, `todo.delete`, `comment.moderate`, `trash.restore` |
| Projekt verwalten | `project.update`, `project.archive`, `members.manage`, `aiContext.update`, `planer.import` |
| Projekt besitzen | `project.delete`, `owner.manage`, `trash.purge` |

**Projektrollen:**

| Rolle | Funktionen |
|---|---|
| Owner | Planen, Mitarbeiten, Aufgaben verwalten, Projekt verwalten, Projekt besitzen |
| Manager | Planen, Mitarbeiten, Aufgaben verwalten, Projekt verwalten |
| Mitglied | Planen, Mitarbeiten |
| Gast | – (nur lesen) |

**Systemfunktionen:**

| Funktion | Rechte |
|---|---|
| Projekte anlegen | `projects.create` |
| Alle Projekte | `projects.viewAll`, `projects.manageAny` |
| Benutzer und Rollen | `users.manage`, `roles.manage` |
| Vorlagen | `templates.manage` |
| Alle Vorlagen | `templates.manageAny` |
| KI und Ideen | `assistant.use`, `ideas.create` |
| Alle Ideen | `ideas.viewAny` |
| Betrieb | `settings.manage`, `database.view` |
| Status | `system.view` |

**Systemrollen:**

| Rolle | Funktionen | Obergrenze Projekt |
|---|---|---|
| Admin | alle | alle |
| Projektleitung | Projekte anlegen, Vorlagen, KI und Ideen, Status | alle |
| Koordination | Projekte anlegen, KI und Ideen, Status | alle |
| Mitglied | KI und Ideen, Status | alle |
| Lesend | Status | keine (nur lesen) |

Die Standardbelegung liegt als Datei im Code (`config/permissions-default.php` oder `database/seeders/permissions.json`) und wird beim ersten Start eingespielt. Ein Test vergleicht die Standardbelegung mit einer erwarteten Matrix (wie heute `ProjectPermissionsTableTest`).

## 6. Einstellungen (Oberfläche)

Seite **`/admin/roles`** (Recht `roles.manage`), ersetzt die heutige Rechte-Tabelle (`permissionMatrix`, nur lesen):

- **Rollen:** Liste je Bereich, anlegen, umbenennen (DE/EN), Funktionen anhaken, bei Systemrollen die Obergrenze wählen, duplizieren, löschen (nur wenn niemand die Rolle hat; sonst zuerst umstellen). Standardrolle für neue Personen und neue Projektmitglieder wählbar.
- **Funktionen:** Liste je Bereich, anlegen, umbenennen, Rechte anhaken (Liste aus §4 mit Kurztext), duplizieren, löschen (nur wenn keine Rolle sie nutzt).
- **Übersicht:** Matrix Rolle × Recht (berechnet, nur lesen), damit man sieht, was am Ende gilt.
- Mitgelieferte Rollen und Funktionen sind veränderbar, aber zurücksetzbar („Standard wiederherstellen“). Ausnahme: siehe §7.

Benutzerverwaltung und Mitgliederseite wählen Rollen aus der Liste statt aus festen Werten.

## 7. Grundregeln, die im Code bleiben (nicht einstellbar)

Diese Regeln schützen vor Aussperren und vor Missbrauch. Sie sind keine Rechte, sondern Bedingungen, die immer gelten.

1. **Mindestens eine Person** hat die Systemrechte `roles.manage` und `users.manage`. Die letzte Rolle bzw. Person mit diesen Rechten kann nicht entfernt oder herabgestuft werden.
2. Niemand entzieht **sich selbst** `roles.manage` oder `users.manage`.
3. **Jedes Projekt** behält mindestens eine Person mit `project.delete` (heute „letzter Owner“). Ausnahme: Personen mit `projects.manageAny`.
4. Eine Projektrolle mit `owner.manage`-relevanten Rechten (`project.delete`, `owner.manage`) vergibt oder entzieht nur, wer selbst `owner.manage` hat (oder `projects.manageAny`).
5. **Fremde Kommentare** ändert niemand. Eigene Kommentare ändern und löschen darf die Autorin oder der Autor, solange sie das Projekt sieht.
6. **Eigene Tasks** (`task.updateOwn`, `task.assignSelf`): „eigen“ heißt `created_by` = Person; `assignSelf` nur an sich selbst.
7. **Archivierte Projekte** sind nur lesbar, außer `project.archive` (zurückholen) und `trash.restore`.
8. **Ideen** sieht nur die Autorin oder der Autor, außer mit `ideas.viewAny`.
9. **2FA** in Produktion immer Pflicht, egal mit welchem Recht.
10. Zuweisen nur an Projektmitglieder.

## 8. Technik

- **Tabellen:** `roles` (`scope` system/project, `key` für mitgelieferte, `names` JSON DE/EN, `is_default`, `project_cap` JSON für Systemrollen), `functions` (`scope`, `key`, `names`), `function_rights` (`function_id`, `right`), `role_functions`.
- `users.role` → `users.role_id`; `project_user.role` → `project_user.role_id`. Breaking Change, `migrate:fresh` lokal.
- **Ein Dienst** `App\Support\Rights` ersetzt `ProjectPermissions` und `SystemPermissions`: `allows(User, Right, ?Project)`, `rightsOf(User, ?Project)`. Ergebnis pro Person und Projekt im Request zwischengespeichert, Rollen und Funktionen mit Cache (bei Änderung in den Einstellungen geleert).
- `ChecksProjectAbilities` und alle Policies rufen nur `Rights::allows` auf. Die Grundregeln (§7) bleiben in den Policies bzw. in `ProjectMembership`.
- GraphQL: `ProjectAbility` und `SystemAbility` werden zum Enum `Right`. `Project.viewerAbilities` → `Project.viewerRights`, `viewerSystemAbilities` → `viewerRights` (System). Neue Typen `Role`, `RoleFunction` (Name „Function“ ist in GraphQL frei, aber in PHP reserviert, daher `RoleFunction`), Mutationen zum Verwalten (Recht `roles.manage`).
- `members[].role` in `planer-v2`: der **Schlüssel** einer Projektrolle (mitgeliefert `owner`, `manager`, `member`, eigene Rollen mit eigenem Schlüssel). Der Validator prüft nur die Form, der Import prüft, ob es die Rolle gibt.
- MCP-Token: handeln mit den Rechten der Person, unverändert.

## 9. Entscheidungen (Oliver, 2026-11-13)

1. **Drei Ebenen:** Recht → Funktion → Rolle.
2. **„Funktion“ freimachen:** Das heutige Feld `job_function` heißt neu **„Tätigkeit“** (`users.activity`, GraphQL `User.activity`, Enum `UserActivity`) und bleibt eine reine Anzeige ohne Rechte.
3. **Obergrenze** über die Systemrolle, „Lesend“ ohne Sonderregel im Code.
4. **Grundregeln §7** bleiben im Code.
5. **Projektrolle „Gast“** (nur lesen) wird mitgeliefert.
6. **Je eine Rolle:** eine Systemrolle je Person, eine Projektrolle je Mitgliedschaft.

## 10. Folgen für die laufende Umsetzung

- **S4 entfällt.** Ersetzt durch Schritt **R1** in `docs/spur-b-sichere-schritte.md`, direkt nach S3. Die neuen Rechte (`task.updateOwn`, `task.assignSelf`, `project.archive`, …) entstehen dort als Rechte (§4) mit Standardbelegung (§5).
- Alle späteren Schritte (S3b, S3c, S3e, S5 bis S7, I1 bis I5, B5a, B6) prüfen nur Rechte über `Rights::allows` und die Policies. Welche Rolle ein Recht hat, legen sie nicht fest.
- Die Rechte-Angaben in `docs/schema-review.md`, `docs/planer-abgleich.md` und `docs/assistent-ideen-konzept.md` („Owner und Manager dürfen …“) gelten als **Standardbelegung**, nicht als feste Logik.
