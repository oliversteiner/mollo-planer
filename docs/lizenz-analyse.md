# Lizenz-Analyse: Ersatz für mPDF, Lizenzen der npm-Pakete

Auftrag 81 A (`k-2115`), Bob, 27.11.2026. Ziel: Der Planer soll unter **AGPL-3.0-or-later** stehen. Im Weg steht `mpdf/mpdf` (GPL-2.0-only). Diese Analyse vergleicht zwei Ersatzkandidaten an denselben Ausgaben und listet die Lizenzen der Pakete. **Wir sind keine Juristen:** Wo eine Einschätzung zur Verträglichkeit von Lizenzen steht, ist sie als gängige Auslegung gemeint und unter «Offene Fragen» zur Bestätigung aufgeführt.

## Empfehlung

**dompdf + FPDI** (dompdf/dompdf LGPL-2.1, Abhängigkeiten LGPL-2.1-or-later, LGPL-3.0-or-later und MIT; FPDI MIT).

* dompdf setzt die heutigen Blade-Vorlagen (HTML und CSS) fast unverändert um: Tabellen mit wiederholtem Kopf auf jeder Seite, Listen mit Häkchen, Zitate, Code-Blöcke, Bilder (data-URI), Umlaute, «», ’, €.
* Das Briefpapier legt FPDI unter jede Seite, wie heute: dompdf erzeugt die Briefseiten ohne Hintergrund, FPDI legt Seite 1 bzw. 2 des Briefpapiers darunter und hängt den QR-Zahlteil an (dieselbe FPDF-Seite wie heute).
* Seitenzahlen «Seite x von y» und das Wasserzeichen «Entwurf» zeichnet dompdf selbst auf jede Seite (Canvas), ohne zweite Bibliothek.
* Laufzeit und Speicher liegen weit unter den Grenzen des Hosters (Tabelle unten).

**TCPDF** würde ebenfalls gehen, braucht aber eigene, auf TCPDF zugeschnittene Vorlagen: Es versteht weder `@page` noch `position: absolute`, wiederholt den Tabellenkopf nicht, verteilt die Spaltenbreiten ungünstig, zeigt die Häkchen von Aufgabenlisten nicht und setzt Beträge in eigene Zeilen. Dazu ist TCPDF seit Version 7 nur noch eine Hülle um `tc-lib-pdf` (13 Pakete, fast wöchentliche Korrekturen); FPDI unterstützt offiziell nur TCPDF 6, das im Wartungsmodus ist. Nicht empfohlen.

## Bestand

* mPDF steckt in `App\Core\Pdf\PdfFrame` (Einstellungen, Briefpapier über `SetDocTemplate`) und `LetterheadUpload::check()` (`setSourceFile`, `importPage`, `getTemplateSize`).
* **Die Aufrufer rufen mPDF auch direkt auf:** `InvoicePdf` (`SetTitle`, `SetWatermarkText`, `SetHTMLFooter` mit `{PAGENO}`/`{nbpg}`, `WriteHTML`, `AddPageByArray`, `SetSourceFile`/`useTemplate` für den Zahlteil, `OutputBinaryData`) und `DocumentPdf` (`SetHTMLHeader`, `SetHTMLFooter`, `WriteHTML`). «Nur `App\Core\Pdf` ändert» geht darum nur, wenn `App\Core\Pdf` eine eigene kleine Schnittstelle bekommt (siehe Aufwand B); die öffentlichen Methoden `render()`/`renderSheet()` der Aufrufer bleiben gleich.
* Schriften (`PdfFonts`): `dejavusans`, `dejavusanscondensed`, `dejavuserif`, `freesans`, `freeserif`, alle von mPDF mitgeliefert.
* `setasign/fpdi` kommt heute **nur über mPDF** ins Projekt, obwohl `InvoicePdf` es direkt nutzt (`StreamReader`). Es muss in B als direkte Abhängigkeit in `composer.json`.

## Lizenzen der Kandidaten (aus `composer licenses --no-dev`)

| Paket | Version | Lizenz |
|---|---|---|
| `dompdf/dompdf` | 3.1.6 | LGPL-2.1 |
| `dompdf/php-font-lib` | 1.0.2 | LGPL-2.1-or-later |
| `dompdf/php-svg-lib` | 1.0.2 | LGPL-3.0-or-later |
| `masterminds/html5` | 2.12.0 | MIT |
| `sabberworm/php-css-parser` | 9.5.0 | MIT |
| `thecodingmachine/safe` | 3.4.0 | MIT |
| `tecnickcom/tcpdf` | 6.11.4 (7.0.24: nur Hülle um `tc-lib-pdf`) | LGPL-3.0-or-later |
| `tecnickcom/tc-lib-*` (13 Pakete, nur mit TCPDF 7) | 8.85.1 u. a. | LGPL-3.0-or-later |
| `setasign/fpdi` | 2.6.10 | MIT |
| `fpdf/fpdf` | 1.90.0 | MIT |
| zum Vergleich: `mpdf/mpdf` | 8.3.1 | **GPL-2.0-only** |

Gängige Auslegung: LGPL-2.1 (Abschnitt 3 erlaubt den Wechsel auf GPL-2.0 «oder eine spätere Version») und LGPL-3.0-or-later lassen sich mit AGPL-3.0 kombinieren; Apache-2.0 ist mit (A)GPL-3.0 verträglich, nicht aber mit GPL-2.0-only. Ohne mPDF bleibt im Backend sonst nur `nette/schema` und `nette/utils` mit «BSD-3-Clause, GPL-2.0-only, GPL-3.0-only» (zur Wahl, BSD-3-Clause genügt).

## Vergleich an denselben Ausgaben

Erzeugt mit erfundenen Daten (Musterrechnung mit 28 Positionen in 3 Projekten, lange Titel mit Umlauten; erfundenes Briefpapier mit zwei Seiten; ein Markdown-Dokument mit Überschriften, Listen, Aufgabenliste, Zitat, Code, Tabelle mit 45 Zeilen über eine Seite, Bild). Bilder nebeneinander (mPDF | dompdf | TCPDF) und alle PDFs: `next-work/abnahme/81/`, die Skripte in `next-work/abnahme/81/skripte/`.

| Merkmal | mPDF (heute) | dompdf + FPDI | TCPDF + FPDI |
|---|---|---|---|
| Briefpapier S. 1 und Folgeseiten | ja | ja (FPDI legt es darunter) | ja (in `Header()`) |
| Satzspiegel, Seite 1 tiefer (`@page :first`) | ja | ja | nein, von Hand (`SetY`) |
| Adressfeld im Fenster (`position: absolute`) | ja | ja, Werte um die Ränder von Seite 1 verschieben | nein, von Hand (`writeHTMLCell`), Zeilen zu weit |
| Tabelle: Kopf auf jeder Seite | ja | ja | nein |
| Tabelle: rechtsbündig aus Markdown (`align`) | ja | nein, eine CSS-Regel nötig | ja |
| Zeilenhöhe, Abstände | Vorlage | etwas grösser (CSS wird genauer umgesetzt), im CSS anzupassen; Rechnung 5 statt 4 Seiten | ungleichmässig |
| Aufgabenliste (Häkchen) | ja | ja | fehlt |
| Code im Text (Schrift Mono) | ja | ja | nein |
| Bild (data-URI) | ja | ja | ja, grösser skaliert |
| Fusszeile, «Seite x von y» | ja | ja (Canvas) | ja |
| Kopfzeile Dokument | ja | ja (`position: fixed`) | ja |
| Wasserzeichen «Entwurf» | ja, **auch auf dem QR-Zahlteil** | ja, nur Briefseiten | ja, nur Briefseiten |
| Umlaute, «», ’, –, € | ja | ja | ja |
| Schriften aus `PdfFonts` | alle 5 | DejaVu Sans, Serif, Mono; **ohne** Condensed, FreeSans, FreeSerif | alle 5 |

**Laufzeit und Speicher** (Mac mini, PHP 8.5, `memory_limit` 256 MB; Spitze über dem Stand vor dem Aufruf):

| Ausgabe | mPDF | dompdf + FPDI | TCPDF + FPDI |
|---|---|---|---|
| Rechnung 28 Positionen mit Briefpapier | 0,26 s, 22 MB | 0,32 s, 33 MB | 0,15 s, 13 MB |
| Dokument mit Briefpapier | 0,12 s, 7 MB | 0,20 s, 23 MB | 0,09 s, 11 MB |
| Rechnung **300 Positionen** mit Briefpapier | 0,91 s, 38 MB | 1,31 s, 56 MB | 0,45 s, 25 MB |

Alle bleiben weit unter 60 s und 256 MB. Der Hoster ist langsamer als der Mac; selbst das Zehnfache läge noch unter der Grenze.

## Prüfung des Briefpapiers (`LetterheadUpload`)

mPDF prüft heute mit seinem eingebauten FPDI (`use FpdiTrait`). Der Ersatz ist **FPDI allein** (`setasign\Fpdi\Fpdi`: `setSourceFile`, `importPage`, `getTemplateSize`): derselbe Parser, darum dasselbe Verhalten, auch die bekannte Grenze (die freie FPDI liest keine PDFs mit komprimierter Querverweistabelle, Test `compressedXref`). Bestehende Tests bleiben gültig; die Hilfsfunktion im Test, die das Briefpapier mit mPDF erzeugt, wechselt auf FPDF.

## npm: Lizenzen der Produktiv-Pakete

Gelesen aus `frontend/package-lock.json` ohne Installation (Einträge ohne `dev`). 890 Pakete, davon 784 MIT, 30 ISC, 20 Apache-2.0, 24 BSD, dazu 0BSD, CC0, BlueOak. Weil `nuxt` als Produktiv-Abhängigkeit eingetragen ist, zählt das Lockfile auch die Werkzeuge des Builds mit. Ins gebaute Frontend (`.output/public`) kommen sie nicht (geprüft mit einer Suche im Build).

| Paket | Lizenz | Im gebauten Frontend? |
|---|---|---|
| `@web.awesome.me/webawesome-pro` 3.14.0 | proprietär (Feld leer) | **ja** (Theme «Matter», Palette «Mild», `wa-combobox`); Ablösung beschlossen (Auftrag 82, Nachtrag E) |
| `lightningcss` 1.33.0 (+ 11 Plattformpakete) | MPL-2.0 | nein, Werkzeug des Builds (im CSS stehen nur Variablennamen `--lightningcss-*`) |
| `caniuse-lite` | CC-BY-4.0 | nein, Daten für den Build |
| `argparse` 2.0.1 | Python-2.0 | nein, Werkzeug |
| `node-forge` 1.4.3 | BSD-3-Clause oder GPL-2.0 (zur Wahl) | nein, Entwicklungsserver |
| `tosource` 2.0.0-alpha.3 | ohne Feld, Datei LICENSE: zlib | nein, `@rollup/plugin-yaml` |

`tools/planer` hat keine Abhängigkeiten (kein Lockfile). Das Lizenzfeld steht dort auf `UNLICENSED`, in `backend/composer.json` auf **`MIT`**: beides passt Auftrag D an.

## Aufwand für B

Schätzung: ein bis zwei Arbeitstage. Modell: **Opus** (neue kleine Schnittstelle im Kern, Wirkung auf Rechnung und Dokumente).

1. `App\Core\Pdf` bekommt eine eigene kleine Schnittstelle statt mPDF-Methoden, z. B. `PdfFrame::render(html, options)` mit Titel, Rändern, Briefpapier, Kopf- und Fusszeile, Seitenzahl, Wasserzeichen und angehängten PDF-Seiten (für den Zahlteil). Innen: dompdf, danach FPDI. `InvoicePdf` und `DocumentPdf` nutzen nur noch diese Schnittstelle; ihre öffentlichen Methoden bleiben.
2. Vorlagen anpassen: Adressfeld relativ zu den Rändern von Seite 1, `td[align=right]`, Zeilenhöhen und Abstände an das heutige Bild angleichen, Schriftnamen (`dejavusans` → «DejaVu Sans»).
3. `LetterheadUpload::check()` auf FPDI allein.
4. `composer.json`: `mpdf/mpdf` raus, `dompdf/dompdf` und `setasign/fpdi` rein; die Erweiterungen aus C prüfen (dompdf braucht `dom` und `mbstring`, für Bilder `gd`).
5. Tests: Architekturtest «kein `Mpdf\`», Test auf `composer licenses` ohne GPL-2.0-only, bestehende PDF-Tests grün, `FpdfBoundaryTest` erweitern (FPDI auf FPDF auch in `App\Core\Pdf`, siehe Frage 3).
6. Vergleichsbilder vorher/nachher, QR-Scan mit der Banking-App, Doku (`docs/abrechnung.md`, `docs/starter-kit.md`).

## Offene Fragen an Oliver

1. **Bibliothek:** dompdf + FPDI wie empfohlen?
2. **Schriften:** dompdf bringt DejaVu Sans, DejaVu Serif und DejaVu Sans Mono mit, aber nicht DejaVu Sans Condensed, FreeSans und FreeSerif. (a) Die drei aus der Auswahl nehmen (gespeicherte Einstellung fällt auf DejaVu Sans zurück) oder (b) die Schriftdateien mitliefern (DejaVu: freie Lizenz; GNU FreeFont: GPL-3.0 mit Font-Ausnahme).
3. **FPDF-Grenze:** Dein Entscheid vom 25.11. sagt, nur der Zahlteil nutzt FPDF (`FpdfBoundaryTest`). FPDI baut auf FPDF auf; beim Zusammensetzen der Seiten nutzt dann auch `App\Core\Pdf` FPDF. Grenze so erweitern?
4. **Wasserzeichen auf dem Zahlteil (Befund heute):** mPDF druckt «Entwurf» bei einem Entwurf auch über den QR-Zahlteil. Mit dompdf entfiele das. So gewollt (Entwurf nicht zahlbar) oder nur auf den Briefseiten?
5. **Lizenzen (keine Juristen):** Bestätigst du die gängige Auslegung, dass LGPL-2.1 (dompdf) und LGPL-3.0-or-later (php-svg-lib) mit AGPL-3.0 und den Apache-2.0-Paketen verträglich sind? Sonst bräuchte es eine Rückfrage bei einer Fachperson.
