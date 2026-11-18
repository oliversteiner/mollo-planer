# Auftrag: Zen-Zeichen gross und am linken Fensterrand (ZH1, ZH2)

Status: erledigt (Commit dieser Änderung)
Stand: 2026-11-18, `dev` bis `0618c23`.
Dokumente: `docs/zen-modus.md` §4.3 (Rahmen, Eck-Knopf), §4.6.

Das „Zen-Zeichen“ ist der Eck-Knopf mit dem Blatt in `components/zen/ZenShell.vue` (öffnet die Suche). Er sitzt heute in `main`, das in Zen eine begrenzte Breite hat und zentriert ist. Dadurch steht er am linken Rand der Lesespalte statt am Fensterrand.

1. **ZH1 – weit links:** Der Knopf steht am linken Rand des Fensters, nicht der Spalte. Projektname und Sichern-Status bleiben unverändert in der Spalte. Der Knopf bleibt beim Scrollen sichtbar (wie heute die Leiste) und überdeckt keinen Inhalt. Abstand zum Rand `--wa-space-m`.
2. **ZH2 – doppelt so gross:** Das Blatt (Icon) misst doppelt so viel wie heute (Wert vorher im Browser messen, dann verdoppeln), der Knopf mindestens 44 × 44 px. Grösse nur über `zen.css` bzw. die Styles von `ZenShell`, keine festen Pixel im Template.
3. **Schmale Fenster (Pflicht, §4.3):** Ist links neben der Spalte nicht genug Platz, darf der Knopf keine Eingabefelder, Sichern-Status oder Meldungen überdecken. Dann wie heute in der Spalte (oder die Spalte erhält links Platz). Telefonbreite zählt mit.
4. **Zustände:** gilt in `calm` und `focus`, hell (Leinen) und dunkel (Leder). Fokusring sichtbar, `aria-label` unverändert.

Abschluss: ein Commit, Prüfungen grün, im Browser angesehen (breit, mittel, schmal; calm und focus), `docs/zen-modus.md` §4.3 nachgeführt, `docs/umsetzung.md` „Stand“, oben `Status: erledigt (<Commit>)`, anhalten. `.mcp.json` nicht committen, gezielt stagen.
