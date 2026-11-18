# Auftrag: Assistent wieder lauffähig (F10.3) — DRINGEND, vor dem Zen-Auftrag weiterarbeiten

Status: offen
Stand: 2026-11-18, `dev` bis `6dc35ee`.
Dokument: `docs/fix-m6.md` Abschnitt „F10.3“ (maßgeblich).

Seit `56ff424` (F10.1) lehnt die Claude-API jede Anfrage ab („compiled grammar too large“), der Assistent ist für alle kaputt.

1. Feld `template` aus dem Antwort-Schema entfernen; Vorlage serverseitig aus Knopf bzw. genanntem Namen bestimmen.
2. Test „Schema-Budget“ und Befehl `php artisan assistant:ping` (echter Aufruf, lokal mit Schlüssel).
3. Fehlertext bei eigenem Fehler statt „nicht erreichbar“.

Abschluss: `assistant:ping` OK, echter Entwurf mit genannter Vorlage → verknüpftes Projekt; ein Commit, Prüfungen grün, `docs/umsetzung.md` nachgeführt, oben `Status: erledigt (<Commit>)`, dann mit dem Zen-Auftrag weiter.
