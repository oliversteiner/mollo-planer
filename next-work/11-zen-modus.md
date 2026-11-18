# Auftrag: Zen-Modus (Z1 bis Z7)

Status: offen — nach `10-fix-m6-nachtrag.md`.
Stand: 2026-11-18, `dev` bis `f8115ae`.
Dokument: `docs/zen-modus.md` (maßgeblich, Fassung 2).

1. Z1 Erhebung: Tabelle in `docs/zen-modus.md` §5 ergänzen, **auf Freigabe durch Oliver warten** (Z2 und Z3 dürfen schon laufen, Z4 erst nach Freigabe).
2. Z2 Zustand und Rahmen (`useViewMode`, Cookie `view_mode`, `data-view`, `zen.css`, `ZenShell`, Kürzel).
3. Z2b Farbschema „Steingarten“ (`utils/steingarten.ts`, Mindestkontrast, Cookies `zen_brightness`/`zen_contrast`) und Slide-In „Stimmung“ (transparenter Hintergrund, Regler Helligkeit/Kontrast, Vorschläge Leinen/Sand/Stein/Leder, Farbleiste) nach §4.6.
4. Z3 Befehlspalette (`CommandPalette`, `useCommands`, Rechte über `viewerRights`).
5. Z4/Z5 Seiten markieren (Gruppe A, dann B), Zeitachse zerlegen, Sicht „aktueller Milestone“ (erster nicht erledigter).
6. Z6 „Fokus“ für Task und ToDo (`?focus=1`), Mechanik offen für Dokumente später.
7. Z7 Browser-Prüfung und Doku.

Kein Backend, keine neue Abhängigkeit, nur `wa-*` und eigene Bausteine. Ausblenden über `data-zen` und `zen.css`, nicht über `v-if` (Ausnahmen begründen).

Abschluss: ein Commit je Ticket, Prüfungen grün, im Browser angesehen, `docs/umsetzung.md` „Stand“ nachgeführt, oben `Status: erledigt (<Commits>)`, anhalten.
