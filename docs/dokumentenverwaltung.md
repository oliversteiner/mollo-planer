# Dokumentenverwaltung je Projekt

Stand: 2026-11-18 (dritte Fassung, baut auf der zweiten vom 2026-11-16 auf). Rolle: Plan der Planung (Stuart), Umsetzung Bob. Status: Backend (Auftrag 16) und Oberfläche (Auftrag 17, D6 bis D8, D10, D11) gebaut, siehe `umsetzung.md`; M8 (Echtzeit) offen. Umsetzung nach dem Release 1.0: **M7** (Dokumente mit Versionsverlauf), **M8** (Echtzeit). Aufträge: `next-work/16-dokumente-backend.md` (D0 bis D5, D9), danach 17 (Oberfläche).
Grundlage: `docs/rollen-funktionen-rechte.md`, `docs/schema-review.md` (A5 Papierkorb), `docs/planer-abgleich.md` §9 (Rich-Text-Konverter), `docs/zen-modus.md`, Kontext-Dokument vom 2026-11-13. Stellen, die Bob am Code prüfen muss, sind markiert (**prüfen**).

**Auftrag (Oliver, 2026-11-16):** Jedes Projekt bekommt eine Dokumentenverwaltung für Vorgehensweisen, Sitzungsprotokolle, Analysen usw. Jedes Dokument gehört zu genau einem Projekt, hat einen Owner, kann für andere zum Bearbeiten oder nur zum Lesen freigegeben werden. Gespeichert als Markdown, exportierbar als PDF. Editor: TipTap. Erwünscht: Bearbeiten in Echtzeit und ein Nachweis, wer welche Änderungen gemacht hat.

---

## 0. Entscheidungen (Oliver)

| Thema | Entscheidung |
|---|---|
| Speicherform | Markdown **nur für Dokumente**. Beschreibungen und Kommentare bleiben TipTap-JSON |
| Freigabe | Stufe für alle Projektmitglieder **plus** Freigaben je Person |
| Standard bei neuen Dokumenten | für alle Projektmitglieder **lesbar** (`read`) |
| Private Dokumente | Owner und Manager des Projekts sehen und verwalten sie (Recht `document.manageAny`) |
| Arten | **feste Liste** im Code |
| Bilder und Anhänge | **später**, nicht in M7 |
| PDF | **PHP im Backend**, synchron. Bibliothek und Transport wie beim Rechnungs-PDF, falls dort festgelegt, sonst Entscheidung nach D0 (§7.2) |
| Echtzeit | erwünscht, als Stufe 2 (M8). Weg **C: Abgleich über HTTP beim Webhoster**, kein zweiter Server |
| Nachweis | **je Änderung**. In M7 als Versionsverlauf, in M8 zusätzlich jede einzelne Änderung |
| Einordnung | nach M6: M7 Dokumente, M8 Echtzeit (2026-11-16) |
| **Umwandlung Markdown ↔ TipTap im Backend** | **2026-11-18**: Der Server wandelt um (§5.2), nicht der Browser. Der Editor arbeitet mit TipTap-JSON |
| **Zeitpunkt** | **2026-11-18**: Bob startet sofort auf einem eigenen Branch `feature/dokumente` (von `dev`). Der Pull Request „Release 1.0“ (#16) bleibt unberührt. Nach `dev` wird erst nach dem Tag `v1.0.0` gemergt |
| **Zen** | Dokumente bekommen Zen von Anfang an (D11, `docs/zen-modus.md`) |

## 1. Kurzfazit

- **M7** läuft mit dem bestehenden Stack, ohne neuen Dienst und ohne Node auf dem Server.
- **M8** (Echtzeit) braucht ebenfalls keinen neuen Server: Die Browser führen Änderungen mit Yjs zusammen, Laravel und MariaDB reichen sie nur weiter. Preis dafür: 1 bis 2 Sekunden Verzögerung statt echter Echtzeit, und ein eigener Baustein für den Abgleich.
- M7 wird so gebaut, dass M8 **nur Tabellen und eine Mutation ergänzt**, nichts umbaut.
- Der **Nachweis** kommt in zwei Stufen: Versionsverlauf ab M7, Protokoll jeder Änderung ab M8. Der Server stempelt beides mit der angemeldeten Person.
- **Eine kanonische Markdown-Form, eine Umwandlung (neu, 2026-11-18):** Der PHP-Umwandler aus S3e wird um das Profil „Dokument“ erweitert. Der Editor und später Yjs liefern TipTap-JSON, der Server schreibt Markdown. Damit entfallen das Beta-Paket `@tiptap/markdown` und ein zweiter Serialisierer in TypeScript, und es gibt keine Scheinänderungen durch zwei verschiedene Schreibweisen.

## 2. Abgrenzung

**M7:** Liste je Projekt, Anlegen, Lesen, Bearbeiten, Freigeben, Owner wechseln, Papierkorb, Suche, Versionsverlauf mit Vergleich und Zurückholen, Export als `.md` und `.pdf`, Zen.

**M8:** gemeinsames Bearbeiten mit kurzer Verzögerung, Anzeige wer gerade im Dokument ist, Protokoll jeder Änderung, Anzeige „wer hat was geschrieben“.

**Offen für später (§11):** Bilder und Anhänge, Kommentare am Dokument, Ordner, Vorlagen für Dokumente, MCP-Werkzeuge, Mails bei Freigabe, Dokumente als KI-Kontext, Aufnahme in die Planer-Datei.

## 3. Datenmodell M7 (MariaDB)

### `documents`

| Spalte | Typ | Hinweis |
|---|---|---|
| `id` | bigint PK | |
| `project_id` | FK `projects`, `cascadeOnDelete` | genau ein Projekt, nie änderbar |
| `owner_id` | FK `users`, nullable, `nullOnDelete` | aus dem Login gesetzt, nie aus der Eingabe |
| `title` | varchar(200) | Pflicht |
| `kind` | varchar(32) | `procedure`, `minutes`, `analysis`, `concept`, `other` (Standard). Texte DE/EN über i18n |
| `content` | longtext | **Markdown** in kanonischer Form, Profil „Dokument“ (§5), höchstens 200 000 Zeichen. Immer die zuletzt gesicherte Fassung |
| `project_access` | varchar(8), Standard `read` | `none`, `read`, `edit`: Stufe für alle Projektmitglieder |
| `revision` | unsigned int, Standard 1 | zählt bei jeder gesicherten Fassung hoch |
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

### `document_versions` (Nachweis Stufe 1)

| Spalte | Typ | Hinweis |
|---|---|---|
| `id` | bigint PK | |
| `document_id` | FK `documents`, `cascadeOnDelete` | |
| `revision` | unsigned int | gleich `documents.revision` zum Zeitpunkt des Sicherns |
| `title` | varchar(200) | |
| `kind` | varchar(32) | |
| `content` | longtext | Markdown dieser Fassung |
| `created_by` | FK `users`, nullable, `nullOnDelete` | wer gesichert hat, aus dem Login |
| `contributors` | JSON, nullable | M8: alle Personen mit Änderungen seit der vorigen Fassung, vom Server berechnet |
| `restored_from` | unsigned int, nullable | Revision, wenn die Fassung durch Zurückholen entstand |
| `created_at` | | |

`unique(document_id, revision)`.

- **Jedes Sichern** legt eine Fassung an, in derselben Transaktion wie die Änderung an `documents`. Fassungen werden nie geändert.
- Keine Frist und kein Ausdünnen. Fassungen verschwinden nur mit dem endgültigen Löschen des Dokuments.
- Zurückholen überschreibt nichts, sondern legt eine **neue** Fassung mit dem alten Inhalt an.
- Größe: ganze Fassungen statt Differenzen. Bei Textdokumenten unkritisch; falls nötig später Differenzen, ohne die API zu ändern.

**Bewusst nicht:** keine polymorphe Freigabe-Tabelle, keine Freigabe an Rollen, keine Ordner.

## 4. Rechte und Freigabe

### 4.1 Neue Projektrechte (Liste in §4 von `rollen-funktionen-rechte.md`)

| Recht | Bedeutung | Standardbelegung (Funktion) |
|---|---|---|
| `document.create` | Dokumente im Projekt anlegen | Mitarbeiten |
| `document.update` | Dokumente bearbeiten, die einem gehören oder zum Bearbeiten freigegeben sind | Mitarbeiten |
| `document.manageAny` | alle Dokumente des Projekts sehen (auch private), bearbeiten, freigeben, Owner setzen, löschen | Projekt verwalten |

Lesen braucht kein eigenes Recht: Wer das Projekt sieht und Zugriff nach §4.2 hat, darf lesen, den Verlauf sehen und exportieren.
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
| In der Liste sehen, lesen, Verlauf sehen, exportieren | Stufe ≥ `read` |
| Titel, Art, Inhalt ändern, Fassung zurückholen | Stufe ≥ `edit` und `document.update` |
| Freigaben ändern, Owner übergeben, löschen | Stufe „verwalten“ |
| Anlegen | `document.create` |
| Wiederherstellen aus dem Papierkorb | Owner oder `document.manageAny` (bzw. `trash.restore`) |
| Endgültig löschen | `trash.purge` |

### 4.3 Grundregeln im Code (nicht einstellbar)

1. Freigaben und Owner nur an **Projektmitglieder**.
2. Verlässt eine Person das Projekt, werden ihre Freigaben in diesem Projekt gelöscht. War sie Owner, wird `owner_id` leer. Dokumente ohne Owner verwaltet, wer `document.manageAny` hat. Fassungen und Protokoll behalten die Person.
3. **Archivierte Projekte:** Dokumente nur lesbar, Verlauf und Export bleiben erlaubt.
4. `project_id` eines Dokuments ändert sich nie (kein Verschieben zwischen Projekten).
5. Ein Dokument im Papierkorb ist nur für Personen mit Stufe „verwalten“ sichtbar.
6. **Fassungen und Protokoll sind unveränderlich.** Es gibt keine Mutation, die sie ändert oder einzeln löscht.

### 4.4 Für das Frontend

`Document.viewerAccess` liefert `READ`, `EDIT` oder `MANAGE` (schon mit `document.update` verrechnet). Das Frontend fragt nie Rollennamen und rechnet die Stufe nicht selbst.

## 5. Inhalt: Markdown und TipTap

### 5.1 Speicherform

- `documents.content` ist **Markdown** in der kanonischen Form (`spec/format-v2.md` §6.1, erweitert um das Profil „Dokument“) und die einzige Quelle. Kein Feld mit HTML, kein zweites Feld mit JSON.
- Anzeige im Lesemodus über TipTap (nicht bearbeitbar). Die Regel „nie `v-html`“ bleibt.
- **Rohes HTML in Markdown ist verboten.** Das Backend lehnt es ab.

### 5.2 Ein Umwandler, zwei Profile, Umwandlung im Backend

Seit S3e gibt es in PHP `App\Planer\RichText\MarkdownToTiptap` und `TiptapToMarkdown` samt kanonischer Markdown-Form und gemeinsamen Testfällen in `spec/fixtures/richtext/` (`docs/planer-abgleich.md` §9). Die Dokumentenverwaltung baut **keinen zweiten Umwandler**, sondern erweitert diesen um ein Profil:

| Profil | Verwendung | Umfang |
|---|---|---|
| „Beschreibung“ (heute) | Milestone, ToDo, Task, Kommentar, Planer-Datei | unverändert, `spec/format-v2.md` §6 |
| „Dokument“ (neu) | `documents.content` | zusätzlich Überschrift 1 und 4, Codeblock, Tabelle, Aufgabenliste, durchgestrichen, Trennlinie |

Nicht erlaubt in beiden Profilen: Bilder, HTML, Einbettungen, Farben, Schriftgrößen.
Im Profil „Dokument“ ist das Backend **streng** wie bei der Planer-Datei: Unerlaubtes ist ein Fehler, es wird nicht still zu Klartext. Das Profil gilt nur für Dokumente; `tools/planer` (JS) und die Planer-Datei kennen es nicht, weil die Datei keine Dokumente enthält.

**Wer wandelt um (Entscheidung 2026-11-18): der Server.**

- **Lesen:** `Document.content` (Markdown) und `Document.contentJson` (Skalar `RichText`, aus `MarkdownToTiptap`). Editor und Lesemodus laden `contentJson`.
- **Sichern:** Der Editor schickt TipTap-JSON (`contentJson`). Der Server prüft es gegen das Profil „Dokument“ und schreibt Markdown mit `TiptapToMarkdown`.
- Das Frontend schreibt nie selbst Markdown, außer dem Download `.md` aus `content`. In M8 liefert der Browser den Stand des Yjs-Dokuments als TipTap-JSON, nicht als Markdown.
- Vorteile: eine kanonische Form (keine Scheinänderungen im Versionsvergleich), kein Beta-Paket, MCP und Export brauchen keine Umwandlung im Browser.
- Preis: Der Editor braucht passende TipTap-Erweiterungen für Tabelle, Aufgabenliste, Codeblock und Trennlinie, und deren JSON muss dem Knotenformat des PHP-Konverters entsprechen (D0 prüft das). Zurück auf Umwandlung im Browser nur, wenn D0 zeigt, dass der PHP-Konverter nicht erweitert werden kann.

**Maßstab:** Jede Testdatei des Profils „Dokument“ kommt nach JSON → Markdown → JSON **unverändert** zurück. Sonst entstehen bei jedem Sichern Scheinänderungen, und der Versionsvergleich wird wertlos. Fällt ein Element durch (z. B. Tabellenzellen mit mehreren Absätzen, Aufgabenlisten in Listen), wird es in Version 1 aus dem Profil genommen statt umgangen.

### 5.3 Prüfung im Backend

Beim Sichern: Länge (höchstens 200 000 Zeichen Markdown nach der Umwandlung), nur Knoten des Profils, HTML und unsichere Links ablehnen. Fehler als Validierungsfehler am Feld `contentJson`.

## 6. Gleichzeitiges Bearbeiten in M7

- `updateDocument` verlangt die `revision`, die der Editor geladen hat. Stimmt sie nicht mehr, antwortet das Backend mit dem Fehler `DOCUMENT_CONFLICT` und der aktuellen Revision. Das Frontend bietet an: neu laden, oder die eigene Fassung als neues Dokument sichern.
- Sichern ist eine bewusste Aktion (Knopf, Tastenkürzel). Ungesicherte Eingaben liegen als Entwurf im Browser (`localStorage`).
- **Prüfen:** an das Muster „Neuladen über Revision“ aus M1 anlehnen.
- Mit M8 entfällt der Konflikt-Dialog für Personen im Editor. `revision` und Fassungen bleiben.

## 7. Export

### 7.1 Markdown

Download im Frontend aus `content`, Dateiname aus dem Titel (`<titel>.md`). Kopfzeilen (Front Matter) mit Titel, Projekt, Owner, Revision, Stand.

### 7.2 PDF

**PHP im Backend, synchron** (entschieden). Der Rechnungs-PDF-Plan (Projektverwaltung-Webapp) ist ein anderes Projekt und sah die PDF-Erstellung zunächst aus Nuxt vor; im Repo `mollo-planer` ist **keine** PDF-Bibliothek festgelegt (Stand 2026-11-18). Deshalb entscheidet D0: `dompdf` gegen `mPDF` mit einem Beispieldokument. Oliver bestätigt die Wahl nach D0, bevor D5 gebaut wird. Passt die Rechnung später dazu, nutzt sie dieselbe Bibliothek.

Ablauf: Markdown → HTML (über `league/commonmark` bzw. den Umwandler, HTML im Text maskiert, unsichere Links aus) → Blade-Druckvorlage (Titel, Projekt, Owner, Revision, Stand, Seitenzahlen, Sprache = `project.locale`) → PDF.

- Synchron, nicht über die Queue (läuft per Cron, ein Download muss sofort antworten).
- Kein Nachladen fremder Inhalte beim Rendern.
- Zu prüfen in D0: Tabelle über zwei Seiten, Codeblock, Umlaute, Seitenzahlen, Lauf beim Webhoster (PHP-Erweiterungen, Speicher, Laufzeit).

**Verworfen:** TipTap „Conversion“ (kostenpflichtig, Inhalt geht an TipTap), Browsershot/Chromium (kein Node beim Webhoster), Drucken im Browser (uneinheitlich).

### 7.3 Transport

Über GraphQL, damit es bei einer API bleibt (Anmeldung, 2FA und Begrenzung gelten automatisch): `exportDocumentPdf(id: ID!): DocumentFile!` mit `fileName`, `mimeType`, `contentBase64`. Dokumente enthalten nur Text, die Dateien bleiben klein.

## 8. API und Oberfläche M7

### 8.1 GraphQL (Kontrakt)

```graphql
enum DocumentAccess { NONE, READ, EDIT, MANAGE }   # MANAGE nur in viewerAccess
enum DocumentShareAccess { READ, EDIT }
enum DocumentKind { PROCEDURE, MINUTES, ANALYSIS, CONCEPT, OTHER }

type Document {
  id: ID!
  project: Project!
  title: String!
  kind: DocumentKind!
  content: String!            # Markdown (kanonisch), nur in der Einzelabfrage laden
  contentJson: RichText!      # dasselbe als TipTap-JSON für den Editor, nur in der Einzelabfrage
  owner: User
  projectAccess: DocumentAccess!        # NONE, READ, EDIT
  shares: [DocumentShare!]!             # nur mit MANAGE sichtbar, sonst leer
  revision: Int!
  updatedBy: User
  createdAt: DateTime!
  updatedAt: DateTime!
  viewerAccess: DocumentAccess!
  versions: [DocumentVersion!]!         # ohne content, neueste zuerst
}

type DocumentShare { user: User!, access: DocumentShareAccess! }
type DocumentFile { fileName: String!, mimeType: String!, contentBase64: String! }

type DocumentVersion {
  revision: Int!
  title: String!
  kind: DocumentKind!
  content: String!            # Markdown, nur in documentVersion laden
  createdBy: User
  contributors: [User!]!      # M7: nur createdBy
  restoredFrom: Int
  createdAt: DateTime!
}

# Query
documents(projectId: ID!, kind: DocumentKind, search: String): [Document!]!
document(id: ID!): Document
documentVersion(documentId: ID!, revision: Int!): DocumentVersion

# Mutation
createDocument(input: CreateDocumentInput!): Document!          # projectId, title, kind, contentJson, projectAccess
updateDocument(id: ID!, revision: Int!, input: UpdateDocumentInput!): Document!   # title, kind, contentJson
restoreDocumentVersion(id: ID!, revision: Int!): Document!
setDocumentSharing(id: ID!, input: DocumentSharingInput!): Document!   # projectAccess, shares[{userId, access}] (ersetzt die Liste)
transferDocument(id: ID!, ownerId: ID!): Document!
deleteDocument(id: ID!): Document!                              # in den Papierkorb, liefert deletionId
exportDocumentPdf(id: ID!): DocumentFile!
```

- `documents` liefert nur Dokumente mit Stufe ≥ `read`, sortiert nach `updated_at` absteigend.
- **Kein N+1:** Die Liste lädt `content` nicht aus der Datenbank. `viewerAccess` wird für die ganze Liste mit einer Abfrage auf `document_shares` (nur die eigene Person) berechnet, Owner per Batch. `versions` lädt `content` nicht.
- `updateDocument` ohne inhaltliche Änderung (nach der Umwandlung gleiches Markdown) legt keine Fassung an.
- Änderungen nur an Freigaben erhöhen die `revision` nicht.
- `TrashItemType` bekommt `DOCUMENT`.
- `MutationCoverageTest` muss die neuen Mutationen abdecken.

### 8.2 Oberfläche (nur `wa-*`, DE/EN)

| Route | Inhalt |
|---|---|
| `/projects/:id/documents` | Liste: Titel, Art, Owner, Freigabe, Stand. Filter nach Art, Suche, Knopf „Dokument anlegen“ (nur mit `document.create`). Leerer Zustand mit Knopf |
| `/projects/:id/documents/:docId` | Lesemodus. Mit `EDIT`: „Bearbeiten“. Mit `MANAGE`: „Freigeben“, „Owner übergeben“, „Löschen“. Immer: „Verlauf“, „Exportieren“ (Markdown, PDF) |
| `/projects/:id/documents/:docId/history` | Liste der Fassungen (Revision, Person, Zeit), Vergleich zweier Fassungen, „Zurückholen“ mit `EDIT` |

Bausteine:

- `DocumentList`
- `DocumentEditor`: TipTap mit Werkzeugleiste für das Profil „Dokument“. **Prüfen:** gemeinsame Basis mit dem bestehenden Rich-Text-Editor (`components/rich-text/Editor.vue`, `utils/rich-text.ts`). Der Baustein bekommt die Quelle des Inhalts von außen (M7: TipTap-JSON, M8: Yjs), damit M8 ihn nicht neu baut.
- `DocumentShareDialog` (`wa-dialog`): Stufe für alle Mitglieder (privat, lesen, bearbeiten) und Liste der Personen mit Stufe. Auswahl nur aus Projektmitgliedern.
- `DocumentHistory` und `DocumentDiff`: Vergleich als Textvergleich der beiden Markdown-Fassungen, zeilen- und wortweise, im Browser berechnet. Anzeige als Text, nie als HTML aus dem Inhalt.
- `DocumentExportMenu`, `DocumentConflictDialog`

Eintrag „Dokumente“ in der Projektnavigation. **Vor dem ersten Ticket mit Oberfläche** erhebt die Planung die bestehenden Seitenmuster (Liste, Detail, Dialog, Verlauf) und legt sie als Checkliste ab; Auftrag 17 verweist darauf.

## 9. M8: Bearbeiten in Echtzeit (Weg C)

### 9.1 Prinzip

TipTap arbeitet für gemeinsames Bearbeiten mit **Yjs**. Yjs führt Änderungen in den **Browsern** zusammen, ohne Konflikte. Ein Server muss die Änderungen nur speichern und weiterreichen, er muss sie nicht verstehen. Das kann Laravel mit MariaDB:

1. Der Browser sammelt seine Änderungen als kleine binäre Pakete (Yjs-Updates).
2. Alle 1 bis 2 Sekunden ruft er **eine** Mutation auf: eigene Pakete abgeben, fremde Pakete seit der letzten Nummer abholen.
3. Der Server speichert jedes Paket mit **Person und Zeit aus dem Login** und gibt die Pakete der anderen zurück.
4. Der Browser spielt die fremden Pakete ein. Der Text ist bei allen gleich.

Kein WebSocket, kein Node, kein zweiter Server. Die Verzögerung entspricht dem Abruftakt.

### 9.2 Voraussetzungen

| Punkt | Stand |
|---|---|
| Hosting | Webhoster reicht (PHP, MariaDB) |
| Frontend | `yjs`, `@tiptap/extension-collaboration`, eigener Provider für den HTTP-Abgleich (klein, aber Eigenbau) |
| Begrenzung | GraphQL erlaubt 120 Anfragen pro Minute und Person. Takt 2 s = 30 pro Minute je offenem Dokument. Bei Untätigkeit und verstecktem Tab auf 10 s strecken. **Prüfen:** eigene Grenze für die Abgleich-Mutation |
| Last | jeder offene Editor erzeugt Dauerabfragen. Für kleine Teams unkritisch, in E0 messen (Antwortzeit, PHP-Prozesse beim Webhoster) |
| Daten | Binärdaten als Base64 in GraphQL |

**Grenze des Wegs:** Mit mehr als etwa einem Dutzend gleichzeitig Schreibender in einem Dokument oder mit dem Wunsch nach Cursorn ohne Verzögerung wäre ein WebSocket-Dienst (Hocuspocus auf eigenem Server) der bessere Weg. Die Tabellen aus §9.3 bleiben dann gleich, nur der Transport wechselt.

### 9.3 Tabellen

**`document_updates`** (Nachweis Stufe 2, nur anfügen)

| Spalte | Typ | Hinweis |
|---|---|---|
| `id` | bigint PK, fortlaufend | ist zugleich die Reihenfolge |
| `document_id` | FK `documents`, `cascadeOnDelete` | |
| `user_id` | FK `users`, nullable, `nullOnDelete` | **aus dem Login**, nie aus der Eingabe |
| `client_id` | unsigned bigint | Yjs-Kennung des Browsers, vom Browser gemeldet |
| `payload` | mediumblob | Yjs-Update, vom Server nicht gelesen |
| `created_at` | datetime(3) | |

Index `(document_id, id)`.

**`document_states`**

| Spalte | Typ | Hinweis |
|---|---|---|
| `document_id` | PK, FK `cascadeOnDelete` | |
| `state` | longblob | zusammengefasster Yjs-Stand, von einem Browser geliefert |
| `up_to_update_id` | bigint | bis zu welchem Paket der Stand reicht |
| `updated_at` | | |

Neue Teilnehmer laden `state` und danach nur die Pakete mit höherer Nummer. Die Pakete in `document_updates` bleiben für den Nachweis erhalten.

**Anwesenheit** (wer ist im Dokument, wo steht der Cursor): im Laravel-Cache mit kurzer Lebensdauer, keine Tabelle.

### 9.4 Kontrakt

```graphql
input DocumentSyncInput {
  since: ID                 # letzte bekannte Paketnummer, null beim Einstieg
  clientId: String!
  updates: [String!]!       # Base64, darf leer sein
  presence: String          # JSON: Cursor, Auswahl
}

type DocumentSyncPayload {
  state: String             # nur beim Einstieg (since = null)
  updates: [DocumentUpdate!]!   # fremde Pakete seit since
  lastId: ID!
  presence: [DocumentPresence!]!
  viewerAccess: DocumentAccess!   # Entzug der Freigabe wirkt sofort
}

type DocumentUpdate { id: ID!, payload: String!, user: User, clientId: String!, createdAt: DateTime! }
type DocumentPresence { user: User!, state: String }

syncDocument(id: ID!, input: DocumentSyncInput!): DocumentSyncPayload!
compactDocument(id: ID!, upToUpdateId: ID!, state: String!): Boolean!
saveDocumentVersion(id: ID!, upToUpdateId: ID!, title: String!, contentJson: RichText!): Document!
documentUpdates(id: ID!, fromRevision: Int, toRevision: Int): [DocumentUpdate!]!   # für die Anzeige des Nachweises
```

- `syncDocument` prüft bei **jedem** Aufruf den Zugriff. Pakete abgeben nur mit Stufe ≥ `edit` und `document.update`, abholen mit Stufe ≥ `read`.
- **Fassung sichern:** PHP kann ein Yjs-Dokument nicht lesen. Deshalb liefert ein Browser den Stand als TipTap-JSON (`contentJson`, aus dem Yjs-Dokument über ProseMirror) und der Server wandelt in Markdown um (§5.2). Er prüft es wie in §5.3, schreibt `documents.content`, erhöht `revision`, legt die Fassung an und trägt als `contributors` alle Personen ein, die seit der vorigen Fassung Pakete geliefert haben (aus `document_updates`, also vom Server berechnet).
- Gesichert wird von Hand, nach 30 Sekunden Ruhe und wenn die letzte Person das Dokument verlässt.
- **Schreiben ohne Editor** (`updateDocument`, später MCP) ist während einer laufenden Sitzung gesperrt und liefert den Fehler `DOCUMENT_IN_SESSION`. Ohne Sitzung setzt es den Yjs-Stand zurück; der nächste Browser baut ihn aus `contentJson` neu auf.

### 9.5 Was der Nachweis leistet

| Frage | Antwort aus | Vom Server gesichert |
|---|---|---|
| Wer hat welche Fassung gesichert, wer hat daran mitgewirkt? | `document_versions` | ja |
| Wer hat wann eine Änderung geliefert? | `document_updates` (`user_id`, Zeit) | ja |
| Was genau hat die Person geändert? | Der Browser spielt die Pakete nach und markiert die Stellen je Person | **teilweise** |

Ehrliche Grenze beim dritten Punkt: Der Server speichert die Pakete, kann ihren Inhalt aber nicht prüfen. Wer wann ein Paket geliefert hat, ist sicher. Welche Zeichen darin stecken, liest erst der Browser. Ein Projektmitglied mit Schreibrecht und böser Absicht könnte ein Paket bauen, das Inhalt einer fremden Yjs-Kennung zuschreibt. Für einen Nachweis im Team reicht das, **für einen Beweis gegenüber Dritten nicht**. Dafür gilt der Versionsverlauf: Dort liegt der vollständige Text jeder Fassung, vom Server geprüft und gestempelt.

Yjs selbst kennt keine Personen. Seine Funktion dafür (`PermanentUserData`) gilt als experimentell, Yjs 14 soll Zuordnungen fest mitbringen. Der Plan hängt von beidem nicht ab, weil die Zuordnung Kennung → Person aus `document_updates` kommt. **Prüfen in E0:** Stand von Yjs 14 und was `y-prosemirror` für die Anzeige schon mitbringt.

## 10. Aufwand (Schätzung)

| Teil | Tickets | Bemerkung |
|---|---|---|
| M7 ohne Verlauf | D0 bis D8 | Kern |
| Versionsverlauf | D9, D10 | klein, weil die Tabelle von Anfang an da ist |
| Zen | D11 | Mechanik ist allgemein |
| M8 Echtzeit über HTTP | E0 bis E6 | größter Einzelposten ist der Abgleich im Frontend (E3) |

Zum Vergleich die verworfenen Wege für M8: Hocuspocus selbst betreiben (weniger Eigenbau, aber zweiter Server mit Betrieb), TipTap Cloud (am wenigsten Eigenbau, Abo ab 49 Dollar im Monat, Inhalte bei TipTap, Versionsvergleich erst im größeren Abo).

## 11. Ausblick

- **MCP:** `list_documents`, `get_document`, `create_document`, `update_document` mit den Rechten der Person. Markdown passt dafür ohne Umwandlung.
- **KI:** Dokumente als Kontext für den Assistenten (mit Freigabe wie beim KI-Kontext, B2).
- **Bilder und Anhänge:** braucht Dateiablage, Größenbegrenzung und Bereinigung beim endgültigen Löschen.
- **Mail bei Freigabe**, **Vorlagen** (z. B. Sitzungsprotokoll), **Kommentare am Dokument**.

## 12. Tickets für Bob

Reihenfolge wie nummeriert. Ein Schritt, ein Commit, Tests grün. Voraussetzung sind `App\Support\Rights`, der Papierkorb und S3e (alle vorhanden). Vorbild für Model, Policy, Papierkorb und GraphQL ist `Idea` (M6, **prüfen**). Arbeit auf dem Branch `feature/dokumente`, siehe §0.

**Aufteilung in Aufträge:**

| Auftrag | Tickets | Inhalt |
|---|---|---|
| `next-work/16-dokumente-backend.md` | D0 bis D5, D9 | Versuch, Datenbank, Rechte, Profil, GraphQL, PDF, Versionsverlauf (Backend). Keine Oberfläche |
| `next-work/17-dokumente-frontend.md` | D6 bis D8, D10, D11 | Oberfläche, Editor, Freigabe, Verlauf, Zen. Nach der Erhebung der Seitenmuster und nach D4 |
| M8-Aufträge | E0 bis E6 | später, nach Abnahme von M7 |

### M7 Dokumente

**D0 Versuch (ohne Produktivcode, Ergebnis als Notiz `docs/dokumente-versuch.md`)**
- PHP-Konverter: das Profil „Dokument“ (§5.2) in `MarkdownToTiptap` und `TiptapToMarkdown` als Versuch ergänzen. Rundlauf JSON → Markdown → JSON für jedes Element, besonders Tabellen, Aufgabenlisten, verschachtelte Listen, Codeblöcke (mit Sprache), Überschriften 1 bis 4.
- TipTap-Erweiterungen im Frontend für das Profil (Tabelle, Aufgabenliste, Codeblock, Trennlinie, durchgestrichen): Paketnamen und Version, wie sich ihr JSON zum Knotenformat des PHP-Konverters verhält (Knotennamen, Attribute).
- PDF: `dompdf` gegen `mPDF` mit einem Beispieldokument (Tabelle über zwei Seiten, Codeblock, Umlaute, Seitenzahlen), Lauf beim Webhoster prüfen.
- Abnahme: Empfehlung je Punkt, Liste der Elemente, die nicht stabil sind (werden aus dem Profil genommen). **Danach anhalten**, Stuart und Oliver bestätigen Profil und PDF-Bibliothek.

**D1 Datenbank und Models**
- Migrationen `documents`, `document_shares`, `document_versions` nach §3, Models, Relationen, Factory, DemoSeeder.
- `SoftDeletes` mit `deleted_by`, `deletion_id`. Projekt im Papierkorb nimmt seine Dokumente mit derselben `deletion_id` mit. Standard `project_access` = `read`.
- Abnahme: `migrate:fresh --seed` läuft, Tests auf SQLite und MariaDB grün.

**D2 Rechte und Policy**
- Rechte `document.create`, `document.update`, `document.manageAny` in die Rechteliste, Kurztexte DE/EN, Standardbelegung, `permissions:sync-defaults`.
- Dienst für den wirksamen Zugriff nach §4.2, `DocumentPolicy`, Scope für sichtbare Dokumente.
- Grundregeln §4.3, auch beim Entfernen eines Mitglieds (Freigaben löschen, Owner leeren).
- Abnahme: Test-Matrix Rolle × Freigabe × Aktion, `OutsiderAccessTest`, archiviertes Projekt, Systemrolle „Lesend“ mit Freigabe `edit` kann nicht schreiben.

**D3 Profil „Dokument“ im Umwandler**
- S3e-Umwandler um das Profil erweitern (§5.2), kanonische Form für die neuen Elemente in `spec/format-v2.md` §6.2, Testfälle in `spec/fixtures/richtext/` (oder `spec/fixtures/document/`).
- Abnahme: beide Richtungen, Rundlauf unverändert, Fehler für HTML, Bild, `javascript:`-Link, Überlänge. Profil „Beschreibung“ unverändert (bestehende Tests grün, auch `tools/planer`).

**D4 GraphQL**
- Typen, Queries, Mutationen nach §8.1 ohne Verlauf und PDF, `contentJson`/`content`, `DOCUMENT_CONFLICT`, Suche (FULLTEXT, Ersatz für SQLite), `TrashItemType.DOCUMENT`, `composer schema:frontend`.
- Jedes Sichern schreibt eine Zeile in `document_versions` (Transaktion).
- Abnahme: `lighthouse:validate-schema`, `MutationTest` und `MutationCoverageTest` grün, Liste lädt `content` nicht, Anzahl der Abfragen für die Liste ist fest (Test).

**D5 PDF-Export**
- Dienst Markdown → HTML → PDF mit der bestätigten Bibliothek, Blade-Vorlage DE/EN, `exportDocumentPdf`.
- Abnahme: Test prüft PDF-Kopf und Dateiname, Zugriff nur mit Stufe ≥ `read`, HTML im Inhalt erscheint als Text.

**D6 Frontend: Liste und Lesen** (Auftrag 17)
- Vorher Seitenmuster erheben (Planung). Route, `DocumentList`, Lesemodus über TipTap, Navigation, i18n, `npm run codegen`.
- Abnahme: Knöpfe nur nach `viewerAccess` und `viewerRights`, leerer Zustand, `lint`, `typecheck`, Vitest grün.

**D7 Frontend: Editor und Sichern** (Auftrag 17)
- `DocumentEditor` mit Werkzeugleiste, Quelle des Inhalts von außen, Laden und Sichern über `contentJson`, Entwurf im Browser, `DocumentConflictDialog`, Warnung beim Verlassen mit ungesicherten Änderungen.
- Abnahme: nach Sichern und Neuladen ist der Inhalt unverändert, Konflikt-Fall getestet.

**D8 Frontend: Freigabe, Owner, Export, Papierkorb** (Auftrag 17)
- `DocumentShareDialog`, Owner übergeben, `DocumentExportMenu` (Markdown im Browser aus `content`, PDF über Mutation), Löschen mit „Rückgängig“, Dokumente auf der Papierkorb-Seite.
- Abnahme: Auswahl nur Projektmitglieder, Export in beiden Formaten.

**D9 Versionsverlauf Backend** (Auftrag 16, nach D5)
- `Document.versions`, `documentVersion`, `restoreDocumentVersion` nach §8.1.
- Abnahme: Fassungen unveränderlich (kein Schreibweg, Test), Zurückholen legt neue Fassung mit `restored_from` an, Rechte nach §4.2.

**D10 Versionsverlauf Frontend** (Auftrag 17)
- Seite `history`, `DocumentHistory`, `DocumentDiff`, „Zurückholen“.
- Abnahme: Vergleich zeigt Einfügungen und Löschungen, Anzeige ohne `v-html`, Dokumentation (`docs/README.md`, Kontext-Dokument) nachgeführt.

**D11 Zen für Dokumente** (Auftrag 17)
- Liste, Lesen und Verlauf in „Ruhig“ nach `docs/zen-modus.md` (Markierungen `data-zen`, nur `zen.css`). „Fokus“ mit Ziel „Dokument“ (`enterFocus` mit Art und ID): ein Dokument lesen oder bearbeiten im Vollbild, Sichern-Status in `#zen-status`.
- Erreichbarkeitsregel: alles, was Zen ausblendet, bleibt über die Suche oder das „…“-Menü erreichbar. Eintrag „Zen · Dokumente“ in der Suche.
- Abnahme: Nachführung in `docs/zen-modus.md`, hell und dunkel angesehen.

### M8 Echtzeit

**E0 Versuch**
- Zwei Browser, Yjs mit TipTap, Abgleich über eine Test-Mutation. Messen: Verzögerung, Paketgröße, Anfragen pro Minute, Verhalten beim Webhoster.
- Stand Yjs 14 und Anzeige der Zuordnung in `y-prosemirror` prüfen.
- Abnahme: Empfehlung für Takt und Grenzen, Entscheidung „weiter mit Weg C“ oder zurück zu Oliver.

**E1 Tabellen:** `document_updates`, `document_states` nach §9.3, `document_versions.contributors` wird gefüllt.

**E2 Abgleich im Backend:** `syncDocument`, `compactDocument`, Anwesenheit im Cache, eigene Begrenzung, Zugriff bei jedem Aufruf. Tests: Entzug der Freigabe mitten in der Sitzung, Person aus dem Login, Reihenfolge der Pakete.

**E3 Abgleich im Frontend:** eigener Yjs-Provider (Takt, Strecken bei Ruhe, Wiederaufnahme nach Netzausfall, Offline-Puffer), `DocumentEditor` mit Yjs als Quelle, Anzeige der anwesenden Personen.

**E4 Fassungen in der Sitzung:** `saveDocumentVersion` (mit `contentJson`), automatisches Sichern, `DOCUMENT_IN_SESSION` für `updateDocument`, Aufbau des Yjs-Stands aus `contentJson`.

**E5 Nachweis:** `documentUpdates`, Ansicht „wer hat was geschrieben“ im Verlauf (Markierung je Person zwischen zwei Fassungen), Hinweistext zur Grenze aus §9.5.

**E6 Abschluss:** Lasttest mit mehreren Personen, Dokumentation, Abnahme durch Oliver.

## Quellen

- TipTap PDF-Export (Convert-Dienst, kostenpflichtig): https://tiptap.dev/docs/conversion/export/pdf/editor-extension
- TipTap Preise: https://tiptap.dev/pricing
- Hocuspocus (WebSocket-Dienst für Yjs): https://tiptap.dev/hocuspocus
- Yjs, Zuordnung von Änderungen zu Personen: https://discuss.yjs.dev/t/how-to-distinguish-which-user-triggered-this-update/2584
- Yjs 14, Versionen und Zuordnungen (FOSDEM 2026): https://archive.fosdem.org/2026/schedule/event/8VKQXR-blocknote-yjs-prosemirror/
