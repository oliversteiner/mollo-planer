# Messung: Helfer gegenüber dem vollständigen Neuschreiben

Stand: 2026-11-12. Gehört zu P4 (Helfer für Agenten) aus `mollo-planer/docs/umsetzung-planer-v2.md`. Reproduzierbar mit `node scripts/messung-helfer.mjs`.

## Methode

- Datei: `fixtures/beispiel-planer.planer.json` (5177 Zeichen).
- Jedes Szenario läuft auf einer Kopie der Datei mit dem echten Helfer.
- **Vorher:** Der Agent liest die ganze Datei und schreibt sie komplett neu. Gerechnet werden zwei Dateilängen.
- **Nachher:** Der Agent schreibt den Befehl (einschließlich stdin) und liest die Ausgabe.
- Gemessen werden **Zeichen**, nicht Tokens. Tokens ≈ Zeichen / 4 ist eine grobe Näherung. Es läuft kein Tokenizer.

## Ergebnis

| Szenario | vorher (Zeichen) | nachher (Zeichen) | Einsparung |
|---|---:|---:|---:|
| Status setzen (`status k-004 review`) | 10354 | 73 | 99 % |
| Task anlegen mit Flags | 10354 | 99 | 99 % |
| Task anlegen mit Beschreibung und userStory (`--json`) | 10354 | 298 | 97 % |
| Task verschieben | 10354 | 77 | 99 % |
| Überblick (`list` statt Datei lesen) | 5177 | 522 | 90 % |

## Einschränkungen

- Das „vorher“ ist ein Modell. Es unterstellt, dass der Agent die ganze Datei neu schreibt. Das ist die Obergrenze. Ein Agent mit gezieltem Editieren spart weniger.
- Gemessen wird nur die Länge, nicht die Qualität der Antwort. Die Helfer prüfen jede Änderung, das Neuschreiben nicht.
- Der Test an einer einzigen Datei mit 13 Elementen (2 Milestones, 4 ToDos, 7 Tasks) sagt wenig über sehr große Pläne.
- Der Überblick mit `list` gibt Titel und IDs, keine Beschreibungen. Wer Beschreibungen braucht, nutzt `show`.
