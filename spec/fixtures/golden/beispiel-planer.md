<!-- Ansicht von planer/2, revision 3, lang de. Änderungen nur über mollo-planer (convert md2json oder die Helfer), nicht von Hand. -->

# Mollo Planer

- Sprache: de

Projekt für Planung und Umsetzung. Alle Milestones, ToDos und Tasks stehen in der Reihenfolge, in der sie in der Datei erscheinen.

## Mitglieder

- oliver@mollo.ch | owner

---

## v1.3 Ordering-Grundlage
<!-- id: m-001, app: 12 -->

- Status: veröffentlicht
- Fällig: 2026-10-31

Erste Version des Ordering-Service. Alle Tasks sind erledigt, deshalb ist die Version veröffentlicht. Dann ist ein Commit je erledigtem Task Pflicht.

### Sperre und Reihenfolge
<!-- id: t-001 -->

- Fällig: 2026-10-20

- [x] Milestone-Reihenfolge absichern <!-- id: k-001, app: 88 -->

  *Als Owner möchte ich doppelte Positionen bei gleichzeitigem Anlegen vermeiden, damit die Zeitachse stimmt.*

  - Fällig: 2026-10-18
  - Zuständig: oliver@mollo.ch
  - Labels: Backend, Feature
  - Commit: abc1234

  Die Sperre liegt auf dem **Projekt**, nicht auf dem Milestone. Das ist ein Muster aus `KanbanOrdering`.

- [x] Lücken nach dem Verschieben schließen <!-- id: k-002 -->

  - Labels: Backend, Fehler
  - Commit: def5678

### Fehlerfälle
<!-- id: t-002 -->

- [x] Doppelte Positionen erkennen <!-- id: k-003 -->

  - Commit: 9a8b7c6

---

## v1.4 Schema-Refactoring
<!-- id: m-002 -->

- Status: geplant
- Fällig: 2026-12-15

Vorbereitung der Umsetzung aus dem Schema-Review. Der Status der Version ist geplant, deshalb ist ein Task ohne Commit erlaubt.

### Ordering
<!-- id: t-003 -->

- Fällig: 2026-12-05

- [~] Ordering-Service ausbauen <!-- id: k-004 -->

  *Als Entwickler möchte ich eine einzige Reihenfolge-Logik, damit neue Sortierungen nicht an drei Stellen gepflegt werden.*

  - Fällig: 2026-12-03
  - Zuständig: oliver@mollo.ch
  - Labels: Backend, Feature

  Ziel ist eine Klasse `Ordering` mit den Methoden `nextPosition`, `openSlot`, `move` und `close`.

  Dabei gelten diese Punkte:

  1. Das Projekt wird vor jeder Änderung gesperrt.
  2. Der Lückenschluss läuft nach jedem Löschen.
  3. Die Tests laufen gegen MariaDB.

  ### Hinweis

  Die Methode `close` ist neu und muss in die Tests für Papierkorb (A5) aufgenommen werden.

- [ ] Lückenschluss nach Löschen testen <!-- id: k-005 -->

  - Labels: Backend, Feature

  > Ohne Test fällt ein fehlender Lückenschluss erst in der Zeitachse auf.

### Plan-Typen
<!-- id: t-004 -->

- [?] Plan-Entwurf für bestehende Projekte prüfen <!-- id: k-006 -->

  *Als Assistent möchte ich Milestones und ToDos per ID anhängen, damit ich keine Duplikate erzeuge.*

  - Zuständig: oliver@mollo.ch
  - Labels: Backend, Feature

  Details im Plan: [A4 im Schema-Review](https://planer.mollo.dev/docs/schema-review). Nur **http**, **https** und **mailto** sind als Link erlaubt.

- [ ] Ältere Plan-Felder entfernen <!-- id: k-007 -->

  - Labels: Backend, Design

---

## Labels
| Schlüssel | Name | Kategorie | Farbe |
|---|---|---|---|
| feature | Feature | type | green |
| bug | Fehler | type | red |
| design | Design | phase | pink |
| backend | Backend | area | cyan |
