# Auftrag: M4 „Datei-Abgleich“ (I1–I5)

Status: erledigt (ef802e8), bereit zur Abnahme M4 (Browser-Sicht und `migrate:fresh` macht Oliver bei der Abnahme; Rückfragen sind beantwortet, siehe `03-m4-rest.md`).
Stand: 2026-11-17.
Dokument: `docs/auftrag-m4.md` (maßgeblich).

## Reihenfolge

1. §2.1 Spezifikation und `tools/planer` angleichen (leeres Projekt, Rollen-Schlüssel, Projektbeschreibung als Markdown).
2. §2.2 Grenzen in der App (`PlanerLimits`, `planer:check-limits`).
3. §2.3 `tasks.position` mit „Nach oben/unten“ in der Zeitachse.
4. I1 Export → I2 Import mit Trockenlauf → I3 Aufräumen (MCP) → I4 Demo-Daten als Dateien → I5 `PlanerTree`, Downloads, Datei-Import, Vorlagen aus Dateien.

## Abschluss

Ein Commit je Teil, `docs/umsetzung.md` „Stand“ nachführen. Am Ende alle Prüfungen grün, `php artisan migrate:fresh --seed --seeder=DemoSeeder`, im Browser ansehen, oben `Status: erledigt (<Commit>)` setzen und für die Abnahme M4 anhalten. Stopp und Rückfrage bei den Fällen in §8.2.
