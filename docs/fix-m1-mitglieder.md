# Fix F2 vor der Abnahme M1: Personen ins Projekt holen

Stand: 2026-11-16, Planung. Code bis `295ee21`.
Anlass (Abnahme M1, Oliver als Anna): Beim Hinzufügen von Mitgliedern erscheint nur Ben.
Entschieden (Oliver, 2026-11-16): (a) eine Personenauswahl mit allen, (b) Mitglieder schon beim Anlegen des Projekts, (c) beim Zuweisen eines Tasks Nicht-Mitglieder ins Projekt holen.

## 0. Ursache

- `components/project/MembersCard.vue` hat zwei Felder für eine Wahl: ein Suchfeld und darunter die Auswahl „Person“. Die Auswahl ist leer, bis im Suchfeld mindestens zwei Buchstaben eines Namens stehen (`userSearch(term)`, Teilstring im Namen, höchstens 20).
- Die Auswahl blendet Personen, die schon Mitglied sind, kommentarlos aus. Im Demoprojekt sind Anna, Chiara und David Mitglied, also bleibt nur Ben. Dass jemand fehlt, weil er schon Mitglied ist, sieht man nicht.
- `CreateProject` kennt keine Mitglieder; ein neues Projekt hat nur die Erstellerin. Aufgaben lassen sich nur Mitgliedern zuweisen (Grundregel §7.10), also erst nach dem Umweg über die Einstellungen.
- `userSearch` prüft `members.manage` in **irgendeinem** Projekt, nicht im betroffenen. Zu weit gefasst.

## 1. Backend und GraphQL

### 1.1 Personen zur Auswahl

```graphql
type MemberCandidate {           # nach types.graphql (C3 aus S6 gleich mit erledigen)
  id: ID!
  name: String!
  avatarUrl: String
  isMember: Boolean!             # nur bei projectId gesetzt, sonst false
}

"Personen, die in ein Projekt aufgenommen werden können. Ohne E-Mail und Sicherheitsstatus."
memberCandidates(projectId: ID): [MemberCandidate!]!
```

- Mit `projectId`: Recht `members.manage` **in diesem Projekt**. Ohne `projectId` (Dialog „Neues Projekt“): Recht `projects.create`. Sonst leere Liste (wie heute).
- Liefert alle freigegebenen Personen (`approved_at` gesetzt), sortiert nach Name, mit `isMember`. Nicht freigegebene oder abgelehnte Registrierungen erscheinen nicht.
- Keine Grenze für die erwartete Größe; ab 500 Personen wäre eine serverseitige Suche nötig (als Hinweis im Code, nicht jetzt bauen).
- `userSearch` entfällt (nur `MembersCard` nutzt es; Breaking Change erlaubt).

### 1.2 Mitglieder beim Anlegen

```graphql
input CreateProjectInput {
  …
  members: [ProjectMemberInput!]   # optional
}
input ProjectMemberInput { userId: ID!, role: String! }   # Rollenschlüssel wie setProjectMember
```

- Die Erstellerin bekommt wie heute die Ersteller-Rolle; steht sie selbst in `members`, wird der Eintrag ignoriert.
- Jedes weitere Mitglied über `ProjectMembership::set` in **derselben Transaktion**; damit gelten die Grundregeln (z. B. Rolle mit „Projekt löschen“ nur mit `owner.manage`, das hat die Erstellerin über die Ersteller-Rolle; **prüfen**, sonst Fehler mit Pfad `input.members.N.role`).
- Doppelte `userId`: Fehler. Nicht freigegebene Person: Fehler.
- Revision: Anlegen samt Mitgliedern ist ein Vorgang (RF2 sorgt für genau +1).

### 1.3 Beim Zuweisen ins Projekt holen

- `createTask`, `updateTask` (falls dort `assigneeId`) und `assignTask` nehmen auch Personen an, die noch nicht Mitglied sind, **wenn** die handelnde Person im Projekt `members.manage` hat. Dann: zuerst Mitglied mit der **Standardrolle des Projekts** (`roles.is_default`, Bereich Projekt) über `ProjectMembership::set`, danach zuweisen, alles in einer Transaktion.
- Ohne `members.manage`: Ablehnung wie heute („nur Projektmitglieder“). `task.assignSelf` bleibt unverändert.
- Die Grundregel §7.10 bleibt wahr: wer zugewiesen ist, ist Mitglied.
- Umsetzung in `ProjectMembership::ensureAssignable` (heute nur Prüfung) → neue Methode `ensureMember(User $actor, Project $project, int|string $userId, string $field): void`, die aufnimmt oder ablehnt. Ein Ort für alle drei Mutationen.
- Mails: Zuweisungs-Mail wie bisher (nicht bei Status `idea`). Keine eigene Mail für die Aufnahme.

## 2. Frontend

### 2.1 Mitglieder in den Projekteinstellungen (`MembersCard`)

- **Ein** durchsuchbares Feld „Person“ statt Suchfeld plus Auswahl. Zeigt beim Öffnen sofort alle Personen aus `memberCandidates(projectId)`, filtert beim Tippen (ohne Groß-/Kleinschreibung, ohne Akzente).
- Wer schon Mitglied ist, steht in der Liste, aber deaktiviert mit „bereits Mitglied“, damit niemand rätselt, warum jemand fehlt.
- Daneben Rolle (vorbelegt mit der Standardrolle) und „Hinzufügen“. Nach dem Hinzufügen: Feld leeren, Liste neu laden (über `useProjectMutation`).

### 2.2 Dialog „Neues Projekt“ (`CreateModal`)

- Neuer Abschnitt „Mitglieder (optional)“: Person wählen (gleiche Komponente wie 2.1, Quelle `memberCandidates` ohne `projectId`), Rolle wählen, zur Liste hinzufügen; Liste mit Entfernen-Knopf. Die Erstellerin steht fest oben mit der Ersteller-Rolle (nicht änderbar).
- Fehler des Servers an der richtigen Zeile (`input.members.N.role`).

### 2.3 Task zuweisen (`task/FormModal`, `task/Detail`)

- Auswahl „Zuständig“: zuerst die Mitglieder, darunter eine zweite Gruppe „Weitere Personen (werden Mitglied)“, **nur** wenn `can('MEMBERS_MANAGE')` im Projekt. Quelle `memberCandidates(projectId)` mit `isMember = false`.
- Nach dem Speichern erscheint die Person in den Mitgliedern (Aktualisieren über `useProjectMutation`) und ein Hinweis „Sven Beispiel ist jetzt Mitglied (Rolle Mitglied).“.
- Ohne `MEMBERS_MANAGE` bleibt die Auswahl wie heute; Hinweis unter dem Feld „Nur Projektmitglieder lassen sich zuweisen.“

### 2.4 Gemeinsame Komponente

- `components/form/PersonSelect.vue` (Name, Avatar, Deaktiviert mit Grund, Gruppen). Genutzt in 2.1, 2.2, 2.3.

## 3. Tests

- `memberCandidates`: Rechte (mit/ohne `members.manage` im Projekt, nur in einem anderen Projekt = leer), ohne `projectId` mit/ohne `projects.create`, nur freigegebene Personen, `isMember` richtig.
- `createProject` mit `members`: Rollen gesetzt, Erstellerin bleibt Ersteller-Rolle, doppelte und nicht freigegebene Personen abgelehnt, Grundregel „Projekt löschen“ greift, Revision +1.
- Zuweisen eines Nicht-Mitglieds: mit `members.manage` → Mitglied mit Standardrolle und zugewiesen (ein Vorgang, Revision +1); ohne → abgelehnt; `assignSelf` unverändert.
- Vitest für die Filterung in `PersonSelect`.
- `MutationCoverageTest`, `OutsiderAccessTest` nachführen; `userSearch` aus Tests entfernen.

## 4. Abschluss

Ein Commit je Teil (1.1 + 2.1, 1.2 + 2.2, 1.3 + 2.3). Prüfungen grün, im Browser ansehen, Stand in `umsetzung.md`, dann melden. Abnahme-Checkliste Abschnitt 1 hat dafür drei neue Punkte.
