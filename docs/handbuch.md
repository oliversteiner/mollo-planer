# Handbuch: eine Quelle, drei Ausgaben

Konzept, Stuard P mit Oliver, 24.11.2026. Meilenstein **1.6 «Handbuch»** (`m-116`). Etappe 1: Auftrag 54 (`next-work/54-handbuch-geruest.md`, ToDo `t-472`). Etappen 2 bis 4 sind noch ohne Auftrag (`t-473`).

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
| Bildschirmfotos | keine. Nur Text; die Schritte nennen die Knöpfe beim Namen. |
| Agenten-Ausgabe | öffentlich ohne Anmeldung, wie `/mcp`. Enthält keine Geheimnisse und keine Daten. |
| Version und Titel | 1.6 «Handbuch». Das Starter-Kit rückt ganz nach hinten (jetzt 1.8, nach 1.7 «Verwaltung und Protokoll»), weil noch einiges gebaut wird, das ins Kit gehört. |
| Reihenfolge | Das Gerüst (Etappe 1) läuft früh, damit neue Aufträge ihr Kapitel gleich mitliefern. |

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
| Beispielprompts | zwei bis vier Prompts zum Kopieren, mit den nötigen Werkzeugen | Prompts |

Bezeichnungen der Oberfläche (Knöpfe, Menüpunkte) werden nicht abgetippt, sondern über ihren Sprachschlüssel eingesetzt (Platzhalter auf `i18n/locales/{de,en}.json`). Eine Umbenennung in der App zieht das Handbuch von selbst nach.

## Sprachregeln (für Menschen)

Kurze Sätze, aktiv, eine Aussage je Satz. Ein Handgriff je Schritt, in der Reihenfolge, in der man ihn ausführt. Zuerst wo, dann was («Öffne Verwaltung → Benutzer. Wähle …»). Fachwörter nur aus dem Glossar, beim ersten Vorkommen verlinkt. Keine Abkürzungen ohne Erklärung. Die genauen Regeln stehen in einem kurzen Leitfaden, den die Musterkapitel vorleben.

## Die drei Ausgaben

| Ausgabe | Form | Ort |
|---|---|---|
| Menschen | Bereich «Hilfe» in der App: Kapitel nach Bereich, Suche, Glossar; «?» im Seitenkopf führt zum Kapitel der aktuellen Seite | `/help` (nur angemeldet), Sprache der App |
| Prompts | Sammlung nach Bereich, jeder Prompt mit Kopier-Knopf und den nötigen Werkzeugen | in der Hilfe der App; zusätzlich in der Agenten-Ausgabe |
| Agenten | reines HTML ohne Skripte und ohne Navigation drumherum, je Kapitel eine Seite und alles auf einer Seite; dazu dieselben Kapitel als Markdown und ein Verzeichnis `llms.txt` | statisch und öffentlich, neben `/mcp`: `/manual/` (englisch) und `/manual/de/` |

Die Markdown-Fassung und `llms.txt` sind ein Zusatz zum verlangten HTML: Agenten lesen sie günstiger, und das Muster gibt es bei `/mcp` schon.

## Woher der Inhalt kommt

- **Vollständigkeit aus dem Code:** Ein Skript liest die Seiten der App (Stand 24.11.: 41), die Rechte, die Einträge der Befehlspalette und die MCP-Werkzeuge (13) und führt die Liste «Funktion → Kapitel». Ein Test schlägt an, wenn eine Seite, ein Recht oder ein Werkzeug in keinem Kapitel vorkommt, und wenn ein Kapitel etwas nennt, das es nicht gibt. Solange der Inhalt wächst, führt eine Liste «noch ohne Kapitel» die Lücken; sie darf nur kürzer werden.
- **Gleichstand der Sprachen:** Ein Test prüft, dass es jedes Kapitel in beiden Sprachen gibt, mit gleicher `id`, gleichen Abschnitten und gleicher Zahl von Schritten.
- **Schlüssel statt Abschrift:** Ein Test prüft, dass jeder verwendete Sprachschlüssel existiert.
- **Text:** Ein Agent schreibt die Kapitel aus Code, Sprachdateien und den vorhandenen Browser-Tests, zuerst deutsch, dann englisch. Vorher drei Musterkapitel, an denen Oliver Ton und Länge festlegt.
- **Pflege:** Jeder künftige Auftrag nennt das betroffene Kapitel («Handbuch: Kapitel X anpassen»); die Abnahme prüft es.

## Kapitel (Bereiche)

Erste Schritte (Konto, Anmelden, zweiter Faktor, Profil) · Projekte · Meilensteine, ToDos und Tasks · Kanban · Meine Tasks · Kommentare · Dokumente · Ideen · Assistent · Vorlagen und Labels · Papierkorb und Archiv · Statistik · Ruhige Ansicht und Befehlspalette · Planer-Datei (Export, Import, Werkzeug) · Mit Agenten arbeiten (Connector, MCP, Zugänge) · Verwaltung (Benutzer, Agenten, Rollen, Einstellungen, Anmeldedienst). Später: Abrechnung (Auftrag 39), Protokoll (Auftrag 53).

## Etappen

1. **Gerüst (Auftrag 54):** Kapitelformat, Sprachleitfaden, Build für alle Ausgaben, Tests (Vollständigkeit, Gleichstand, Schlüssel), Hilfe in der App als Übersicht und Kapitelansicht, drei Musterkapitel in beiden Sprachen.
2. **Inhalt Plan:** Projekte bis Statistik.
3. **Inhalt Verwaltung und Agenten**, Prompt-Sammlung.
4. **Hilfe in der App fertig:** «?» auf jeder Seite, Suche, Glossar.

## Starter-Kit

Das Kit kommt zuletzt. Das Gerüst wird trotzdem so gebaut, dass es später ohne Umbau in die Basis wandern kann: Es hängt nicht am Fachcode, und jedes Kapitel trägt im Kopf, ob es zur Basis (Konten, Rechte, Verwaltung) oder zur App (Plan) gehört.

## Offen

- Wer die Kapitel ab Etappe 2 schreibt: Bob oder eine eigene Schreib-Persona. Entscheid nach der Abnahme der Musterkapitel.
- Die Schritte der Kernkapitel zusätzlich im Browser-Test durchspielen (verhindert veraltete Anleitungen, kostet Laufzeit in der CI): später entscheiden.
