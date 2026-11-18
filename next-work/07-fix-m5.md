# Auftrag: Fix M5 (F8)

Status: offen
Stand: 2026-11-18, `dev` bis `9a79a76`.
Dokument: `docs/fix-m5.md` (maßgeblich). Ergebnisse der Abnahme: `docs/abnahme-m5.md`.

0. F8.4 `pull --watch` endet nach dem ersten Durchlauf (Timer `unref`), zuerst.
1. F8.1 `DateTime` mit Zeitzone ausgeben (ISO 8601), Frontend-Stellen prüfen. Betrifft die ganze App.
2. F8.2 Meldung nach „Testen“ übersetzt wie die Statuszeile.
3. F8.3 Beim Wechsel der Art das Ziel-Feld leeren bzw. den gespeicherten Wert wieder einsetzen.
4. F8.5 Warnung, wenn `.mollo-planer` mit Zugang für andere lesbar ist.

Abschluss: ein Commit je Punkt, Prüfungen grün, im Browser angesehen, `docs/umsetzung.md` „Stand“ nachgeführt, oben `Status: erledigt (<Commits>)`, anhalten.
