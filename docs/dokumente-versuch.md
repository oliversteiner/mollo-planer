# Dokumentenverwaltung: Versuch D0

Stand: 2026-11-18 (Bob). Zu `docs/dokumentenverwaltung.md` §5.2, §7.2 und Ticket D0 (`next-work/16-dokumente-backend.md`).

Der Versuch hat **keinen Produktivcode** und keine Abhängigkeit im Repo geändert. Alles lief auf Kopien im Scratchpad: der PHP-Konverter als Kopie mit dem Profil „Dokument“, die TipTap-Erweiterungen und beide PDF-Bibliotheken in je einem Wegwerf-Projekt. Die Skripte liegen nicht im Repo. Die Fälle stehen unten, damit D3 sie als Fixtures (`spec/fixtures/richtext/`) übernehmen kann.

## Kurzfassung

| Frage | Ergebnis |
|---|---|
| Lässt sich der PHP-Konverter um das Profil „Dokument“ erweitern? | **Ja.** Umwandlung im Backend (Entscheidung 2026-11-18) bleibt. Die Erweiterung ist klein (je Richtung etwa 100 Zeilen). Kein Rückfall auf Umwandlung im Browser nötig. |
| Rundlauf JSON → Markdown → JSON | 51 von 57 Fällen unverändert. Die 6 anderen sind unten aufgeführt; **keiner ist ein Fehler des Konverters**, drei sind Elemente, die aus dem Profil genommen werden (Plan §5.2 „Maßstab“), eines ist eine einmalige Normalisierung, eines eine bekannte Grenze von CommonMark. |
| Passen die TipTap-Erweiterungen zum Knotenformat? | **Ja**, mit vier Paketen mehr (Tabelle, Codeblock, Trennlinie, Durchgestrichen). 55 von 56 Dokumenten bestehen die Schemaprüfung von TipTap 3.34. Das JSON, das der Editor zurückschickt (mit allen Standard-Attributen), ergibt in 55 von 55 Fällen **dasselbe Markdown**. |
| PDF-Bibliothek | **Empfehlung mPDF** (Begründung unten). `dompdf` ist die Ausweichlösung. Entscheiden: Oliver. |

## 1. Konverter: Profil „Dokument“

### Was ich ergänzt habe (Kopie, nicht im Repo)

Lesen (`MarkdownToTiptap`): Überschrift 1 bis 4; Codeblock mit Sprache (`FencedCode`, auch eingerückter Code); Trennlinie; Tabelle (`tableRow`, `tableHeader` in der Kopfzeile, sonst `tableCell`, je Zelle ein Absatz); Aufgabenliste (`TaskListExtension` von league/commonmark, bereits installiert, `taskList` und `taskItem` mit `checked`); durchgestrichen als Mark `strike`. Eine Liste mit Punkten und Aufgaben wird in **je eine Liste pro Abschnitt** geteilt, weil TipTap sie nicht mischen kann.

Schreiben (`TiptapToMarkdown`): `#` bis `####`; Zaun aus Backticks, der um eins länger ist als der längste Backtick-Lauf im Code, dahinter die Sprache; `---`; Tabelle mit `| --- |` (senkrechter Strich im Text als `\|`); `- [ ]` und `- [x]`, Fortsetzungen zwei Zeichen eingerückt; `~~` als Begrenzer von `strike` (in Fett und Kursiv eingeschlossen wie bisher). Folgt eine Aufgabenliste auf eine Aufzählung (oder umgekehrt), schreibt die zweite `*` statt `-`. Sonst läse CommonMark beide als eine Liste.

Das Profil „Beschreibung“ und `tools/planer` bleiben unberührt (die Kopie lebt außerhalb des Repos).

### Rundlauf JSON → Markdown → JSON (57 Fälle)

**Stabil (51):** Überschrift 1 bis 4 (auch mit Fett); Codeblock mit und ohne Sprache, mit Leerzeile, mit Backticks und eigenem Zaun, mit `#`, `*` und Tabulator, mit Einzug, leer, in Aufzählung, im Zitat; Trennlinie zwischen Absätzen, am Ende, in Aufzählung; durchgestrichen allein, mit fett oder kursiv, mit `~` im Text, im Link; Tabelle einfach, mit Fett, Code und Link, mit `|` im Text und im Code, mit leerer Zelle, mit einer Spalte, nur mit Kopfzeile, in Aufzählung, im Zitat, zwischen Absätzen, mit Umlauten und `€ & < «»`; Aufgabenliste einfach, mit Formatierung, mit zwei Absätzen, mit Unterliste, mit Unteraufgaben, in Aufzählung und in nummerierter Liste, mit Codeblock; Aufzählung und Aufgabenliste hintereinander (beide Reihenfolgen) sowie nummerierte Liste vor Aufgabenliste; verschachtelte Listen; Text mit `#`, `---`, `|`, ```` ``` ````, `<br>` am Zeilenanfang (maskiert).

**Nicht stabil (6):**

| Fall | Was passiert | Folge für das Profil |
|---|---|---|
| Tabelle **ohne Kopfzeile** (erste Zeile `tableCell`) | Markdown hat immer eine Kopfzeile: beim Lesen wird die erste Zeile `tableHeader`. Danach stabil. | **Einmalige Normalisierung.** Der Server schreibt die erste Zeile immer als Kopfzeile. Der Editor legt Tabellen mit Kopfzeile an und bietet „Kopfzeile aus“ nicht an. |
| Tabellenzelle mit **zwei Absätzen** | Absätze werden mit einem Leerzeichen verbunden, Struktur geht verloren. | **Aus dem Profil.** Eine Zelle ist ein Absatz. |
| Tabellenzelle mit **hartem Umbruch** | wie oben | **Aus dem Profil.** Kein `<br>` (HTML ist verboten). |
| Tabellenzelle mit **Liste** | Inhalt geht verloren. | **Aus dem Profil.** |
| Tabelle mit **colspan** | Zusammengeführte Zelle wird zu zwei Zellen. | **Aus dem Profil.** Der Editor bietet „Zellen verbinden“ nicht an. |
| Durchgestrichen **kursiv direkt am Wort** (`y*~~z~~*`) | Nicht lesbar wie geschrieben. | **Grenze von CommonMark** (wie `a**.**b` in `spec/format-v2.md` §6.1: ein Begrenzer vor Satzzeichen, direkt an einem Wort). Mit Leerzeichen davor stabil. In §6.2 als Grenze beschreiben, nicht umgehen. |

Mit „Aus dem Profil“ meine ich: Das Backend lehnt solche Knoten beim Sichern **mit Fehler** ab (Plan §5.2: streng, nie still verlieren), und der Editor erzeugt sie gar nicht erst.

### Markdown zuerst (17 Fälle, lesen → schreiben → lesen)

Alle 17 sind nach dem ersten Schreiben **stabil**. Folgendes wird dabei normalisiert (und läge im Versionsvergleich nur einmal als Änderung vor): Überschriften mit Unterstreichung → `#`; eingerückter Code und `~~~` → Zaun aus Backticks (Info-String nach dem ersten Wort abgeschnitten); Tabellen ohne äußere Striche, mit Ausrichtung (`:---:`, **Ausrichtung geht verloren**) und mit ungleich langen Zeilen (zu kurze werden aufgefüllt, überzählige Zellen entfallen) → einheitlich; `[X]` → `[x]`; `*`-Aufgaben → `-`; nummerierte Aufgabenliste → Aufgabenliste; Aufgaben und Punkte gemischt → getrennte Listen; `***`, `___`, `- - -` → `---`; `~~**a**~~` → `**~~a~~**`; lose Aufgabenliste → dicht; zwei Leerzeichen am Zeilenende → `\`. Ein vollständiges Protokoll (Überschriften, Tabelle, Aufgaben, Trennlinie, Codeblock, Zitat) läuft ebenfalls stabil.

**Streng abgelehnt** (Fehler wie bei der Planer-Datei): HTML-Block, Inline-HTML, HTML-Kommentar, Bild, Überschrift ab Ebene 5, nicht geschlossenes Fett, Link mit anderem Schema (`javascript:`), `<br>` in einer Zelle. **Nicht abgelehnt, bleiben Text** (wie heute bei der Beschreibung, bewusst): Fußnoten, `$formeln$`, Definitionslisten. Bare URLs bleiben Text, `<https://…>` wird ein Link (Hinweis wie in §9.5).

**Ein Stolperstein, kein Fehler:** Eine Tabelle, auf die ohne Leerzeile ein Absatz folgt, nimmt diesen Absatz als weitere Zeile auf. So verhält sich Markdown (GFM) überall. Der Editor schreibt immer eine Leerzeile; nur in Dateien und über MCP ist es möglich.

### Was D3 im Produktivcode ändern muss

1. `RichTextDocument` bekommt ein zweites, **eigenes Profil** (z. B. `DocumentProfile` oder ein Parameter), nicht die Liste des Profils „Beschreibung“ erweitern. Zusätzliche Knoten: `heading` 1 bis 4, `codeBlock` (nur Text, Attribut `language` Text oder null, Zeichen `A–Z a–z 0–9 _ + # . -`), `horizontalRule`, `table`/`tableRow`/`tableHeader`/`tableCell`, `taskList`/`taskItem`; Mark `strike`.
2. Zelle: **genau ein** `paragraph`, nur `text`; kein `hardBreak`, kein `colspan`/`rowspan` über 1, kein `align` außer `null`. Standard-Attribute des Editors (`colspan: 1`, `rowspan: 1`, `colwidth: null`, `align: null`, `checked: false`) beim Prüfen **annehmen und entfernen**.
3. `listItem` und `taskItem` müssen mit einem `paragraph` beginnen (die Schemaprüfung von TipTap lehnt „Überschrift als erstes Kind eines Listenpunkts“ ab, der PHP-Leser nahm sie bisher an). Im Profil „Dokument“ also Fehler. Im Profil „Beschreibung“ bleibt es, wie es ist.
4. `MarkdownToTiptap` und `TiptapToMarkdown` bekommen den Parameter „Profil“ (Beschreibung unverändert als Standard); die Fixtures bekommen `*.doc.in.md`, `*.doc.out.md`, `*.doc.tiptap.json`, `*.doc.error.md` nach dem Muster von §6.1. Die Fälle aus diesem Papier sind die Grundlage.
5. Der Versuch ließ das Längenlimit von `RichTextDocument` weg. D3 setzt die 200 000 Zeichen aus Plan §5.3 (nach der Umwandlung) und die Knotengrenzen für das Profil neu.

## 2. TipTap-Erweiterungen (Frontend, Entwurf für Auftrag 17)

Geprüft mit `@tiptap/core` und `@tiptap/pm` 3.34.0 in einem Wegwerf-Projekt. Im Repo stehen heute 3.33; das Frontend nimmt `^3.33.0`.

| Element | Paket | Schon im Repo? | Knoten/Mark im JSON |
|---|---|---|---|
| Überschrift 1 bis 4 | `@tiptap/extension-heading` (`levels: [1, 2, 3, 4]`) | ja (heute 2 und 3) | `heading`, `attrs.level` |
| Codeblock | `@tiptap/extension-code-block` | **neu** | `codeBlock`, `attrs.language` (Text oder `null`), Inhalt: ein `text` ohne Marks |
| Trennlinie | `@tiptap/extension-horizontal-rule` | **neu** | `horizontalRule` |
| Durchgestrichen | `@tiptap/extension-strike` | **neu** | Mark `strike` |
| Tabelle | `@tiptap/extension-table` (`Table`, `TableRow`, `TableHeader`, `TableCell`) | **neu** | `table` > `tableRow` > `tableHeader`/`tableCell` > `paragraph` |
| Aufgabenliste | `@tiptap/extension-list` (`TaskList`, `TaskItem`) | **ja**, bereits installiert | `taskList` > `taskItem` (`attrs.checked`) |

**Knotennamen und Attribute stimmen mit dem PHP-Konverter überein**, ohne Zuordnung. Unterschiede, die D3 und Auftrag 17 kennen müssen:

- Der Editor schickt **Standard-Attribute** mit: Zellen `colspan`, `rowspan`, `colwidth`, (ab 3.34) `align`; Codeblock `language`; Aufgabe `checked`. Der Server entfernt sie beim Prüfen (Punkt 2 oben). Ausgabe aus dem Server hat sie nicht, der Editor ergänzt sie beim Laden. Das ist unkritisch: Maßstab ist das Markdown (55 von 55 gleich).
- `ListItem` verlangt `paragraph block*`: ein Listenpunkt beginnt mit einem Absatz.
- Konfiguration, die das Profil erzwingt: Tabelle ohne `resizable`, Zellen auf **einen Absatz** (`TableCell.extend({ content: 'paragraph' })`, ebenso `TableHeader`), kein „Zellen verbinden/teilen“ im Menü, kein „Kopfzeile ausschalten“, Aufgabenliste mit `nested: true`, Codeblock mit freier Sprache (Textfeld oder kleine Auswahl, nur Zeichen des Profils).
- Das Paket `@tiptap/extension-table` bringt eigene Markdown-Funktionen mit (`renderTableToMarkdown`). Sie werden **nicht** genutzt: Es gibt nur den PHP-Konverter als Quelle der Wahrheit.

Neue Pakete wären vier (Tabelle, Codeblock, Trennlinie, Durchgestrichen), je `^3.33.0` wie die übrigen. Das ist eine Änderung an `frontend/package.json`: **braucht deine Freigabe** (kommt erst mit Auftrag 17, nicht in diesem Auftrag).

## 3. PDF: `dompdf` gegen `mPDF`

Beispieldokument: Überschriften, Umlaute und `€ ½ « » – — …`, ein sehr langes Wort, Listen, Aufgaben, **Tabelle mit 70 Zeilen über drei bis vier Seiten**, Codeblock mit langer Zeile, Zitat, Trennlinie, Link. HTML aus `league/commonmark` (`html_input: escape`, `allow_unsafe_links: false`, Tabelle, Durchgestrichen, Aufgabenliste). Beide auf PHP 8.5.12 (lokal). Ein Lauf auf **Hostpoint** war mir nicht möglich: ich habe keinen Serverzugang. Das bleibt offen (siehe unten).

| | dompdf 3.1.6 | mPDF 8.3.1 |
|---|---|---|
| PHP-Anforderung | `^7.1 \|\| ^8.0`; `ext-dom`, `ext-mbstring` | `~8.5.0` ausdrücklich (**geprüft 2026-11-19 an Packagist: 8.3.1 verlangt wirklich nur 8.5.x, auch `>=8.6` ist ausgeschlossen**); `ext-mbstring`, **`ext-gd`** |
| Größe in `vendor/` | etwa 10 MB | **etwa 93 MB** (Schriften) |
| Beispieldokument (4 bzw. 3 Seiten) | 0,21 s, Spitze 38 MB | 0,23 s, Spitze 50 MB (ein Prozess, beide hintereinander, daher grob) |
| **Dokument mit 200 000 Zeichen** (Maximum), `memory_limit` 128 MB | 3,3 s, **Spitze 100 MB**, 137 KB | 2,1 s, **Spitze 26 MB**, 170 KB |
| Umlaute, Sonderzeichen, Zeichen `«»€½–—` | ja (DejaVu Sans) | ja (DejaVu Sans) |
| Tabelle über mehrere Seiten, **Kopfzeile wiederholt** | ja | ja |
| Durchgestrichen, Aufgaben-Kästchen, Codeblock mit Umbruch, Zitat, Link | ja | ja |
| **Kopf- und Fußzeile, Seitenzahl „Seite 1 von 3“** | Fußzeile über `page_text` ja, **Kopfzeile auf jeder Seite nicht eingebaut** (nur über ein festes Element, nicht geprüft) | **eingebaut** (`SetHTMLHeader`, `SetHTMLFooter`, `{PAGENO}`, `{nbpg}`) |
| Seitenumfang bei gleicher Schrift | 4 Seiten (größere Zeilenabstände in Tabellen) | 3 Seiten |
| Fremdinhalt beim Rendern | `isRemoteEnabled` standardmäßig aus (im Versuch aus) | Bilder gibt es im Profil nicht; Fremdzugriffe nicht nötig |

**Empfehlung: mPDF.** Gründe: (1) Kopf- und Fußzeile mit Titel, Projekt, Owner, Revision, Stand und Seitenzahl, wie Plan §7.2 sie verlangt, sind eingebaut; (2) beim größten erlaubten Dokument bleibt es bei 26 MB statt 100 MB, bei dem für Hostpoint üblichen `memory_limit` von 128 MB ist `dompdf` damit nah an der Grenze; (3) PHP 8.5 steht ausdrücklich in den Anforderungen. Nachteile: 93 MB mehr in `vendor/` (das Deploy-Archiv wird größer, `composer install` auf dem Server dauert länger) und die zusätzliche Erweiterung `ext-gd` (im Projekt wegen Avatarbildern ohnehin nötig: `intervention/image` mit Treiber GD).

**Entscheid und Server (2026-11-19):** mPDF (Oliver, trotz `~8.5.0`: Ein späteres PHP 8.6 braucht dann ein mPDF-Update oder `dompdf`). Gebaut in D5 (`App\Services\DocumentPdf`, Vorlagen `resources/views/documents/pdf*.blade.php`). Oliver hat Hostpoint geprüft: PHP 8.5, `dom`, `gd`, `mbstring` vorhanden, Kommandozeile `memory_limit` 256M, 500 GB Platz. Festgelegt für Webanfragen von `api.planer.mollo.dev`: **`memory_limit` 256M** (das größte Dokument mit 200 000 Zeichen braucht mit mPDF rund 26 MB, Reserve für Import und Export der Planer-Datei und Avatare) und **`max_execution_time` 60 s** (das größte PDF braucht rund 2 s). Der Code erhöht beides **nicht** selbst (kein `ini_set`, kein `set_time_limit`); es gelten die Werte aus dem Panel.

**Offen, nur Oliver kann es prüfen:** `php -m` auf Hostpoint zeigt `gd`, `mbstring`, `dom`; die Speicher- und Laufzeitgrenzen der Hostpoint-PHP-Umgebung (`memory_limit`, `max_execution_time`). Der Test auf dem Server entfällt, solange kein Probe-Deploy vorgesehen ist. Eine Rückfalloption bleibt `dompdf` (kleiner, gleiche HTML-Vorlage; nur die Kopfzeile müsste anders gelöst werden).

## 4. Was ich brauche

1. **Profil bestätigen** (Stuart/Oliver): Tabellenzelle = ein Absatz, kein Zellenverbinden, keine Ausrichtung in Version 1, Aufgabenliste und Aufzählung nie gemischt, Kopfzeile Pflicht. Ist ein Punkt anders gewünscht, melde es, bevor ich D3 baue.
2. **PDF-Bibliothek wählen** (Oliver): Empfehlung mPDF, Ausweichlösung dompdf.
3. Hinweis für Auftrag 17: vier neue TipTap-Pakete (Freigabe nötig, wenn es so weit ist).

Danach geht es mit D1 weiter (Datenbank und Models, D1 und D2 sind von der Entscheidung unabhängig).
