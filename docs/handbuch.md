# Handbuch: eine Quelle, drei Ausgaben

Konzept vom 24.11.2026, angepasst am 25.11.2026 (Bilder, Schreib-Persona). Meilenstein **1.6 «Handbuch»**. Etappe 1: Auftrag 54. Den Inhalt ab Etappe 2 schreibt eine eigene Schreib-Persona nach jedem abgeschlossenen Meilenstein.

Wie man ein Kapitel schreibt (Format, Platzhalter, Bilder, Ablauf nach einem Meilenstein): `handbuch/README.md`. Was gebaut ist, steht am Ende unter «Stand».

## Ziel

Das Handbuch führt die Funktionen des Mollo Planers einzeln auf und erklärt die Bedienung, in drei Varianten:

1. **Für Menschen:** einfache Sprache, klar nachvollziehbare Schritte.
2. **Beispielprompts:** um eine Funktion einem Agenten zu übergeben.
3. **Für Agenten:** reine HTML-Ausgabe zum direkten Lesen.

## Entscheide (Oliver, 24.11.2026)

| Frage | Entscheid |
|---|---|
| Ort für Menschen | in der App: Bereich «Hilfe» mit Suche und einem «?» auf jeder Seite. Nur für Angemeldete. |
| Sprachen | alles deutsch und englisch (Menschen, Prompts, Agenten-Ausgabe). |
| Bilder | ja, deutsch und englisch (geändert am 25.11.). Für den Anfang automatische Screenshots; jedes Bild lässt sich durch ein eigenes ersetzen. Der Text bleibt ohne Bilder vollständig verständlich; die Hilfe lässt sich mit oder ohne Bilder anzeigen. Siehe «Bilder». |
| Agenten-Ausgabe | öffentlich ohne Anmeldung, wie `/mcp`. Enthält keine Geheimnisse und keine Daten. |
| Version und Titel | 1.6 «Handbuch». Das Starter-Kit rückt ganz nach hinten (jetzt 1.8, nach 1.7 «Verwaltung und Protokoll»), weil noch einiges gebaut wird, das ins Kit gehört. |
| Reihenfolge | Das Gerüst (Etappe 1) läuft früh. |
| Wer schreibt | Bob schreibt im Gerüst die drei Musterkapitel. Danach die Schreib-Persona **Clara** (ein eigenes Agentenkonto): Nach dem Abschluss jedes Meilensteins schreibt Stuard ihr einen kurzen Auftrag, und sie schreibt dessen Funktionen ins Handbuch (25.11.). |

## Grundsatz

Die drei Varianten werden nicht dreimal geschrieben. Jede Funktion ist **ein Kapitel als Markdown-Datei im Repo**, je Sprache eine Datei (`handbuch/de/<kapitel>.md`, `handbuch/en/<kapitel>.md`). Ein Build erzeugt daraus alle Ausgaben.

## Aufbau eines Kapitels

| Abschnitt | Inhalt | Geht in |
|---|---|---|
| Kopf (Frontmatter) | `id`, Titel, Bereich, nötiges Recht, Seiten der App (für das «?»), MCP-Werkzeuge, seit Version | alle |
| Wozu | ein, zwei Sätze: was die Funktion bringt | Mensch, Agent |
| So geht's | nummerierte Schritte, ein Handgriff je Schritt | Mensch |
| Gut zu wissen | Grenzen, Sonderfälle, typische Fehler | Mensch, Agent |
| Für Agenten | welche Werkzeuge, welche Felder, was über MCP nicht geht | Agent |
| Bilder (in «So geht's») | nach dem Schritt, den sie zeigen; mit Alternativtext; nie eine Information, die nicht im Text steht | Mensch |
| Beispielprompts | zwei bis vier Prompts zum Kopieren, mit den nötigen Werkzeugen | Prompts |

Bezeichnungen der Oberfläche (Knöpfe, Menüpunkte) werden nicht abgetippt, sondern über ihren Sprachschlüssel eingesetzt (Platzhalter auf `i18n/locales/{de,en}.json`). Eine Umbenennung in der App zieht das Handbuch von selbst nach.

## Sprachregeln (für Menschen)

Kurze Sätze, aktiv, eine Aussage je Satz. Ein Handgriff je Schritt, in der Reihenfolge, in der man ihn ausführt. Zuerst wo, dann was («Öffne Verwaltung → Benutzer. Wähle …»). Fachwörter nur aus dem Glossar, beim ersten Vorkommen verlinkt. Keine Abkürzungen ohne Erklärung. Die genauen Regeln stehen in einem kurzen Leitfaden, den die Musterkapitel vorleben.

## Die drei Ausgaben

| Ausgabe | Form | Ort |
|---|---|---|
| Menschen | Bereich «Hilfe» in der App: Kapitel nach Bereich, Suche, Glossar, Schalter «Bilder anzeigen»; «?» im Seitenkopf führt zum Kapitel der aktuellen Seite | `/help` (nur angemeldet), Sprache der App |
| Prompts | Sammlung nach Bereich, jeder Prompt mit Kopier-Knopf und den nötigen Werkzeugen | in der Hilfe der App; zusätzlich in der Agenten-Ausgabe |
| Agenten | reines HTML ohne Skripte und ohne Navigation drumherum, je Kapitel eine Seite und alles auf einer Seite; dazu dieselben Kapitel als Markdown und ein Verzeichnis `llms.txt` | statisch und öffentlich, neben `/mcp`: `/manual/` (englisch) und `/manual/de/` |

Die Markdown-Fassung und `llms.txt` sind ein Zusatz zum verlangten HTML: Agenten lesen sie günstiger, und das Muster gibt es bei `/mcp` schon.

## Bilder

- **Automatisch:** Ein Skript (`npm run manual:shots`) nimmt mit Playwright jedes Bild in Deutsch und Englisch auf, gegen die Wegwerf-Datenbank der Browser-Tests, nur mit Testdaten, heller Modus, feste Fenstergrösse. Je Kapitel eine kleine Aufnahme-Datei unter `handbuch/aufnahmen/`.
- **Eigene Bilder:** Gleicher Name unter `handbuch/bilder/eigen/<sprache>/…` (oder `eigen/alle/…` für ein Bild ohne Text) hat Vorrang vor dem automatischen. Das Skript schreibt dort nie hinein.
- **Prüfliste:** Das Skript führt `handbuch/bilder/pruefen.md` mit den Aufnahmen, bei denen es Zweifel hat. Oliver ersetzt diese bei Bedarf durch eigene.
- **Anzeige:** In der Hilfe mit oder ohne Bilder (Schalter, im Browser gespeichert). Die Agenten-Ausgabe enthält keine Bilder.
- **Tests:** jedes Bild in beiden Sprachen vorhanden, mit Alternativtext, verwendet und höchstens 300 KB.

## Woher der Inhalt kommt

- **Vollständigkeit aus dem Code:** Ein Skript liest die Seiten der App (Stand 24.11.: 41), die Rechte, die Einträge der Befehlspalette und die MCP-Werkzeuge (13) und führt die Liste «Funktion → Kapitel». Ein Test schlägt an, wenn eine Seite, ein Recht oder ein Werkzeug in keinem Kapitel vorkommt, und wenn ein Kapitel etwas nennt, das es nicht gibt. Solange der Inhalt wächst, führt eine Liste «noch ohne Kapitel» die Lücken; sie darf nur kürzer werden.
- **Gleichstand der Sprachen:** Ein Test prüft, dass es jedes Kapitel in beiden Sprachen gibt, mit gleicher `id`, gleichen Abschnitten und gleicher Zahl von Schritten.
- **Schlüssel statt Abschrift:** Ein Test prüft, dass jeder verwendete Sprachschlüssel existiert.
- **Text:** Die Schreib-Persona schreibt die Kapitel aus Code, Sprachdateien, den Tasks und PRs des Meilensteins und den vorhandenen Browser-Tests, zuerst deutsch, dann englisch, und nimmt die Bilder neu auf. Vorher drei Musterkapitel (Bob, Auftrag 54), an denen Oliver Ton, Länge und Bilder festlegt.
- **Pflege:** Jeder künftige Auftrag nennt das betroffene Kapitel («Handbuch: Kapitel X»), damit die Schreib-Persona es findet. Geschrieben wird nach dem Abschluss des Meilensteins, nicht im Auftrag selbst. Die Tests (Vollständigkeit) zeigen, was fehlt.

## Kapitel (Bereiche)

Erste Schritte (Konto, Anmelden, zweiter Faktor, Profil) · Projekte · Meilensteine, ToDos und Tasks · Kanban · Meine Tasks · Kommentare · Dokumente · Ideen · Assistent · Vorlagen und Labels · Papierkorb und Archiv · Statistik · Ruhige Ansicht und Befehlspalette · Planer-Datei (Export, Import, Werkzeug) · Mit Agenten arbeiten (Connector, MCP, Zugänge) · Verwaltung (Benutzer, Agenten, Rollen, Einstellungen, Anmeldedienst). Später: Abrechnung (Auftrag 39), Protokoll (Auftrag 53).

## Etappen

1. **Gerüst (Auftrag 54):** Kapitelformat, Sprachleitfaden (mit Ablauf «Nach einem Meilenstein»), Build für alle Ausgaben, Bilder (automatisch und eigene), Tests, Hilfe in der App als Übersicht und Kapitelansicht mit Schalter für Bilder, drei Musterkapitel in beiden Sprachen.
2. **Bestehender Inhalt nachholen** (Schreib-Persona): Plan, Verwaltung, Agenten, Prompt-Sammlung; Reihenfolge nach der Liste «noch ohne Kapitel».
3. **Laufend:** nach jedem abgeschlossenen Meilenstein dessen Funktionen.
4. **Hilfe fertig:** «?» auf jeder Seite, Suche, Glossar.

## Starter-Kit

Das Kit kommt zuletzt. Das Gerüst wird trotzdem so gebaut, dass es später ohne Umbau in die Basis wandern kann: Es hängt nicht am Fachcode, und jedes Kapitel trägt im Kopf, ob es zur Basis (Konten, Rechte, Verwaltung) oder zur App (Plan) gehört.

## Offen

- Die Schritte der Kernkapitel zusätzlich im Browser-Test durchspielen (verhindert veraltete Anleitungen, kostet Laufzeit in der CI): später entscheiden.

## Stand (Etappe 1, Auftrag 54)

- Quelle: `handbuch/de/`, `handbuch/en/`, Leitfaden `handbuch/README.md`. Neben `pages`, `right` und `tools` trägt der Kopf `commands` (Einträge der Befehlspalette), damit die Vollständigkeit auch die Befehlspalette prüft.
- Build: `npm run manual` (`frontend/scripts/manual.mjs`, läuft vor `dev` und `build`), Parser der Planer-Beschreibungen (`tools/planer/core/richtext.mjs`), keine neue Abhängigkeit. Erzeugt `frontend/app/data/manual/<sprache>.json` (Hilfe) und `frontend/public/manual/` (Agenten). Die erzeugten Dateien liegen im Repo wie bei `/mcp`; ein Test hält sie gleich mit der Quelle. Bilder werden beim Build nach `frontend/public/help-images/` kopiert (nicht im Repo, Name mit Prüfsumme, ein Jahr im Cache).
- Links im Kapitel nur mit `http` oder `https` (der Parser kennt keine relativen Links).
- Bilder: `npm run manual:shots` (`frontend/playwright.manual.config.ts`, Aufnahmen in `handbuch/aufnahmen/<kapitel>.mts`), Prüfliste `handbuch/bilder/pruefen.md`.
- Vollständigkeit: `frontend/scripts/manual-inventory.mjs`, Liste `handbuch/ohne-kapitel.txt`, Tests in `frontend/tests/unit/manual.test.ts`.
- Hilfe in der App: `/help` und `/help/<kapitel>`, Eintrag im Benutzermenü und in der Befehlspalette.
