# Auftrag: Zen-Nachtrag (ZF1 bis ZF6)

Status: erledigt (7ece663 ZF6, a276eec ZF1, 56f3084 ZF2/ZF3 und der Commit dieser Änderung ZF4/ZF5); Telefonbreite, hell/dunkel und EN offen für die Nachprüfung
Stand: 2026-11-18, `dev` bis `142f359`.
Dokumente: `docs/zen-modus.md` (maßgeblich: §1, §4.4, §4.6, Tickets Z2, Z2b, Z3) und `docs/abnahme-zen.md`.

Beim Zen-Auftrag fehlen Teile, die nach `f8115ae` dazukamen (Commits `9f4a97d`, `c488148`, `6dc35ee` an `docs/zen-modus.md` und `next-work/11`). Bitte die aktuelle Fassung von `docs/zen-modus.md` ganz lesen.

1. **ZF1 Z2b komplett:** `utils/steingarten.ts` (Rampe, Kippen bei 50, Mindestkontrast), Plugin `--zen-*`, Abbildung auf `--wa-*` in `zen.css`, Cookies `zen_brightness`/`zen_contrast` (Standard 12/55), `MoodDrawer` „Stimmung“ (transparenter Hintergrund, Regler Helligkeit/Kontrast, Knöpfe Leinen 6/50, Sand 30/45, Stein 52/70, Leder 88/55, Farbleiste, Kontrastanzeige, Zurücksetzen), Aufruf über Eck-Knopf und Suche. Vitest wie in Z2b.
2. **ZF2 Aufruf nach §4.4:** Palette heißt „Suche“ (Titel, Platzhalter mit „Zen“); Treffer „Zen · <Bereich>“ mit Wahl des Bereichs (aktiv, sonst `zen_area`, sonst Meine Tasks) und Tests; Knopf „Zen“ und Lupe in der Kopfleiste; Kürzel `Cmd/Ctrl+.` und Eintrag im Benutzermenü entfernen; in Zen bei leerer Eingabe oben „Zen verlassen“ und „Stimmung“.
3. **ZF3:** Bei Eingabe ab „ze“ bzw. „ruhig“/„calm“/„stimmung“ stehen die Zen-Treffer immer zuoberst, vor Treffern mitten im Wort.
4. **ZF4:** Knopf „Fokus“ im Task- und ToDo-Detail auch in `calm` sichtbar (Erreichbarkeitsregel).
5. **ZF5:** `Esc` bzw. „Zurück“ aus dem Fokus stellt den vorherigen Zustand wieder her, auch ein vorher offenes Detail.
6. **ZF6 (Entwicklung):** Mit den Änderungen für den Tablet-Zugriff (offen im Arbeitsbaum, von Oliver): Im Entwicklungsmodus die API-Adresse aus dem aufgerufenen Host ableiten (`http://<host>:8000`), wenn `NUXT_PUBLIC_API_BASE` leer ist, damit `localhost:3000` und `10.0.1.122:3000` beide funktionieren; `.env.example` mit Platzhalter `<IP-des-Mac>` statt echter IP; dann diese Änderungen committen (ohne `.env`, ohne `.mcp.json`).

Abschluss: ein Commit je Punkt, Prüfungen grün, im Browser angesehen (Steingarten hell und dunkel, Stimmung, Zen über Suche und Knopf von einer Einstellungsseite aus), `docs/umsetzung.md` „Stand“ nachgeführt, oben `Status: erledigt (<Commits>)`, anhalten.
