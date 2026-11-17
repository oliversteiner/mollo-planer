# Auftrag: Rest M4 nach Rückfragen

Status: offen
Stand: 2026-11-17, `dev` bis `7970dd4`.
Dokument: `docs/auftrag-m4.md` §7a (Entscheidungen von Oliver).

1. `templateId` bei `importPlan`/`import_plan`: bleibt weg, nichts zu tun außer `docs/umsetzung.md` („Rückfrage an die Planung“ → „entschieden 2026-11-17: bleibt weg“).
2. `DevelopmentHistorySeeder` auf `planer-v2` umstellen (§7a), eigener Commit.
3. Keine weiteren Änderungen an M3/M4 vor den Abnahmen. `php artisan migrate:fresh --seed --seeder=DemoSeeder` und die Browser-Sicht macht Oliver bzw. die Planung bei der Abnahme.

Abschluss: Prüfungen grün, oben `Status: erledigt (<Commit>)`, anhalten.
