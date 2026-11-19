# 16 · Dokumentenverwaltung, Backend (D0 bis D5, D9)

Status: **D1 bis D4 und D9 erledigt** (`3997403` D1, `81889b3` D2, `f002a5f` D3, `8a2ef66` D4, `90d9fd4` D9, auf `feature/dokumente`, `dev` eingemergt am 2026-11-19, nichts auf `dev` gepusht). **D5 (PDF) wartet:** mPDF 8.3.1 verlangt wirklich `~8.5.0` (Packagist, geprüft 2026-11-19), das würde ein Update auf PHP 8.6 blockieren; `composer require` läuft erst nach der Antwort von Oliver und Stuart (siehe unten, «Entscheid zu D0»).
Entscheid zu D0 (Oliver und Stuart, 2026-11-19, übernommen aus `16-entscheid-d0.md`): **Profil «Dokument»** wie in `docs/dokumente-versuch.md` §1 und §4 Punkt 1 (Überschrift 1 bis 4, Codeblock mit Sprache, Trennlinie, Durchgestrichen, Tabelle mit genau einem Absatz je Zelle, kein Zellenverbinden, keine Ausrichtung, erste Zeile immer Kopfzeile, Aufgabenliste und Aufzählung nie gemischt; Unerlaubtes lehnt der Server mit Fehler ab; die CommonMark-Grenze steht in `spec/format-v2.md` §6.2). **PDF-Bibliothek: mPDF**, `dompdf` bleibt Ausweichlösung (`docs/dokumentenverwaltung.md` §7.2). Vor `composer require` zu prüfen: die PHP-Anforderung von mPDF (siehe oben). Server (Oliver, 2026-11-19): PHP 8.5, `dom`, `gd`, `mbstring` vorhanden, 500 GB frei; Werte für Webanfragen von `api.planer.mollo.dev`: `memory_limit` 256M, `max_execution_time` 60 s (in D5 in `docs/dokumente-versuch.md` §3 und `docs/deploy-template.md` eintragen, keine Erhöhung per `ini_set` oder `set_time_limit` im Code). `composer.json`: `mpdf/mpdf` ist freigegeben; die vier TipTap-Pakete (Auftrag 17) noch nicht.
Plan: `docs/dokumentenverwaltung.md` (dritte Fassung, 2026-11-18). Maßgeblich ist der Plan; hier die Zusammenfassung. Die Abnahmekriterien je Ticket stehen im Plan §12.

## Vorab

- **Eigener Branch:** `git switch -c feature/dokumente` von `dev`. Nichts davon nach `dev` mergen und nichts auf `dev` pushen, bis Oliver den Tag `v1.0.0` gesetzt hat. Der Pull Request „Release 1.0“ (#16) bleibt unberührt. Die Dateien `docs/dokumentenverwaltung.md` und `next-work/16-...` liegen noch uncommittet im Arbeitsverzeichnis: sie gehören in den ersten Commit auf dem Branch.
- `.mcp.json` und `.claude/` nie committen, gezielt stagen.
- Nur Backend. Keine Oberfläche in diesem Auftrag (D6 bis D8, D10, D11 kommen als Auftrag 17).
- Vorbild für Model, Policy, Papierkorb, GraphQL und Tests: `Idea` (M6). Rechte: `App\Support\Rights`.

## Entscheidungen (Oliver)

Markdown als Speicherform nur für Dokumente. Standard für neue Dokumente `project_access = read`. Manager sehen auch private. Feste Arten: `procedure`, `minutes`, `analysis`, `concept`, `other`. Keine Bilder. **Versionsverlauf gehört zu M7** (`document_versions`, D1 und D9). Echtzeit ist M8 und nicht Teil dieses Auftrags, aber M7 wird so gebaut, dass M8 nur Tabellen und eine Mutation ergänzt.

**Umwandlung im Backend (2026-11-18):** Der Server wandelt Markdown ↔ TipTap um, mit den vorhandenen `App\Planer\RichText\MarkdownToTiptap` und `TiptapToMarkdown`, erweitert um das Profil „Dokument“ (Plan §5.2). Der Editor schickt und lädt TipTap-JSON (`contentJson`, Skalar `RichText`). Kein `@tiptap/markdown` im Browser. Zeigt D0, dass das nicht geht, melde es, bevor du D3 baust.

**PDF:** Im Repo ist keine PDF-Bibliothek festgelegt. D0 vergleicht `dompdf` und `mPDF`. Die Wahl trifft Oliver nach D0.

## Reihenfolge

1. **D0** Versuch ohne Produktivcode, Ergebnis `docs/dokumente-versuch.md`: Rundlauf des Profils im PHP-Konverter, passende TipTap-Erweiterungen (nur Notiz), PDF-Bibliotheken auf Hostpoint-Tauglichkeit. **Danach anhalten und melden.** Stuart und Oliver bestätigen Profil und Bibliothek.
2. **D1** Migrationen `documents`, `document_shares`, `document_versions`, Models, Factory, DemoSeeder, Papierkorb (`deletion_id`).
3. **D2** Rechte `document.create`, `document.update`, `document.manageAny`, Zugriffsdienst, `DocumentPolicy`, Grundregeln (Mitglied entfernt, archiviertes Projekt).
4. **D3** Profil „Dokument“ in Konverter und Prüfer, `spec/format-v2.md` §6.2, Fixtures. Das Profil „Beschreibung“ und `tools/planer` bleiben unverändert (Tests grün).
5. **D4** GraphQL ohne Verlauf und PDF (Typen, Queries, Mutationen, `DOCUMENT_CONFLICT`, Suche, `TrashItemType.DOCUMENT`), jedes Sichern schreibt eine Fassung. `composer schema:frontend`.
6. **D5** PDF-Export (`exportDocumentPdf`) mit der bestätigten Bibliothek.
7. **D9** Versionsverlauf Backend (`Document.versions`, `documentVersion`, `restoreDocumentVersion`).

D1 bis D5 und D9 je ein Commit. Prüfungen grün: Backend-Tests auf SQLite und `composer test:mariadb`, `lighthouse:validate-schema`, `MutationCoverageTest`, Lint.

## Abschluss

Nach D4 meldet Bob den Stand, damit Stuart die Seitenmuster für Auftrag 17 erhebt (D5 und D9 können parallel weiterlaufen). Nach D9: `docs/umsetzung.md` „Stand“ nachführen, oben `Status: erledigt (<Commits>)`, anhalten und melden.
