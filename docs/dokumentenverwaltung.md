# Dokumentenverwaltung je Projekt (Vorschlag)

Stand: 2026-11-16. Rolle: Analyse und Plan. Status: **Vorschlag zur Entscheidung** (Fragen in §10).
Grundlage: `docs/rollen-funktionen-rechte.md`, `docs/schema-review.md` (A5 Papierkorb), Kontext-Dokument vom 2026-11-13. Der Code wurde für diesen Vorschlag nicht gelesen; Stellen, die Bob am Code prüfen muss, sind markiert (**prüfen**).

**Auftrag (Oliver, 2026-11-16):** Jedes Projekt bekommt eine Dokumentenverwaltung für Vorgehensweisen, Sitzungsprotokolle, Analysen usw. Jedes Dokument gehört zu genau einem Projekt, hat einen Owner, kann für andere zum Bearbeiten oder nur zum Lesen freigegeben werden. Gespeichert als Markdown, exportierbar als PDF. Editor: TipTap.

---

## 1. Kurzfazit

- Machbar mit dem bestehenden Stack, ohne neuen Dienst und ohne Node auf dem Server.
- **Zwei Stellen weichen von heutigen Regeln ab** und brauchen eine Entscheidung:
  1. Rich Text wird heute als TipTap-JSON gespeichert. Dokumente sollen als **Markdown** gespeichert werden. Vorschlag: Markdown gilt **nur für Dokumente**, Beschreibungen und Kommentare bleiben JSON.
  2. Dokumente brauchen mehr Formatierung als die heutige Whitelist (Überschriften, Tabellen, Aufgabenlisten, Codeblöcke). Vorschlag: ein zweites, größeres Profil „Dokument“.
- **PDF nicht über TipTap.** Der PDF-Export von TipTap ist ein kostenpflichtiger Cloud-Dienst, der Inhalt geht an einen Dritten. Vorschlag: PDF im Backend mit reinem PHP erzeugen.
- **Freigabe** = Daten am Dokument (Stufe für alle Projektmitglieder plus Freigaben je Person). Die **Rechte** bleiben im bestehenden Modell (Recht → Funktion → Rolle).
- Kein gleichzeitiges Bearbeiten in Echtzeit. Schutz vor Überschreiben über eine Revisionsnummer.

## 2. Abgrenzung

**In Version 1:** Liste je Projekt, Anlegen, Lesen, Bearbeiten, Freigeben, Owner wechseln, Papierkorb, Suche, Export als `.md` und `.pdf`.

**Nicht in Version 1 (Ausblick §9):** Bilder und Anhänge, Versionsverlauf, Kommentare am Dokument, Ordner, Vorlagen für Dokumente, MCP-Werkzeuge, Mails bei Freigabe, Aufnahme in die Planer-Datei.

## 3. Datenmodell (MariaDB)

### `documents`

| Spalte | Typ | Hinweis |
|---|---|---|
| `id` | bigint PK | |
| `project_id` | FK `projects`, `cascadeOnDelete` | genau ein Projekt, nie änderbar |
| `owner_id` | FK `users`, nullable, `nullOnDelete` | aus dem Login gesetzt, nie aus der Eingabe |
| `title` | varchar(200) | Pflicht |
| `kind` | varchar(32) | Art, siehe §10 Frage 4. Standard `other` |
| `content` | longtext | **Markdown**, Profil „Dokument“ (§5), höchstens 200 000 Zeichen |
| `project_access` | varchar(8) | `none`, `read`, `edit`: Stufe für alle Projektmitglieder |
| `revision` | unsigned int, Standard 1 | zählt bei jeder Inhaltsänderung hoch |
| `updated_by` | FK `users`, nullable, `nullOnDelete` | |
| `created_at`, `updated_at` | | |
| `deleted_at`, `deleted_by`, `deletion_id` | | Papierkorb wie A5 |

Indizes: `(project_id, deleted_at, updated_at)` für die Liste, `(project_id, kind)` für den Filter, `FULLTEXT(title, content)` für die Suche.
Tests laufen auch auf SQLite: Die Suche braucht dort einen Ersatz (`LIKE`). Die Volltextsuche wird nur mit `composer test:mariadb` geprüft.

### `document_shares`

| Spalte | Typ | Hinweis |
|---|---|---|
| `id` | bigint PK | |
| `document_id` | FK `documents`, `cascadeOnDelete` | |
| `user_id` | FK `users`, `cascadeOnDelete` | muss Projektmitglied sein |
| `access` | varchar(8) | `read` oder `edit` |

`unique(document_id, user_id)`, Index `(user_id)`.

**Bewusst nicht:** keine polymorphe Freigabe-Tabelle, keine Freigabe an Rollen, keine Ordner. Ein Dokument ohne Freigabe ist privat.

## 4. Rechte und Freigabe

### 4.1 Neue Projektrechte (Liste in §4 von `rollen-funktionen-rechte.md`)

| Recht | Bedeutung | Standardbelegung (Funktion) |
|---|---|---|
| `document.create` | Dokumente im Projekt anlegen | Mitarbeiten |
| `document.update` | Dokumente bearbeiten, die einem gehören oder zum Bearbeiten freigegeben sind | Mitarbeiten |
| `document.manageAny` | alle Dokumente des Projekts sehen, bearbeiten, freigeben, Owner setzen, löschen | Projekt verwalten |

Lesen braucht kein eigenes Recht: Wer das Projekt sieht und Zugriff nach §4.2 hat, darf lesen und exportieren.
`trash.restore` und `trash.purge` gelten auch für Dokumente. `projects.manageAny` gibt wie überall alle Projektrechte.

### 4.2 Wirksamer Zugriff

Zugriff einer Person P auf Dokument D (höchste Stufe gewinnt):

1. P hat `document.manageAny` im Projekt → **verwalten**.
2. P ist Owner von D → **verwalten**.
3. Freigabe an P in `document_shares` → `read` oder `edit`.
4. `project_access` von D → `none`, `read` oder `edit`.

**Bearbeiten** gilt nur, wenn die Stufe mindestens `edit` ist **und** P das Recht `document.update` hat. So wirkt die Obergrenze der Systemrolle „Lesend“ ohne Sonderregel: Eine Freigabe zum Bearbeiten wird für diese Person zu „nur lesen“.

| Aktion | Bedingung |
|---|---|
| In der Liste sehen, lesen, exportieren | Stufe ≥ `read` |
| Titel, Art, Inhalt ändern | Stufe ≥ `edit` und `document.update` |
| Freigaben ändern, Owner übergeben, löschen | Stufe „verwalten“ |
| Anlegen | `document.create` |
| Wiederherstellen | Owner oder `document.manageAny` (bzw. `trash.restore`) |
| Endgültig löschen | `trash.purge` |

### 4.3 Grundregeln im Code (nicht einstellbar)

1. Freigaben und Owner nur an **Projektmitglieder**.
2. Verlässt eine Person das Projekt, werden ihre Freigaben in diesem Projekt gelöscht. War sie Owner, wird `owner_id` leer. Dokumente ohne Owner verwaltet, wer `document.manageAny` hat.
3. **Archivierte Projekte:** Dokumente nur lesbar, Export bleibt erlaubt.
4. `project_id` eines Dokuments ändert sich nie (kein Verschieben zwischen Projekten).
5. Ein Dokument im Papierkorb ist nur für Personen mit Stufe „verwalten“ sichtbar.

### 4.4 Für das Frontend

`Document.viewerAccess` liefert `READ`, `EDIT` oder `MANAGE` (schon mit `document.update` verrechnet). Das Frontend fragt nie Rollennamen und rechnet die Stufe nicht selbst.

## 5. Inhalt: Markdown und TipTap

### 5.1 Speicherform

- `documents.content` ist **Markdown** und die einzige Quelle. Kein zweites Feld mit JSON oder HTML.
- Der Editor lädt Markdown, arbeitet intern mit TipTap-JSON und speichert wieder Markdown. Umwandlung im Frontend mit dem offiziellen Paket `@tiptap/markdown` (seit TipTap 3.7, Status laut Hersteller **Beta**).
- Anzeige im Lesemodus ebenfalls über TipTap (nicht bearbeitbar). Die Regel „nie `v-html`“ bleibt.
- **Rohes HTML in Markdown ist verboten.** Der Editor darf eingebettetes HTML nicht übernehmen, das Backend lehnt es ab.

### 5.2 Profil „Dokument“ (Whitelist)

Absatz, Überschrift 1 bis 4, fett, kursiv, durchgestrichen, Code im Text, Codeblock, Zitat, Liste, nummerierte Liste, Aufgabenliste, Link (`http`, `https`, `mailto`), Trennlinie, Tabelle.
Nicht erlaubt: Bilder, HTML, Einbettungen, Farben, Schriftgrößen.

- Das bestehende Profil für Beschreibungen und Kommentare (`RichTextDocument.php`, `utils/rich-text.ts`) bleibt unverändert.
- Das neue Profil wird in `spec/format-v2.md` §6.1 als zweites Profil beschrieben, damit es eine Definition für Markdown gibt. **Prüfen:** Gibt es schon einen Umwandler Markdown ↔ TipTap-JSON (Planer-Datei, M4)? Wenn ja, dieselbe kanonische Form verwenden, statt eine zweite einzuführen.

### 5.3 Prüfung im Backend

Beim Speichern: Länge, Markdown mit `league/commonmark` (GFM) parsen, nur Knoten des Profils zulassen, HTML und unsichere Links ablehnen. Fehler als Validierungsfehler am Feld `content`.

### 5.4 Risiko: Rundlauf

Markdown → JSON → Markdown darf den Text nicht verändern, sonst entstehen bei jedem Speichern Scheinänderungen. Weil `@tiptap/markdown` Beta ist, wird das **vor** dem Bau geprüft (Ticket D0) und danach mit einer festen Sammlung von Beispieltexten in Vitest abgesichert. Fällt ein Element durch (z. B. Tabellen), wird es in Version 1 aus dem Profil genommen statt umgangen.

## 6. Gleichzeitiges Bearbeiten

- Keine Echtzeit-Zusammenarbeit (kein WebSocket-Dienst auf Hostpoint).
- `updateDocument` verlangt die `revision`, die der Editor geladen hat. Stimmt sie nicht mehr, antwortet das Backend mit dem Fehler `DOCUMENT_CONFLICT` und der aktuellen Revision. Das Frontend bietet an: neu laden, oder die eigene Fassung als neues Dokument sichern.
- Speichern ist eine bewusste Aktion (Knopf, Tastenkürzel). Ungespeicherte Eingaben liegen als Entwurf im Browser (`localStorage`), damit beim Neuladen nichts verloren geht.
- **Prüfen:** an das Muster „Neuladen über Revision“ aus M1 anlehnen.

## 7. Export

### 7.1 Markdown

Download im Frontend aus `content`, Dateiname aus dem Titel (`<titel>.md`). Kopfzeilen (Front Matter) mit Titel, Projekt, Owner, Stand. Kein Aufruf am Server nötig.

### 7.2 PDF

Im Backend, synchron, reines PHP:

Markdown → HTML (`league/commonmark`, HTML im Text maskiert, unsichere Links aus) → Blade-Druckvorlage (Titel, Projekt, Owner, Stand, Seitenzahlen, Sprache = `project.locale`) → PDF.

- Bibliothek: `dompdf` oder `mPDF`. Entscheidet der Versuch in D0 anhand von Tabellen, Seitenumbrüchen, Codeblöcken und Schrift. Kein Nachladen fremder Inhalte.
- Nicht über die Queue: Die Queue läuft per Cron, ein Download muss sofort antworten.

**Verworfen:**

| Variante | Grund |
|---|---|
| TipTap „Conversion“ (`@tiptap-pro/extension-export-pdf`) | kostenpflichtiges Abo, Dokument geht an den Dienst von TipTap |
| Browsershot / Chromium | braucht Node und Chromium, gibt es auf Hostpoint nicht |
| Drucken im Browser | Ergebnis je Browser verschieden, später nicht für Connector nutzbar |

### 7.3 Transport

Vorschlag: über GraphQL, damit es bei einer API bleibt (Anmeldung, 2FA und Begrenzung gelten automatisch):

`exportDocumentPdf(id: ID!): DocumentFile!` mit `fileName`, `mimeType`, `contentBase64`.

Dokumente enthalten nur Text, die Dateien bleiben klein. Alternative siehe §10 Frage 7.

## 8. API und Oberfläche

### 8.1 GraphQL (Kontrakt)

```graphql
enum DocumentAccess { NONE, READ, EDIT, MANAGE }   # MANAGE nur in viewerAccess
enum DocumentShareAccess { READ, EDIT }

type Document {
  id: ID!
  project: Project!
  title: String!
  kind: String!
  content: String!            # Markdown, nur in der Einzelabfrage laden
  owner: User
  projectAccess: DocumentAccess!        # NONE, READ, EDIT
  shares: [DocumentShare!]!             # nur mit MANAGE sichtbar, sonst leer
  revision: Int!
  updatedBy: User
  createdAt: DateTime!
  updatedAt: DateTime!
  viewerAccess: DocumentAccess!
}

type DocumentShare { user: User!, access: DocumentShareAccess! }
type DocumentFile { fileName: String!, mimeType: String!, contentBase64: String! }

# Query
documents(projectId: ID!, kind: String, search: String): [Document!]!
document(id: ID!): Document

# Mutation
createDocument(input: CreateDocumentInput!): Document!          # projectId, title, kind, content, projectAccess
updateDocument(id: ID!, revision: Int!, input: UpdateDocumentInput!): Document!   # title, kind, content
setDocumentSharing(id: ID!, input: DocumentSharingInput!): Document!   # projectAccess, shares[{userId, access}] (ersetzt die Liste)
transferDocument(id: ID!, ownerId: ID!): Document!
deleteDocument(id: ID!): Document!                              # in den Papierkorb, liefert deletionId
exportDocumentPdf(id: ID!): DocumentFile!
```

- `documents` liefert nur Dokumente mit Stufe ≥ `read` (Scope wie `visibleToViewer`), sortiert nach `updated_at` absteigend.
- **Kein N+1:** Die Liste lädt `content` nicht aus der Datenbank. `viewerAccess` wird für die ganze Liste mit einer Abfrage auf `document_shares` (nur die eigene Person) berechnet, Owner per Batch.
- `TrashItemType` bekommt `DOCUMENT`.
- Änderungen nur an Freigaben erhöhen die `revision` nicht.
- `MutationCoverageTest` muss die neuen Mutationen abdecken.

### 8.2 Oberfläche (nur `wa-*`, DE/EN)

| Route | Inhalt |
|---|---|
| `/projects/:id/documents` | Liste: Titel, Art, Owner, Freigabe, Stand. Filter nach Art, Suche, Knopf „Dokument anlegen“ (nur mit `document.create`). Leerer Zustand mit Knopf |
| `/projects/:id/documents/:docId` | Lesemodus. Mit `EDIT`: „Bearbeiten“. Mit `MANAGE`: „Freigeben“, „Owner übergeben“, „Löschen“. Immer: „Exportieren“ (Markdown, PDF) |

Bausteine:

- `DocumentList`
- `DocumentEditor`: TipTap mit Werkzeugleiste für das Profil „Dokument“. **Prüfen:** gemeinsame Basis mit dem bestehenden Rich-Text-Editor, nur andere Erweiterungen und andere Speicherform.
- `DocumentShareDialog` (`wa-dialog`): Stufe für alle Mitglieder (privat, lesen, bearbeiten) und Liste der Personen mit Stufe. Auswahl nur aus Projektmitgliedern.
- `DocumentExportMenu`
- `DocumentConflictDialog`

Eintrag „Dokumente“ in der Projektnavigation. Vor dem ersten Ticket mit Oberfläche die bestehenden Seitenmuster erheben (Liste, Detail, Dialog), damit die neuen Seiten gleich gebaut sind.

## 9. Ausblick (nach Version 1)

- **Versionsverlauf:** Tabelle `document_versions` (Revision, Titel, Inhalt, wer, wann), Vergleich und Zurückholen.
- **MCP:** `list_documents`, `get_document`, `create_document`, `update_document` mit den Rechten der Person. Markdown passt dafür ohne Umwandlung.
- **KI:** Dokumente als Kontext für den Assistenten (mit Freigabe wie beim KI-Kontext, B2).
- **Bilder und Anhänge:** braucht Dateiablage, Größenbegrenzung und Bereinigung beim endgültigen Löschen.
- **Mail bei Freigabe**, **Vorlagen** (z. B. Sitzungsprotokoll), **Bearbeitungshinweis** („wird gerade bearbeitet von …“).

## 10. Fragen

1. **Markdown als Speicherform nur für Dokumente**, Beschreibungen und Kommentare bleiben TipTap-JSON. Einverstanden? (Empfehlung: ja.)
2. **Freigabe-Modell:** Stufe für alle Projektmitglieder **plus** Freigaben je Person wie in §4? Und welcher Standard für neue Dokumente: privat (`none`) oder für alle lesbar (`read`)? (Empfehlung: `read`, weil die Ablage „zentral“ sein soll.)
3. **Private Dokumente:** Sehen Owner und Manager des Projekts (Recht `document.manageAny`) auch private Dokumente anderer? (Empfehlung: ja, sonst entstehen Dokumente, die nach einem Austritt niemand mehr erreicht.)
4. **Arten:** feste Liste im Code (`procedure`, `minutes`, `analysis`, `concept`, `other`) oder je Projekt definierbar wie Labels (M3)? (Empfehlung: feste Liste in Version 1.)
5. **Bilder und Anhänge** in Version 1 weglassen? (Empfehlung: ja.)
6. **Versionsverlauf** in Version 1 weglassen, nur Schutz über Revision? (Empfehlung: ja, Verlauf als nächster Schritt.)
7. **PDF-Transport:** über GraphQL als Base64 (eine API) oder eine eigene Download-Route mit signierter Adresse (wäre eine neue Ausnahme zur Regel „GraphQL ist die einzige API“)? (Empfehlung: GraphQL.)
8. **Einordnung:** eigener Meilenstein nach M2 (Papierkorb) und nach dem Rechte-Umbau, weil beides vorausgesetzt wird. Vor oder nach M4?

## 11. Tickets für Bob

Reihenfolge wie nummeriert. Ein Schritt, ein Commit, Tests grün. Voraussetzung: `App\Support\Rights` und Papierkorb (A5) sind umgesetzt. D1 bis D8 erst nach Entscheidung der Fragen.

**D0 Versuch (ohne Produktivcode, Ergebnis als Notiz in `docs/`)**
- `@tiptap/markdown` mit dem Profil „Dokument“: Rundlauf Markdown → JSON → Markdown für jedes Element, besonders Tabellen, Aufgabenlisten, verschachtelte Listen, Codeblöcke. HTML im Markdown muss abschaltbar sein.
- PDF: `dompdf` gegen `mPDF` mit einem Beispieldokument (Tabelle über zwei Seiten, Codeblock, Umlaute, Seitenzahlen). Lauffähig auf Hostpoint (PHP-Erweiterungen, Speicher, Laufzeit).
- Prüfen, ob ein Umwandler Markdown ↔ JSON für die Planer-Datei schon besteht.
- Abnahme: Empfehlung je Punkt, Liste der Elemente, die nicht stabil sind.

**D1 Datenbank und Models**
- Migrationen `documents`, `document_shares` nach §3, Models, Relationen `Project::documents`, Factory, DemoSeeder.
- `SoftDeletes` mit `deleted_by`, `deletion_id`. Projekt im Papierkorb nimmt seine Dokumente mit derselben `deletion_id` mit.
- Abnahme: `migrate:fresh --seed` läuft, Tests auf SQLite und MariaDB grün.

**D2 Rechte und Policy**
- Rechte `document.create`, `document.update`, `document.manageAny` in die Rechteliste, Kurztexte DE/EN, Standardbelegung, `permissions:sync-defaults`.
- Dienst für den wirksamen Zugriff nach §4.2, `DocumentPolicy`, Scope für sichtbare Dokumente.
- Grundregeln §4.3, auch beim Entfernen eines Mitglieds (Freigaben löschen, Owner leeren).
- Abnahme: Test-Matrix Rolle × Freigabe × Aktion, `OutsiderAccessTest`, archiviertes Projekt, Systemrolle „Lesend“ mit Freigabe `edit` kann nicht schreiben.

**D3 Markdown-Profil im Backend**
- Prüfer für das Profil „Dokument“ nach §5.3, Ergänzung in `spec/format-v2.md`.
- Abnahme: Tests je erlaubtem und je verbotenem Element (HTML, Bild, `javascript:`-Link, Überlänge).

**D4 GraphQL**
- Typen, Queries, Mutationen nach §8.1, `DOCUMENT_CONFLICT`, Suche (FULLTEXT, Ersatz für SQLite), `TrashItemType.DOCUMENT`, `composer schema:frontend`.
- Abnahme: `lighthouse:validate-schema`, `MutationTest` und `MutationCoverageTest` grün, Liste lädt `content` nicht, Anzahl der Abfragen für die Liste ist fest (Test).

**D5 PDF-Export**
- Dienst Markdown → HTML → PDF, Blade-Vorlage DE/EN, Mutation `exportDocumentPdf`.
- Abnahme: Test prüft PDF-Kopf und Dateiname, Zugriff nur mit Stufe ≥ `read`, HTML im Inhalt erscheint als Text.

**D6 Frontend: Liste und Lesen**
- Vorher Seitenmuster erheben. Route, `DocumentList`, Lesemodus über TipTap, Navigation, i18n, `npm run codegen`.
- Abnahme: Knöpfe nur nach `viewerAccess` und `viewerRights`, leerer Zustand, `lint`, `typecheck`, Vitest grün.

**D7 Frontend: Editor und Speichern**
- `DocumentEditor` mit Werkzeugleiste, Laden und Speichern als Markdown, Entwurf im Browser, `DocumentConflictDialog`, Warnung beim Verlassen mit ungespeicherten Änderungen.
- Abnahme: Rundlauf-Tests mit der Beispielsammlung aus D0, Konflikt-Fall getestet.

**D8 Frontend: Freigabe, Owner, Export, Papierkorb**
- `DocumentShareDialog`, Owner übergeben, `DocumentExportMenu` (Markdown im Browser, PDF über Mutation), Löschen mit „Rückgängig“, Dokumente auf der Papierkorb-Seite.
- Abnahme: Auswahl nur Projektmitglieder, Export in beiden Formaten, Dokumentation (`docs/README.md`, Kontext-Dokument) nachgeführt.

## Quellen

- TipTap Markdown (offizielles Paket, Beta): https://tiptap.dev/docs/editor/markdown
- TipTap PDF-Export (Convert-Dienst, kostenpflichtig): https://tiptap.dev/docs/conversion/export/pdf/editor-extension
- TipTap Preise: https://tiptap.dev/pricing
