# Dokumente von Mollo Planer: Einstieg

Stand: 2026-11-13 (D1). Wer neu einsteigt, Mensch oder Agent, liest zuerst dieses Dokument.

## Was gilt (Vorrang von oben nach unten)

1. **`umsetzung.md`**: Arbeitsschritte, Reihenfolge, Meilensteine M1 bis M6, Stand. Was dort steht, wird gebaut.
2. **`planer-abgleich.md`**: Entscheidungen zu Format Planer v2, Import/Export, Labels, Vorlagen, Werten, Status, Rich Text, Webhook.
3. **`rollen-funktionen-rechte.md`**: Rechte, Funktionen, Rollen, Grundregeln.
4. **`assistent-ideen-konzept.md`**: KI-Assistent, Ideen, KI-Kontext.
5. **`../spec/format-v2.md`** und **`../spec/planer-v2.schema.json`**: das Format Planer v2 (technisch verbindlich).
6. **`schema-review.md`**: Datenmodell der App (A1 bis A7, B1 bis B3). Rechte-Angaben dort sind Standardbelegung, siehe 3.
7. **`neubewertung-2026-11-13.md`**: Bewertung des Stands, Begründung der Meilensteine.

Bei Widerspruch gilt das weiter oben genannte Dokument. Widersprüche nicht still auflösen, sondern nachfragen.

## Arbeitsweise

- Code auf Englisch, Oberfläche und Mails DE/EN über die Übersetzungsdateien, technische Werte klein und englisch.
- Rechte prüft der Code nur über `App\Support\Rights`, nie über Rollennamen (außer den Grundregeln, die bis R1 Stufe 3b umgestellt werden).
- Prüfungen vor jedem Commit: siehe `../CLAUDE.md` bzw. `../AGENTS.md` (Backend, Frontend, `tools/planer`).
- Planungsdokumente entstehen in der Planungssitzung und liegen hier. Die Umsetzung führt den Stand in `umsetzung.md` nach.

## Weiterhin gültig (Fachthemen)

- `frontend-webawesome-plan.md`, `frontend-plan.md`: Oberfläche (Web Awesome, Seiten)
- `registrierung-plan.md`: Selbstregistrierung und Freigabe
- `system-plan.md`: Status und Datenbank-Doku
- `deploy-template.md`: Deployment bei Hostpoint
- `desktop-plan.md`: geplante Desktop-App (Tauri)
- `claude-connector.md`: heutiger Connector (bis I3)
- `beispiel-planer.md`: Beispiel der Markdown-Ansicht von Planer v2
- `entwicklung.md`: Seed des Projekts „Mollo Planer · Entwicklung“ (altes v1-Format, wird in I4 umgestellt)
- `claude-desktop-kontext.md`: Kurzfassung für ein Claude-Desktop-Projekt

## Historisch (nur zum Nachlesen)

`backend-plan.md`, `graphql-kontext.md`, `rollen-plan.md`, `refactoring-vor-umsetzung.md`, `umsetzung-planer-v2.md`, `planer-vs-mollo-version.md`, `connector-erweiterung-plan.md`; teilweise `planer-datei-ssot.md` und `labels-plan.md`. Jedes trägt oben einen Hinweis.
