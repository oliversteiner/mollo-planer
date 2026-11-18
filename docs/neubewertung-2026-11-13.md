# Neubewertung des Codes und der Planung

Stand: 2026-11-13 12:30, geprüft bis Commit `dc066bc`. Rolle: Analyse und Plan, kein Code geändert.
Geprüft: Git-Verlauf seit `d736d6a` (47 Commits), Backend (`app/`, `config/`, Migrationen), `tools/planer`, `spec/`, Deployment, Dokumente. `tools/planer`: `npm test` 134 von 134 grün (hier ausgeführt). Backend-Tests laut letztem Commit 588 grün (hier nicht ausführbar, kein PHP in dieser Umgebung).

---

## 1. Kurzfazit

- **Fortschritt gut, Qualität hoch.** Jeder Schritt hat eigene Commits, Tests und eine Stand-Zeile. Die Refactorings R1 bis R4 haben die Grundlage tatsächlich vereinfacht (ein Lösch-Pfad, eine Reihenfolge-Logik, Operationen gegen das Schema geprüft, ein Rechte-Dienst).
- **Werkzeug und Format sind der App voraus.** `tools/planer` und `spec/` können schon `planer-v2` vollständig (IDs aus der Datenbank, Labels mit Sprachen, Status `idea`/`planned`, Vorlagen, kanonisches Markdown). Die App kennt davon erst einen Teil. Das ist gewollt, erzeugt aber bis I1/I2 eine Lücke, die nicht durch Tests abgedeckt ist (siehe 3.1).
- **Die Planung ist groß geworden** (rund 25 offene Schritte). Die Reihenfolge stimmt fachlich, aber es fehlt ein klarer Zwischenstand, an dem die App wieder auslieferbar und abgenommen ist (siehe 5).
- **Dokumente laufen auseinander.** Zehn ältere Dokumente beschreiben noch `ProjectPermissions`, `mollo-version` oder feste Rollen (siehe 3.6).

## 2. Stand je Schritt

| Schritt | Stand |
|---|---|
| R1 bis R4, R7 (Refactorings) | erledigt (R2 Rest geht in I1/I2 auf) |
| U0 Umzug, T0 Name, T1, T4, T5, T6 | erledigt |
| S1 `useDelete`, S2 Inline-Code | erledigt |
| S3 Datenstruktur | A1, A2, A3, A5-Spalten, A6 erledigt; **offen: Task-Status `idea`/`planned` in der App** |
| R1 Rollen/Funktionen/Rechte | Stufe 1, 2, 3a erledigt; **offen Stufe 3b** (Enum `Right`, `role_id`, restliche Rechte, Grundregeln §7 auf Rechte, `activity`, `/admin/roles`) |
| S3b, S3c, S3f, S3e, S5, S6, S7 | offen |
| I1 bis I5, T2, T3, W1, B5a, B6 | offen |

**Abweichung von der Reihenfolge:** R1 wurde begonnen, bevor S3 fertig war (Status `idea`/`planned` fehlt in der App). Fachlich unkritisch, weil sich beides kaum berührt. Die Stand-Zeile in `umsetzung.md` nennt A6 noch als offen, obwohl `2d04b51` es umsetzt.

## 3. Befunde

### 3.1 Lücke zwischen Format und App (mittel)

`spec/planer-v2.schema.json` erlaubt Task-Status `idea` und `planned`, Labels mit `names` je Sprache und Vorlagen mit Gerüst. Die App hat `TaskStatus` mit vier Werten, Label-Sets statt Projekt-Labels und kein Vorlagenmodell. Bis I1/I2 gibt es keinen Test, der App und Format vergleicht.

**Empfehlung:** In S3 (Rest) einen kleinen Test ergänzen, der die Enum-Werte der App (`TaskStatus`, `ProjectRole`/Rollenschlüssel, `LabelColor`, Locale) mit den Werten im Schema vergleicht. Er schlägt heute fehl und wird mit S3, R1 und S7 grün. Damit ist die Lücke sichtbar statt still.

### 3.2 Schlüssel der Funktionen auf Deutsch (klein, jetzt billig)

`config/permissions.php` nutzt deutsche Schlüssel (`projekte-anlegen`, `aufgaben-bewegen`, `projekt-besitzen`). Laut Regel sind technische Werte englisch und klein (`docs/planer-abgleich.md` §5.1, `CLAUDE.md`). Die Namen (`names.de`/`names.en`) sind richtig.

**Empfehlung:** Schlüssel auf Englisch umstellen (`create-projects`, `move-tasks`, `own-project` …), bevor Stufe 3b die Einstellungsseite und `role_id` baut. Heute nur Konfiguration und Tests.

### 3.3 Standardbelegung weicht von §5 ab (klein, gewollt als Zwischenstand)

Die Funktionen in `config/permissions.php` (`aufgaben-bewegen`, `aufgaben-eigene`, `aufgaben-bearbeiten`, `loeschen`, `labelsets`) sind ein verhaltensgleicher Zwischenstand. `docs/rollen-funktionen-rechte.md` §5 sieht andere Bündel vor (Planen, Mitarbeiten, Aufgaben verwalten, Projekt verwalten, Projekt besitzen; System: Vorlagen, KI und Ideen, Betrieb, Status).

**Empfehlung:** In R1 Stufe 3b auf §5 umstellen und den Matrix-Test auf §5 ausrichten. In die Stand-Zeile aufnehmen, damit es nicht vergessen wird.

### 3.4 Grundregeln noch über Rollennamen (gewollt, offen in 3b)

`Rights::canChangeMembership`, `ProjectMembership` (letzter Owner), `CreateProject` (Ersteller wird Owner) und `UserPolicy` (Admin kann sich nicht selbst herabstufen) prüfen noch `ProjectRole::OWNER` bzw. `UserRole::ADMIN`. Laut §7 sollen sie auf Rechte gehen (`project.delete`, `owner.manage`, `users.manage`, `roles.manage`). Sonst greifen die Schutzregeln nicht mehr, sobald eine eigene Rolle Owner-Rechte bekommt.

**Empfehlung:** In Stufe 3b zwingend, mit je einem Test „eigene Rolle mit `project.delete` ist geschützt wie Owner“. Für „Ersteller wird Owner“: die Standard-Projektrolle mit `project.delete` als Ersteller-Rolle in den Einstellungen festlegen (statt fest `owner`).

### 3.5 Statischer Cache in `Rights` (klein)

`Rights::$grants` ist statisch und wird einmal geladen. In Anfragen ist das richtig. Ein Queue-Worker (Hostpoint: `schedule:run` per Cron, Jobs laufen im selben Prozess nacheinander) behält den Cache über mehrere Jobs. Ändert jemand in `/admin/roles` die Rechte, sehen danach laufende Jobs (z. B. Mails mit Sichtbarkeitsprüfung) kurz den alten Stand.

**Empfehlung:** `Rights::clearCache()` am Anfang jedes Jobs (Listener auf `JobProcessing`) und nach jeder Änderung an Rollen/Funktionen. Ein Test dafür.

### 3.6 Veraltete Dokumente (mittel, für alle Agenten)

Diese Dokumente beschreiben noch den alten Stand (`ProjectPermissions`, feste Rollen, `mollo-version`, Label-Sets, alter Connector): `claude-desktop-kontext.md`, `backend-plan.md`, `graphql-kontext.md`, `entwicklung.md`, `claude-connector.md`, `connector-erweiterung-plan.md`, `labels-plan.md`, `rollen-plan.md`, `refactoring-vor-umsetzung.md` (Stand `347a4d5`), `umsetzung-planer-v2.md`, `planer-datei-ssot.md`, `planer-vs-mollo-version.md`. Dazu Restbestände von `planer/2` in `umsetzung.md` (12 Stellen).

Risiko: Ein neuer Agent liest zuerst `claude-desktop-kontext.md` oder `backend-plan.md` (Vorrang laut Kontext-Datei!) und baut gegen den alten Stand.

**Empfehlung:** Schritt **D1 (Dokumente bereinigen)** jetzt, vor R1 Stufe 3b:
- Neues kurzes Einstiegsdokument `docs/README.md`: welche Dokumente gelten (Reihenfolge des Vorrangs), welche historisch sind.
- Vorrang neu: `planer-abgleich.md`, `rollen-funktionen-rechte.md`, `assistent-ideen-konzept.md`, `umsetzung.md`, `spec/format-v2.md`; danach `schema-review.md`.
- Historische Dokumente oben mit „Historisch, ersetzt durch …“ markieren (nicht löschen).
- `claude-desktop-kontext.md` und den Kopfkommentar in `schema.graphql` neu fassen.
- `umsetzung.md` in `umsetzung.md` umbenennen (der Name „Spur B“ ist überholt) und `planer/2`-Reste ersetzen.

### 3.7 Doppelte Grenzwerte bis I1 (klein, gewollt)

Grenzwerte (Titel 160, Beschreibung 5000, …) stehen heute in `spec/planer-v2.schema.json`, `App\Plan\PlanSchema` und `plan.graphql` (mit Test zwischen den letzten beiden). Das Schema ist laut Plan die einzige Quelle, wird aber von PHP noch nicht gelesen.

**Empfehlung:** In I1 `PlanSchema` die Grenzwerte aus dem JSON-Schema lesen lassen und den Test auf „PlanSchema = Schema“ umstellen. Namensraum vereinheitlichen: heute `App\Plan`, geplant `App\Planer` → einheitlich `App\Planer`.

### 3.8 Was gut gelöst ist

- `Rights` als ein Dienst mit Standardmatrix aus Konfiguration und Tabellen, verhaltensgleich umgestellt (Stufe 1 ohne Tabellen, Stufe 2 mit Tabellen).
- `tools/planer` ohne Abhängigkeiten, 134 Tests, Vorlagen und kanonisches Markdown sauber getrennt in `core/` und `cli/`.
- Deployment lässt `tools/` und `spec/fixtures/` weg, behält das Schema.
- Der Agent hat Widersprüche in der Planung erkannt und angehalten (Vorlagen-Format), statt zu raten.

## 4. Bewertung der Planung

| Bereich | Bewertung |
|---|---|
| Format `planer-v2` | stabil, vollständig spezifiziert, gut getestet |
| Rechte | Konzept entschieden und tragfähig; Umsetzung halb, die riskanten Teile (Grundregeln, `role_id`) stehen noch aus |
| Import/Export (I1, I2) | gut beschrieben, aber der größte und riskanteste Block (Roundtrip, `full`-Löschen, Rechte je Änderung) |
| Vorlagen (S7) | entschieden; Abhängigkeit zu I1 (Labels je Projekt) beachten |
| Assistent (B5a, B6) | entschieden, hängt an I2, I3, I5 |
| Umfang gesamt | groß; ohne Zwischenabnahme wächst das Risiko, dass die App lange nicht auslieferbar ist |

**Abhängigkeit, die im Plan nicht ausdrücklich steht:** I1 exportiert `project.labels` mit `names`. Das gibt es erst mit S7 (Labels je Projekt). I1 muss also nach S7 kommen. Die heutige Reihenfolge erfüllt das, sollte aber als Abhängigkeit im Dokument stehen, damit sie bei Umstellungen nicht verloren geht.

## 5. Empfehlung: Meilensteine mit Abnahme

Gleiche Schritte, aber in auslieferbare Pakete gebündelt. Nach jedem Meilenstein: Browser-Prüfung, Demo-Daten neu, Abnahme durch Oliver, dann erst weiter.

| Meilenstein | Schritte | Ergebnis für die Nutzung |
|---|---|---|
| **M1 Fundament** | D1, S3 Rest (Status `idea`/`planned`), R1 Stufe 3b, S3b, S3c, S3f | Rechte einstellbar, Release/Commit sichtbar, neue Status, App lädt gezielt neu |
| **M2 Sicher löschen** | S5, S6 | Archiv, Papierkorb mit Rückgängig |
| **M3 Vorlagen und Labels** | S3e, S7 | Projekte aus Vorlagen, Labels je Projekt mit Sprachen |
| **M4 Datei-Abgleich** | I1, I2, I3, I4, I5 | Export/Import, Vorschau, Downloads, neuer Connector |
| **M5 Synchronisation** | T2, T3, W1 | `pull`/`push`, GitHub Action, Webhook |
| **M6 Assistent und Ideen** | B5a, B6 | Assistent für alle, Ideen-Seite, KI-Kontext |

S3e (Konverter) steht in M3, weil er für I1/I2 gebraucht wird und unabhängig testbar ist.

## 6. Nächste Schritte (Vorschlag)

1. **D1 Dokumente bereinigen** (3.6), inklusive Einstiegsdokument und Umbenennung des Umsetzungsdokuments.
2. **S3 Rest:** Status `idea`/`planned` in der App, plus Vergleichstest App ↔ Schema (3.1).
3. **R1 Stufe 3b** mit englischen Funktionsschlüsseln (3.2), Standardbelegung nach §5 (3.3), Grundregeln auf Rechte (3.4), Cache-Leeren (3.5).
4. Danach weiter nach Meilenstein M1.

## 7. Entscheidungen (Oliver, 2026-11-13)

1. Meilensteine M1 bis M6 mit Abnahme nach jedem Meilenstein: **übernommen** (`umsetzung.md`).
2. D1 (Dokumente bereinigen): **erledigt in der Planung** (`README.md`, `umsetzung.md` statt `spur-b-sichere-schritte.md`, Hinweise in historischen Dokumenten, `claude-desktop-kontext.md` neu, `CLAUDE.md`/`AGENTS.md` auf den neuen Vorrang umgestellt). Offen für die Umsetzung: Kopfkommentar in `schema.graphql` (R1 Stufe 3b).
3. Befunde 3.1 bis 3.5 und 3.7: **aufgenommen** in `umsetzung.md` („Ergänzungen aus der Neubewertung“). Zusätzlich: `docs/entwicklung.md` (Seed im v1-Format) wird in I4 auf Planer v2 umgestellt.
