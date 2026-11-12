# Mollo Planer · GraphQL-Schema und Entscheidungen

Stand: 2026-11-07 (nach Task-Kommentaren, Mail-Benachrichtigungen, Projektsprache, formatiertem Text und Icons)

## Was es gibt
- **Schema:** `backend/graphql/schema.graphql` (Laravel Lighthouse, mit Direktiven).
- **Demo:** Artifact „GraphQL-Terminal Projektverwaltung“, https://claude.ai/artifact/LZoPWr7XLcUykPeLk6d6gY
  - Simulierte API im Browser mit zwölf Lektionen (Lektion 8 „Kommentare an Tasks“, Lektion 9 „Beschreibung und Icon“), Personenwechsel (Anna Admin, Ben Owner, Chiara Manager/Owner, David Mitglied), Schema-Browser und Rechte-Tabelle. Verschickte Mails erscheinen als Servermeldung unter der Antwort.
  - Die Demo ist nur zum Ausprobieren, sie ist nicht Teil dieses Repositorys.

## Entscheidungen
- Ein ToDo hat mehrere Tasks. Es wird automatisch DONE, wenn alle seine Tasks erledigt sind, und geht wieder auf OPEN, wenn ein Task dazukommt oder zurückgesetzt wird. Milestones leiten ihren Status genauso aus den ToDos ab. Kein Eingabefeld dafür.
- Tasks haben ein eigenes Fälligkeitsdatum (`dueDate`, optional). Neue Migration nötig: `tasks.due_date` (date, nullable).
- Alle Projektmitglieder dürfen Milestones und ToDos anlegen und ändern und alle Tasks verschieben.
- Ein klares Rollensystem bleibt trotzdem: Rollen Owner, Manager, Mitglied pro Projekt, dazu die globale Rolle `users.role` (siehe `rollen-plan.md`). Zwei zentrale Rechte-Tabellen (`App\Support\ProjectPermissions` und `App\Support\SystemPermissions`) legt pro Fähigkeit fest, welche Rolle sie hat. Alle Policies fragen nur diese Tabelle. Einschränkungen später = eine Zeile ändern.
- Das Frontend fragt `Project.viewerAbilities` ab und zeigt danach Knöpfe an oder aus.

## Icons (Font Awesome Pro, fester Stil „Duotone Light“)
- **Labels** (seit 2026-11-10, Plan `labels-plan.md`): app-weite Label-Sets (mitgelieferte unveränderlich und übersetzt, eigene mit Ersteller), jedes Projekt wählt ein Set (`setProjectLabelSet`, Recht „Projekt ändern“), Tasks bekommen mehrere Labels aus diesem Set (`setTaskLabels`, Recht „Task ändern“; nach einem Set-Wechsel bleiben alte Labels erlaubt). Farbe als Farbton `LabelColor` der Theme-Palette.
- Projekt, Milestone, ToDo und Task haben `icon: String` (optional; beim Projekt seit 2026-11-09, für die Seitenleiste). Gespeichert wird **nur der Name**, zum Beispiel `rocket` oder `rocket-launch`. **Der Stil ist für die ganze App fest „Duotone Light“** und steht im Frontend, nicht in der Datenbank. Vorteil: einheitlicher Look, einfacherer Picker, und ein späterer Stilwechsel braucht keine Datenmigration.
- Der Server prüft nur das Format des Namens (Kleinbuchstaben, Ziffern, Bindestriche; Regel `App\Rules\FontAwesomeIconRule`), nicht ob das Icon existiert. Der Katalog liegt im Frontend. `null` oder ein unbekannter Name zeigt dort das Standard-Icon je Ebene.
- Wer das Objekt ändern darf, darf auch sein Icon ändern.
- Frontend: Icon-Picker mit Suche, nur Icons anzeigen, die es in Duotone Light gibt. Anzeige als Web Font (`@fortawesome/fontawesome-pro` mit CSS), je nach Font-Awesome-Version mit den Klassen `fa-duotone fa-light fa-rocket` (Version 7) bzw. dem entsprechenden Klassenpaar der eingesetzten Version. Nur die benötigte Schrift laden, nicht alle Stile. Zweifarbigkeit über die CSS-Variablen `--fa-primary-color` und `--fa-secondary-color` steuern, damit sie zum Theme passt.
- Lizenz: bestätigt, die Pro-Lizenz enthält „Duotone Light“.
- Lizenz und Deployment: Das Pro-Paket kommt aus dem privaten npm-Register von Font Awesome und braucht ein Token. Lokal in `.npmrc`, in GitHub Actions als Secret. Das Token nie ins Repository einchecken. Wenn die Build-Pipeline die Fonts in den Build kopiert, liegen sie auf dem eigenen Server, es braucht keinen Kit-Code und keine Domain-Freigabe.

## Formatierter Text (TipTap): Beschreibungen und Kommentare
- Milestone, ToDo und Task haben `description`, Kommentare haben `body`, jeweils vom Typ `RichText`. Das ist ein TipTap-Dokument als JSON-Objekt (`type: "doc"`). Nur die Projekt-Beschreibung bleibt reiner Text.
- **Gespeichert wird JSON, nie HTML.** Das Frontend zeigt es mit TipTap an (nur lesend oder im Editor), nie mit `v-html`. So lässt sich kein Skript einschleusen.
- Der Server prüft jedes Dokument gegen eine Whitelist (Regel `App\Rules\RichTextRule`, Skalar `App\GraphQL\Scalars\RichText`): Absatz, Überschrift Ebene 2 und 3, Aufzählung, nummerierte Liste, Zeilenumbruch, Zitat, fett, kursiv, Link (nur http, https, mailto). Maximal 20000 Zeichen, höchstens 10 Ebenen tief. Fehler kommen pro Feld unter `extensions.validation` (`input.description` bzw. `input.body`).
- Beschreibung ohne Text wird als null gespeichert. Ein Kommentar ohne Text wird abgelehnt (`body` ist Pflicht).
- Frontend: `@tiptap/vue-3`, `@tiptap/starter-kit` und `@tiptap/extension-link`. StarterKit einschränken: Überschriften nur [2, 3], Code, Codeblock, Durchgestrichen und Trennlinie aus, Link mit `rel="noopener noreferrer nofollow"` und `target="_blank"`. Die Whitelist im Editor und im Server müssen übereinstimmen. Eine gemeinsame Editor-Komponente für Beschreibung und Kommentar nutzen, für Kommentare mit kleinerer Werkzeugleiste.
- Datenbank: Spalte `description` bei `milestones` und `todos` von text auf `json` ändern, bei `tasks` neu als `json` nullable, `comments.body` als `json`. Im Model Cast `array`. Sind schon Texte vorhanden, in der Migration in ein Absatz-Dokument umwandeln.
- Nicht enthalten: Bilder, Tabellen, Anhänge, gemeinsames Bearbeiten, eine Klartext-Vorschau für Listen (könnte später als `descriptionText` dazukommen). Beschreibung und Kommentartext stehen nicht in den Mails.

## Kommentare an Tasks
- Typ `Comment` (id, task, author, body, createdAt, updatedAt). `Task.comments` (älteste zuerst) und `Task.commentCount`. Abfrage `task(id)` für die Detailansicht im Kanban.
- Mutationen `addComment`, `updateComment`, `deleteComment`. Der Autor wird aus dem Login gesetzt, nicht aus der Eingabe.
- Alle Mitglieder dürfen kommentieren (`COMMENT_CREATE`). Eigene Kommentare ändern und löschen darf die Autorin oder der Autor immer. Fremde Kommentare löschen nur Owner und Manager (`COMMENT_MODERATE`). Fremde Kommentare ändert niemand, auch kein Admin.
- `Comment.viewerCanEdit` und `viewerCanDelete` sagen dem Frontend, ob es die Knöpfe zeigen soll.
- Kommentare werden mit ihrem Task gelöscht (`cascadeOnDelete`). Wird eine Person gelöscht, bleiben ihre Kommentare, `author` ist dann null (`user_id` nullable, `nullOnDelete`).

## E-Mail-Benachrichtigungen (vom Auftraggeber bestätigt)
- **Neuer Task** oder **neue Zuweisung** (`assignTask`): Mail an die zugewiesene Person.
- **Neuer Kommentar**: Mail an die zugewiesene Person und an alle, die den Task schon kommentiert haben.
- Die handelnde Person bekommt nie eine Mail. Wer das Projekt nicht mehr sehen darf, auch nicht. Gleiche Person nochmal zuweisen löst nichts aus.
- Jede Person kann Mails für sich abschalten: `User.notifyByEmail` (Standard ja), setzen mit `updateMyNotifications(emailEnabled)`. Nur für sich selbst.
- **Sprache der Mails = Sprache des Projekts.** Feld `Project.locale` (Enum `Locale`: DE, EN; Standard DE). Setzen bei `createProject` und `updateProject` (Recht „Projekt ändern“). Kein `users.locale` nötig.
- Umsetzung im Backend: Laravel-Notifications mit `ShouldQueue`, Versand nach dem Speichern (`afterCommit`), nie im Request. In der Notification `locale()` auf `project->locale` setzen. Auf Hostpoint läuft die Queue per Cron (`queue:work --stop-when-empty`), Mails kommen also mit bis zu einer Minute Verzögerung. SMTP-Zugangsdaten in `.env`. Mailtexte in `lang/de` und `lang/en`.
- Nicht enthalten: Mails bei anderen Ereignissen (Statuswechsel, Fälligkeit), Sammelmails (Digest), @-Erwähnungen, Anhänge.

## Rechte-Tabelle (bestätigt)
| Fähigkeit | Owner | Manager | Mitglied |
|---|---|---|---|
| Projekt ändern (auch Sprache) | ja | ja | nein |
| Projekt löschen | ja | nein | nein |
| Mitglieder verwalten | ja | ja | nein |
| Milestone anlegen / ändern (auch Beschreibung, Icon) | ja | ja | ja |
| Milestone löschen | ja | ja | nein |
| ToDo anlegen / ändern (auch Beschreibung, Icon) | ja | ja | ja |
| ToDo löschen | ja | ja | nein |
| Task anlegen / zuweisen | ja | ja | ja |
| Task ändern (auch Beschreibung, Icon) | ja | ja | ja |
| Task verschieben | ja | ja | ja |
| Task löschen | ja | ja | nein |
| Kommentieren | ja | ja | ja |
| Fremde Kommentare löschen | ja | ja | nein |
| Label-Sets anlegen / duplizieren (gilt in irgendeinem Projekt; eigene Sets ändert der Ersteller oder ein Admin) | ja | ja | nein |

Admins (Rolle `admin`) dürfen immer alles (ausser fremde Kommentare ändern). Projekte anlegen dürfen Admin, Projektleitung und Koordination. Benutzer verwalten dürfen nur Admins.

## Weitere Entscheidungen im Schema
- camelCase in GraphQL, snake_case in der Datenbank (`@rename`).
- Lesen filtert der Scope `visibleToViewer` (Unsichtbares ist null oder leer). Schreiben prüft eine Policy per `@can`.
- Login, Logout, Passwort-Reset und 2FA laufen über Fortify (REST), nicht über GraphQL. Die 2FA-Pflicht sperrt die ganze GraphQL-Route per Middleware in `config/lighthouse.php` (Details zu Passkeys siehe `backend-plan.md`).
- Projekte anlegen nach der Systemrolle (`projects.create`), löschen nach Rechte-Tabelle (Owner). Wer ein Projekt anlegt und kein Admin ist, wird dessen Owner. Kein Archivieren.
- Tasks lassen sich nur Personen zuweisen, die im Projekt Mitglied sind.

## Offen
- Nichts mehr. Alles hier Beschriebene ist gebaut (Stand 2026-11-08). Die Enums sind an die PHP-Enums in `App\Enums` gebunden. Den Baufortschritt und weitere Entscheidungen führt `backend-plan.md`.
