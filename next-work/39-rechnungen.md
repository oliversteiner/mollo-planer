# 39 · Abrechnung: Rechnungen aus erledigten Meilensteinen

Planer: planer.mollo.dev · Projekt 2 «Mollo Planer» · ToDo `t-382` (Status der Teile dort nachführen).

Status: offen. Auftrag von Oliver über Stuart (2026-11-23). Nach 38, 40, 41, 37, 36 (Reihenfolge bestimmt Oliver). Branch von `dev` (`feature/abrechnung`), PR nach `dev`, nicht mergen. Teile A bis F je ein Commit.

## Entscheide (Oliver, 2026-11-23)

1. **Eigenes Hauptmenü «Abrechnung»**, projektübergreifend, mit **Systemrecht** `billing.manage`. Kein Reiter «Rechnungen» im Projekt.
2. **Fixpreis pro Meilenstein.** Eine Rechnungsposition je Meilenstein.
3. **Kundenliste:** Kunden sind eigene Einträge; ein Projekt verweist auf einen Kunden.
4. **Eine Rechnung darf Meilensteine aus mehreren Projekten desselben Kunden enthalten**, auf der Rechnung nach Projekt gruppiert.
5. **Ohne MWST.** Die Struktur verbaut eine spätere MWST nicht (Beträge netto, UID-Feld leer erlaubt).
6. **Zahlteil: Schweizer QR-Rechnung**, immer auf einer **eigenen letzten Seite ohne Briefpapier**. Kein TWINT.
7. **Briefpapier als PDF** (Teil F), auch für den PDF-Export der Dokumente, wählbar pro Export.
8. **Name «Abrechnung», nicht «Auftragsverwaltung».** Offerten, Aufträge, Stunden, Mahnungen kommen später; Menü und Routen so anlegen, dass sie ohne Umbau dazukommen können.

Anleitung für Oliver zum Briefpapier (Masse, Export): PDF «briefpapier-anleitung.pdf» von Stuart (2026-11-23); die Vorgabewerte unten stammen daraus.

## Ausgangslage

PDF-Erzeugung gibt es: `App\Services\DocumentPdf` (mPDF, Blade-Vorlagen in `resources/views/documents/`, Sprache des Projekts), Mutation `exportDocumentPdf`. Grenzen auf Hostpoint: `memory_limit` 256M, `max_execution_time` 60 (`next-work/16-entscheid-d0.md`). Für die Abrechnung fehlen Preise, Kunden, Firmendaten und Rechnungen.

## Datenmodell (Vorschlag, Bob darf begründet abweichen)

**Beträge immer als ganze Rappen** (`unsigned bigint`, `*_cents`), nie Float. Währung CHF (Feld `currency`, vorerst fest `CHF`).

* `milestones.price_cents` (nullable). Kein Preis: nicht verrechenbar.
* `customers`: Firma (optional), Name, Strasse, Hausnummer, PLZ, Ort, Land (ISO-2, Vorgabe CH), E-Mail (optional), Sprache der Rechnung (de/en), Notiz (intern), archiviert. **Strukturierte Adresse** (QR-Rechnung ab Version 2.3 erlaubt nur noch diese).
* `projects.customer_id` (nullable, `nullOnDelete`; Kunden mit Rechnungen nicht löschbar, nur archivieren).
* **Firmendaten (Absender)**, Einstellungen der Abrechnung: Name, Strasse, Hausnummer, PLZ, Ort, Land, E-Mail, Telefon, Website (optional), **IBAN** (normale IBAN, Prüfziffer; QR-IBAN nicht nötig), UID (optional, leer), Zahlungsfrist in Tagen (Vorgabe 30), Format der Rechnungsnummer (Vorgabe `{YYYY}-{NNNN}`), Briefpapier und Satzspiegel (Teil F).
* `invoices`: `customer_id`, `number` (eindeutig, **leer im Entwurf**), `status` (`draft`, `issued`, `paid`, `cancelled`), `issue_date`, `due_date`, `currency`, `total_cents`, `recipient` (JSON-Abzug der Kundenadresse beim Ausstellen), `sender` (JSON-Abzug der Firmendaten), `reference` (Creditor Reference ISO 11649, `RF…`, aus der Nummer), `locale`, `pdf_path` (privater Speicher), `notes` (optional, Text auf der Rechnung), `created_by`, `issued_by`, `issued_at`, `paid_at`, `cancelled_at`, `cancel_reason`.
* `invoice_items`: `invoice_id`, `project_id` (nullable, `nullOnDelete`) **und** `project_name` (Abzug), `milestone_id` (nullable, `nullOnDelete`), `position`, `title`, `description` (Klartext, aus der Beschreibung des Meilensteins gekürzt, im Entwurf änderbar), `amount_cents`.
* Nummernkreis: eigene Tabelle (Jahr → letzte Nummer), Vergabe beim Ausstellen in einer Transaktion mit Sperre (`lockForUpdate`), lückenlos.
* Konto-Verzeichnis aus Auftrag 37: `created_by`, `issued_by` eintragen; `customers` und `invoices` sind Inhalt, keine Konto-Tabellen.

## Regeln

* **Verrechenbar:** Meilenstein `done`, mit Preis, Projekt mit Kunde, und in keiner Rechnung mit Status `draft`, `issued` oder `paid`. Ein Meilenstein in einem Entwurf ist für andere Entwürfe gesperrt.
* **Eine Rechnung, ein Kunde:** alle Positionen gehören zu Projekten desselben Kunden.
* **Ausstellen ist endgültig:** Nummer vergeben, Adressen, Projektnamen und Positionen einfrieren, PDF erzeugen und **speichern** (Aufbewahrung 10 Jahre, nie neu erzeugen). Danach keine Änderung mehr ausser Status.
* **Storno:** Status `cancelled` mit Grund; Nummer bleibt belegt; die Meilensteine werden wieder verrechenbar. Ausgestellte Rechnungen werden nie gelöscht. Entwürfe dürfen gelöscht werden.
* **Bezahlt:** von Hand setzen, mit Datum. **Überfällig** ist abgeleitet (ausgestellt, Fälligkeit vorbei), kein eigener Status.
* **Fälligkeit:** Ausstelldatum + Zahlungsfrist.
* Meilenstein oder Projekt mit ausgestellter Rechnung bleibt löschbar (Papierkorb wie heute); die Rechnung trägt ihren Abzug.
* Fehlen Kundenadresse, Firmendaten oder IBAN, sagt die Oberfläche genau, was fehlt (Fehlercodes, nicht nur Text).

## Rechte und Datenschutz

* Neues **Systemrecht `billing.manage`** (Vorgabe: System-Admin; zuweisbar wie andere Systemrechte). Es ist **unabhängig** von den Rollen im Projekt: Owner oder Admin eines Projekts sehen ohne dieses Recht weder Preise noch Kunden noch Rechnungen.
* `milestones.price_cents` und `projects.customer` in GraphQL nur mit Recht; sonst `null`, nie der Wert.
* **Agenten und Connector:** keine Preise, keine Kunden, keine Rechnungen. `export_plan`, Teilexport, Planer-Format v2 und der Assistent enthalten nichts davon. Agent-Konten bekommen `billing.manage` nie (Prüfung beim Zuweisen). Test dafür.
* Firmendaten und Briefpapier ändern: mit Recht und starker Anmeldung.
* PDFs auf dem privaten Speicher, Download nur über eine geprüfte Mutation, nie über eine öffentliche URL.

## A. Daten, Recht, Kunden, Firmendaten (Backend)

Migrationen, Modelle, Recht, GraphQL für Kunden (Liste, anlegen, ändern, archivieren), Kunde am Projekt, Firmendaten, Preis am Meilenstein. Validierung: IBAN (Prüfziffer, CH/LI), PLZ/Land, Beträge ≥ 0 und ≤ 9 999 999.99 (Grenze der QR-Rechnung).

## B. Rechnungen (Backend)

* GraphQL: `billableMilestones(customerId?)` (gruppiert nach Kunde und Projekt, mit Summen), `invoices(filter: status, customerId, year)`, `invoice(id)`, Mutationen `createInvoiceDraft(customerId, milestoneIds, notes)`, `updateInvoiceDraft`, `deleteInvoiceDraft`, `issueInvoice`, `markInvoicePaid`, `cancelInvoice`, `exportInvoicePdf`, `previewInvoicePdf` (Entwurf, ohne Speichern; Musterrechnung für Teil F). Namen Bobs Wahl. Fehlercodes für «nicht verrechenbar», «anderer Kunde», «Kunde fehlt», «Firmendaten fehlen».
* Ein Dienst für die Abläufe (z. B. `InvoiceService`), kein Code in den Resolvern. Abfragen mit fester Zahl (kein N+1), auch über viele Projekte.

## C. PDF mit QR-Rechnung

* Eigene Vorlage (`resources/views/invoices/…`), gemeinsamer PDF-Rahmen mit `DocumentPdf` (mPDF-Einstellungen, Kopf, Fuss, Briefpapier aus Teil F) ausgelagert, nicht kopiert.
* Inhalt: Absender, Empfänger im Adressfeld, Rechnungsnummer, Datum, Fälligkeit, Positionen **nach Projekt gruppiert** (Zwischentitel Projektname), je Titel, Beschreibung, Betrag; Total; Zahlungsfrist; Notiz. Sprache: Sprache des Kunden.
* **Zahlteil** immer auf einer **eigenen letzten Seite** ohne Briefpapier, unten 105 mm, Empfangsschein 62 mm, Zahlteil 148 mm, nach Swiss Payment Standards. Mit einer gepflegten Bibliothek (Vorschlag `sprain/swiss-qr-bill`; vorher prüfen: PHP 8.5, Lizenz, Ausgabe mit mPDF, z. B. als SVG). Nicht selbst bauen. Neue Pakete vorher Oliver melden.
* Referenz: Creditor Reference (`SCOR`, `RF…`) aus der Rechnungsnummer.
* Prüfung: QR-Code mit einer Banking-App lesen (Oliver) und die PDF im Validierungsportal von SIX prüfen (Oliver lädt hoch; Bob liefert eine Muster-PDF mit Testdaten).
* Laufzeit und Speicher für eine Rechnung mit 30 Positionen aus 3 Projekten messen und melden.

## D. Oberfläche

Mit `ui-consistency` (finding-patterns, planning, implementing, verifying), wie Auftrag 17. Das Hauptmenü ist neu: zuerst prüfen, wie die bestehenden Hauptbereiche (Projekte, Systemverwaltung) aufgebaut sind, und dem folgen.
* **Hauptmenü «Abrechnung»** (nur mit Recht), Unterseiten:
  * **Rechnungen:** Liste (Nummer, Kunde, Datum, Fälligkeit, Total, Status inkl. «überfällig»), Filter Status/Kunde/Jahr, Summen offen und überfällig. Detail: Positionen, PDF herunterladen, «Als bezahlt markieren», «Stornieren» mit Grund.
  * **Verrechenbar:** erledigte, unverrechnete Meilensteine mit Preis, gruppiert nach Kunde und Projekt, mit Summen; auswählen → «Rechnung erstellen» (Entwurf). Hinweise auf Projekte ohne Kunden und erledigte Meilensteine ohne Preis.
  * **Entwurf:** Beschreibung und Notiz bearbeiten, Positionen entfernen, Vorschau als PDF, **Ausstellen mit Bestätigung** («kann nicht rückgängig gemacht werden»).
  * **Kunden:** Liste, anlegen, bearbeiten, archivieren; je Kunde Projekte und Rechnungen.
  * **Einstellungen:** Firmendaten, Nummernformat, Zahlungsfrist, Briefpapier mit Satzspiegel und «Musterrechnung als PDF» (Teil F).
* **Im Projekt** (nur mit Recht): in den Projekteinstellungen Auswahl des Kunden; im Meilenstein-Formular Feld «Preis (CHF)»; bei Meilensteinen ein dezentes Kennzeichen «verrechnet» mit Link zur Rechnung. Kein Reiter «Rechnungen».
* Routen und Menü so, dass später «Offerten» und «Aufträge» als weitere Unterseiten dazukommen können.
* Texte de und en.

## E. Tests

* Feature-Tests: Verrechenbarkeit, doppelte Verrechnung verhindert (auch zwei Entwürfe gleichzeitig), «anderer Kunde» abgelehnt, mehrere Projekte eines Kunden in einer Rechnung, Nummern lückenlos und eindeutig unter gleichzeitigem Ausstellen, Einfrieren (Änderung an Meilenstein, Projektname oder Kundenadresse ändert ausgestellte Rechnung nicht), Storno gibt Meilensteine frei, Rechte (Projekt-Owner ohne `billing.manage` sieht keinen Preis und keinen Kunden), Agent/Connector/Export ohne Preis und Kunde, PDF erzeugt und gespeichert, Referenz gültig.
* Unit-Tests: Creditor Reference, IBAN-Prüfung, Rappen-Rechnung.
* Browser-Test: von «Verrechenbar» bis zum Download.
* `composer test`, `composer test:mariadb`, `composer analyse`, Frontend-Prüfungen.

## F. Briefpapier (PDF-Rahmen der Basis)

Gehört zum gemeinsamen PDF-Rahmen und damit in die Basis (`App\Core\…`, siehe `docs/starter-kit.md`); Rechnungen und Dokumente nutzen ihn. Die Abrechnung selbst ist ein optionales Modul, nicht Basis.

* **Upload** unter Abrechnung → Einstellungen (mit Recht, starke Anmeldung): PDF für **Seite 1** und optional für **Folgeseiten** (ein PDF mit 1 oder 2 Seiten, oder zwei Dateien, Bobs Wahl). A4 hochkant, höchstens 5 MB.
* **Prüfung beim Upload:** Format A4, Seitenzahl, und dass mPDF (FPDI) die Datei lesen kann. PDFs mit komprimierten Objekt-Streams (ab PDF 1.5) liest die freie FPDI oft nicht: dann klare Meldung «als PDF 1.4 oder PDF/X-1a exportieren». Keine Umwandlung auf dem Server.
* Speicher privat; ein neues Briefpapier gilt nur für neue PDFs.
* **Satzspiegel in mm**, einstellbar, Vorgaben: oberer Rand Seite 1 100, Folgeseiten 35, unten 25, links 20, rechts 20; Adressfeld Fenster links, 20 mm von links, 50 mm von oben, 75 × 30 mm (sichtbarer Bereich im C5-Fenster laut ePost-Vorgaben: 15–95 mm von links, 47–81 mm von oben). Fenster rechts wählbar; Werte dafür stellt Oliver nach Nachmessen ein.
* **Schrift** des Texts: Auswahl aus wenigen mitgelieferten, gut lesbaren Schriften. Eigene Schrift hochladen: nicht in diesem Auftrag.
* **Vorschau:** «Musterrechnung als PDF» mit Testdaten.
* **Anleitung zum Herunterladen:** Direkt beim Upload des Briefpapiers (Abrechnung → Einstellungen, Abschnitt Briefpapier) ein gut sichtbarer Link «Anleitung und Massblätter (PDF)» mit kurzem Hinweis («Masse, Exporteinstellungen und Vorlagen 1:1 für dein Grafikprogramm»). Auch im leeren Zustand (noch kein Briefpapier) prominent zeigen; bei einer Fehlermeldung zum Upload (z. B. «nicht lesbar») ebenfalls darauf verweisen.
  * Quelle: `docs/briefpapier/briefpapier-anleitung.pdf` (Erzeuger: `briefpapier-anleitung.py` daneben). Beim Build als statische Datei ausliefern (z. B. nach `frontend/public/downloads/`, Name mit Version oder Datum, damit der Cache aus Auftrag 36 nicht stört). Die Datei enthält keine Geheimnisse; ein öffentlicher Pfad ist in Ordnung.
  * **Masse abgleichen:** Die Vorgaben des Satzspiegels im Planer (oben Seite 1 100, Folgeseiten 35, unten 25, links/rechts 20, Adressfeld 20/50 mm, 75 × 30 mm) und die Werte in der Anleitung (`V` im Skript) müssen gleich sein. Weicht Bob begründet ab, passt er das Skript an und erzeugt die PDF neu; ein Test vergleicht die Vorgaben im Code mit einer kleinen Datei der Werte (z. B. JSON neben dem Skript), damit sie nicht auseinanderlaufen.
* **Zahlteil-Seite** immer ohne Briefpapier.
* **Dokumente:** `exportDocumentPdf` bekommt eine Option «mit Briefpapier» (Vorgabe aus), in der Oberfläche beim Export wählbar, nur sichtbar, wenn ein Briefpapier hochgeladen ist. Für Dokumente braucht es **kein** `billing.manage`.
* Tests: Upload gültig/ungültig (Format, nicht lesbar, zu gross, ohne Recht), Hintergrund auf Seite 1 und Folgeseiten, nicht auf der Zahlteil-Seite; Dokument-Export mit und ohne Option.

## Nicht Teil dieses Auftrags

Offerten, Auftragsbestätigungen, Stunden, Versand per Mail, Mahnungen, MWST, Zahlungsabgleich (camt.054), eBill, Gutschriften, Teilrechnungen eines Meilensteins.
