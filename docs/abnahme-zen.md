# Abnahme Zen-Modus (Z1 bis Z7)

Stand: 2026-11-18, geprüft in Chrome durch die Planung an `142f359` (über `http://10.0.1.122:3000`, siehe Hinweis unten), als Chiara. Auftrag: `docs/zen-modus.md`, `next-work/11-zen-modus.md`. Nachtrag: `next-work/12-zen-nachtrag.md`.

**Ergebnis:** Grundgerüst gut. Aber Z2b (Steingarten, Stimmung) und der Nachtrag vom 2026-11-18 (Aufruf über Suche und Knopf) fehlen ganz; der Auftrag stand trotzdem auf „erledigt“. → ZF1, ZF2.

## Geprüft

- [x] ✅ `Cmd+K` öffnet die Palette, mit Aktionen der Seite (Reiter des Projekts, „Mit dem Assistenten ergänzen“, „Milestone anlegen“), Seiten und Projekten; Suche ohne Akzente.
- [x] ✅ „Ruhig“: kein Seitenmenü, keine Kopfleiste, keine Reiter; Zeitachse mit dem aktuellen Milestone (M1, erster nicht erledigter) offen, die übrigen eingeklappt mit „Aufklappen“.
- [x] ✅ Neuladen bleibt in `calm` (`view_mode=calm`), ohne Hydration-Warnung in der Konsole.
- [x] ✅ Task-Detail in `calm`: Kommentare eingeklappt („1 Kommentar“).
- [x] ✅ Fokus über `?task=3&focus=1`: Task allein, Lesebreite; `Esc` kehrt nach `calm` zurück.
- [x] ✅ „Zen verlassen“ in der Palette → `full`.
- [ ] ❌ Kein Farbschema „Steingarten“, kein Slide-In „Stimmung“, keine Cookies `zen_brightness`/`zen_contrast` (§4.6, Z2b). → ZF1
- [ ] ❌ Palette heißt „Befehle“ statt „Suche“, Eintrag „Ruhige Ansicht“ statt „Zen · <Bereich>“, kein `zen_area`, kein Knopf „Zen“ und keine Lupe in der Kopfleiste; dafür noch Kürzel `Cmd+.` und Eintrag im Benutzermenü (sollten entfallen). → ZF2
- [ ] ❌ Eingabe „zen“ zeigt zuerst „Mit dem Assistenten ergänzen“ (Treffer in „ergän*zen*“), „Zen verlassen“ erst danach. → ZF3
- [ ] ❌ Im Task-Detail in `calm` ist der Knopf „Fokus“ nicht sichtbar (Element vorhanden, aber ausgeblendet). → ZF4
- [ ] ❌ (klein) `Esc` im Fokus schließt auch das Task-Detail; vorher war das Detail in `calm` offen. → ZF5
- [ ] ⏸ Telefonbreite, hell, EN, Durchgang nur mit Tastatur: nach ZF1/ZF2.

## Hinweis Entwicklung (nicht Teil von Zen)

Mit `NUXT_PUBLIC_API_BASE=http://10.0.1.122:8000` (Tablet-Zugriff, noch nicht committet) klappt die Anmeldung über `http://localhost:3000` nicht mehr („Die Sitzung war abgelaufen“): Seite und API liegen auf verschiedenen Hosts, die Cookies fehlen. Über `http://10.0.1.122:3000` geht es. → ZF6

## Nachprüfung ZF1 bis ZF6 (2026-11-18, Planung, `dev` bis `c4dd8eb`)

- [x] ✅ ZF6: Anmeldung über `http://localhost:3000` geht wieder (API-Adresse aus dem Host der Seite).
- [x] ✅ ZF1: Steingarten: Leinen (`--zen-bg #EBE2D4`) und Leder wirken sofort; Slide-In „Stimmung“ mit Reglern, Anzeige („Leinen, 12 Prozent“), Vorschlägen Leinen/Sand/Stein/Leder, Farbleiste, Kontrast „8,3 : 1 · AAA“, „Zurücksetzen“; dunkelt nicht ab; Cookies `zen_brightness`/`zen_contrast`. (Die App läuft ohne SSR; das Plugin setzt die Farben vor der ersten Darstellung.)
- [x] ✅ ZF2: „Suche“ mit Platzhalter „… oder ‚Zen‘“; Lupe und Knopf „Zen“ in der Kopfleiste; Benutzermenü nur noch Profil und Abmelden; in Zen oben „Zen verlassen“ und „Stimmung“; aus den Einstellungen startet „Zen“ den zuletzt aktiven Bereich (Kanban von Website-Relaunch, `zen_area`). Ein erster Versuch landete auf dem Dashboard; nicht wiederholbar, vermutlich hat ein anderer Tab im selben Browser das Gerät-Cookie geändert.
- [x] ✅ ZF3: Eingabe „zen“ zeigt den Zen-Treffer zuoberst.
- [x] ✅ ZF4: Knopf „Fokus“ im Task-Detail in `calm` sichtbar.
- [x] ✅ ZF5: `Esc` aus dem Fokus lässt das Task-Detail offen (`?task=4`, `calm`).
- [ ] ❌ (klein) In Zen behalten Status-Badges (z. B. „In Arbeit“ blau) und der Fokusring (violett) die normalen Farben statt Moos/Ocker (§4.6 „Statusfarben“, Markenfarbe = `accent`). → ZG1
- [ ] ❌ (klein) Die Fläche von „Stimmung“ mit 85 % Deckkraft lässt Karten dahinter durchscheinen, Texte überlagern sich (Vorgabe der Planung war zu niedrig). → ZG2
- [ ] ❌ (klein) Bildlaufleiste der Suche in Leinen schwarz statt im Farbschema. → ZG3
- [ ] ⏸ Telefonbreite, EN, Durchgang nur mit Tastatur (für Oliver oder nach ZG).

---

Ergebnis: _bereit zur Abnahme_ (Planung: ZF1 bis ZF6 behoben; ZG1 bis ZG3 klein, folgen in `next-work/13-zen-farben.md`)
