# Auftrag: Zen-Farben nachziehen (ZG1 bis ZG3)

Status: erledigt (Commit dieser Änderung)
Stand: 2026-11-18, `dev` bis `c4dd8eb`.
Dokumente: `docs/zen-modus.md` §4.6, `docs/abnahme-zen.md` (Nachprüfung ZF).

1. **ZG1:** In `calm`/`focus` folgen auch Status-Badges (`StatusBadge`: Erledigt Moos, In Arbeit und Review Ocker, Offen/Geplant/Idee neutral), Fokusringe und alle `--wa-color-brand-*`/`--wa-color-focus` der Palette (`accent`); Gefahr bleibt rot mit ≥ 4,5:1. Nur über `zen.css`, keine Farben in Komponenten. Vitest/Snapshot der Abbildung, im Browser hell (Leinen) und dunkel (Leder) ansehen.
2. **ZG2:** Fläche des Slide-Ins „Stimmung“ mit 96 % Deckkraft statt 85 % (Seite bleibt sichtbar, Texte dahinter scheinen nicht mehr durch); weiterhin kein Abdunkeln, keine Unschärfe. `docs/zen-modus.md` §4.6 entsprechend nachführen.
3. **ZG3:** Bildlaufleisten in Zen (Suche, Drawer, Seiten) in `--zen-border`/`--zen-surface` (`scrollbar-color`).

Abschluss: ein Commit, Prüfungen grün, im Browser angesehen, `docs/umsetzung.md` „Stand“ nachgeführt, oben `Status: erledigt (<Commit>)`, anhalten.
