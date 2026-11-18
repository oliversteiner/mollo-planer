# 16 · Dokumentenverwaltung, Backend (D0 bis D5, D9)

Status: offen
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
