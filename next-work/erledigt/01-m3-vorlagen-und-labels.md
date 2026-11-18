# Auftrag: M3 „Vorlagen und Labels“ (S3e, S7)

Status: erledigt (4193943), bereit zur Abnahme M3 (Browser-Prüfung durch Bob: Vorlagenseite, Gerüst-Baum, Projekt-Labels, Übernehmen mit Vorschau, Neues Projekt aus Vorlage, Telefonbreite)
Stand: 2026-11-17, `dev` bis `c26119e`. M2 ist abgenommen.
Dokument: `docs/auftrag-m3.md` (maßgeblich), dazu `docs/refactoring-m1-m3.md` (RF8).

## Reihenfolge

1. §0a: RF8 (`App\Plan\PlanSchema` nach `App\Planer`), eigener Commit.
2. §1 (entschieden): gemischt verschachtelte Listen in `spec/format-v2.md` §6.1 und `tools/planer` erlauben, `mixed-list.error.md` → `mixed-list.in.md`/`.out.md`.
3. §2 S3e: `MarkdownToTiptap` (strict/lenient), `TiptapToMarkdown`, gemeinsame Testfälle (`*.tiptap.json`, `*.lenient.json`), Eigenschaftstest.
4. §3 S7: Datenmodell und `planer:sync-templates` (englische Label-Namen korrigieren) → Backend/GraphQL → Frontend.
5. Folgen aus M2 beachten (§0a Nr. 2–7): Labels und Papierkorb, Archiv-Liste, Revision, Reihenfolge des Gerüsts, Beschreibungen über den Konverter, Oberfläche.

## Abschluss

Ein Commit je Teil, `docs/umsetzung.md` „Stand“ nachführen. Am Ende alle Prüfungen grün (auch `tools/planer`), `php artisan migrate:fresh --seed --seeder=DemoSeeder`, im Browser ansehen, oben `Status: erledigt (<Commit>)` setzen und für die Abnahme M3 anhalten. Stopp und Rückfrage bei den Fällen in §5.2.
