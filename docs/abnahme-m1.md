# Abnahme Meilenstein M1 „Fundament“

Stand: 2026-11-16 (ergänzt nach RF5, S3c, S3f; Code bis `8e2bf87`). Rolle: Checkliste für Oliver, nach Abschluss von M1 durch die Umsetzung.
Vorher durch die Umsetzung: alle Prüfungen grün (`php artisan test`, `composer test:mariadb`, `npm test` in `frontend/` und `tools/planer/`, Lint, Typecheck, Build), dann lokal `php artisan migrate:fresh --seed --seeder=DemoSeeder`.

Personen aus den Demo-Daten: Anna (Admin), Ben, Chiara, David. Je Punkt: ✅ passt, ❌ mit kurzer Notiz.

---

## 1. Rollen, Funktionen, Rechte (`/admin/roles`)

- [x] ✅ Als Anna `/admin/roles` öffnen: Reiter System | Projekt, je Reiter Rollen / Funktionen / Übersicht als kompakte Zeilen, Namen in DE und EN (Fix F1).
- [ ] ❌ Ansicht Übersicht zeigt die Standardbelegung (Owner, Manager, Mitglied, Gast; Admin, Projektleitung, Koordination, Mitglied, Lesend). `/system/rechte` leitet dorthin um. → F4.4 (Spalten abgeschnitten, kein Scroll-Hinweis)
- [x] ✅ Filter: Textsuche, Mitgeliefert/Eigene, „Hat Recht …“, In Gebrauch/ungenutzt wirken in allen drei Ansichten; Zustand bleibt nach Neuladen (URL).
- [ ] ❌ Eigene Projektrolle „Redaktion“ anlegen mit den Funktionen „Planen“ und „Mitarbeiten“. Ben in einem Projekt diese Rolle geben. Ben kann ToDos und eigene Tasks anlegen, aber keine fremden Tasks löschen. → F4.5 (eigener Task nicht änderbar)
- [x] ✅ Eigene Funktion anlegen, einer Rolle zuordnen, wieder entfernen. Löschen einer Funktion, die noch eine Rolle nutzt, wird verhindert.
- [x] ✅ „Standard wiederherstellen“ bei einer geänderten mitgelieferten Rolle stellt die Standardbelegung her.
- [x] ✅ Ersteller-Rolle in den Einstellungen festlegen. Ein neues Projekt (als Person mit `projects.create`) gibt dem Ersteller diese Rolle.
- [x] ✅ Ohne `roles.manage` (z. B. als Ben) ist `/admin/roles` nicht im Menü und beim direkten Aufruf gesperrt.
- [x] ✅ `/admin/roles` (auch Übersicht) nur mit `roles.manage`, `/admin/settings` nur mit `settings.manage` erreichbar (Menü und direkter Aufruf).
- [x] ✅ Ersteller-Rolle (`/admin/settings`): Auswahl bietet nur Projektrollen mit „Projekt löschen“ an. → F3
- [x] ✅ Projekteinstellungen > Mitglieder: ein Feld „Person“ zeigt sofort alle freigegebenen Personen; Mitglieder stehen deaktiviert mit „bereits Mitglied“ (Fix F2).
- [x] ✅ Neues Projekt: Mitglieder mit Rolle gleich im Dialog wählen; Anna bleibt mit der Ersteller-Rolle (Fix F2). Nach F3 ✅
- [x] ✅ Task zuweisen als Anna: „Weitere Personen (werden Mitglied)“ wählbar, die Person ist danach Mitglied mit Standardrolle. Als Ben (ohne Mitglieder verwalten) nur Mitglieder (Fix F2).

## 2. Grundregeln (dürfen nicht aushebelbar sein)

- [x] ✅ Anna kann sich `users.manage`/`roles.manage` nicht selbst entziehen (Rolle ändern auf sich selbst wird abgelehnt).
- [x] ✅ Der letzten Person mit `users.manage`/`roles.manage` lässt sich die Rolle nicht wegnehmen.
- [x] ✅ Im Projekt: Die letzte Person mit „Projekt löschen“ lässt sich nicht entfernen oder herabstufen (auch wenn ihre Rolle eine eigene Rolle mit diesem Recht ist, nicht „Owner“).
- [x] ✅ Nur wer `owner.manage` hat, kann eine Rolle mit „Projekt löschen“ vergeben.
- [x] ✅ Systemrolle „Lesend“: Person ist Owner eines Projekts und kann dort trotzdem nichts ändern (Obergrenze).
- [x] ✅ Projektrolle „Gast“: sieht das Projekt, kann nichts ändern, nicht kommentieren.
- [x] ✅ Mitglied: legt einen Task an, ändert ihn, weist ihn sich selbst zu; einen fremden Task kann es nicht ändern; einen eigenen Task kann es nicht einer anderen Person zuweisen. (Nachtrag 2026-11-17: eigenen Task in der Oberfläche nicht änderbar → F4.5)
- [x] ✅ Fremde Kommentare: niemand kann sie ändern, auch Anna nicht. Löschen dürfen die Autorin und, wer „Fremde Kommentare löschen“ hat (Standard: Owner, Manager, Admin; entschieden 2026-11-17: so lassen); Mitglied und Gast nicht.

## 3. Tätigkeit

- [x] ✅ Profil und Benutzerverwaltung zeigen „Tätigkeit“ (EN „Activity“), nicht mehr „Funktion“.

## 4. Task-Status `idea` und `planned`

- [x] ✅ Kanban zeigt sechs Spalten in der Reihenfolge Idee, Geplant, Offen, In Arbeit, Prüfung, Erledigt.
- [x] ✅ Schalter „Ideen und Geplantes anzeigen“ blendet die zwei Spalten aus und bleibt nach dem Neuladen gleich.
- [x] ✅ Ein ToDo mit einem erledigten Task und einem Task in „Idee“ ist erledigt; der Fortschritt zählt die Idee nicht.
- [x] ✅ „Meine Tasks“ und Dashboard zeigen unter „offen“ keine Ideen, aber geplante Tasks. → F3
- [ ] ⏸ Zuweisungs-Mail: Ein zugewiesener Task in „Idee“ schickt keine Mail (Mailpit). Wechsel auf „Geplant“ schickt sie dann.

## 5. Release und Commit (S3b)

- [x] ✅ Milestone bekommt Version `1.4` und Status „geplant“; der Zeitstrahl zeigt die Version.
- [x] ✅ Auf „veröffentlicht“ setzen geht nur, wenn alle Tasks erledigt sind und jeder erledigte Task einen Commit oder „ohne“ hat. Die Meldung nennt, was fehlt. → F3
- [x] ✅ Task-Detail: Commit eintragen (Kurz-Hash sichtbar), ungültiger Hash wird abgelehnt.
- [x] ✅ Englische Oberfläche zeigt „planned“/„released“ bzw. „none“.

## 6. Revision und Neuladen (S3c, S3f)

- [x] ✅ Zwei Browserfenster (Anna und Ben im selben Projekt, Zeitachse bzw. Kanban). Änderung in einem Fenster erscheint im anderen nach höchstens etwa 30 Sekunden, mit Hinweis „Das Projekt wurde inzwischen geändert“.
- [x] ✅ Eigene Änderung lädt nicht die ganze Seite neu (kein Flackern, Scrollposition bleibt).
- [x] ✅ Ein Kommentar ändert die Revision nicht (kein Hinweis im anderen Fenster).
- [x] ✅ Eigene Änderung zeigt im eigenen Fenster keinen Hinweis „inzwischen geändert“.
- [x] ✅ Labels eines Tasks ändern und ein Mitglied hinzufügen erscheinen im anderen Fenster (Revision zählt auch diese).
- [x] ✅ Tab im Hintergrund: keine Abfragen (Netzwerk-Tab der Entwicklerwerkzeuge).

## 7. Allgemein

- [x] ✅ Hell und dunkel, Deutsch und Englisch auf den neuen Seiten (`/admin/roles`, Task-Detail, Kanban, Milestone-Formular).
- [x] ✅ Tastatur: `/admin/roles` vollständig ohne Maus bedienbar, Fokus sichtbar.
- [x] ✅ Telefonbreite: `/admin/roles` und Kanban ohne seitliches Scrollen der Seite.
- [x] ✅ Menü zeigt nur Einträge, für die die Person Rechte hat (Ben, Chiara, David vergleichen).

---

## Ergebnis

- Teilabnahme am 2026-11-16 (Oliver und Claude in Chrome), fortgesetzt am 2026-11-17 nach F3: alle 41 Punkte geprüft: 38 ✅, 2 ❌ (1.2 → F4.4, 1.4 → F4.5), 1 ⏸ (Mail, auf später verschoben).
- Abgenommen am: ____ durch: Oliver
- Offene Punkte (gehen als Fix vor M2 an die Umsetzung):
  - F1 Rollenseite unübersichtlich (2026-11-16) → `docs/fix-m1-rollenseite.md`, umgesetzt in `bb3c782`, Abschnitt 1 neu prüfen.
  - F2 Mitglieder: Auswahl zeigt nur Ben (2026-11-16) → `docs/fix-m1-mitglieder.md`, umgesetzt in `3ae8bb3`, `41ef213`, `9e8f503`.
  - F4 aus Olivers Punkten: `docs/fix-m1-f4.md` (F4.1 Beschriftung System-/Projektrolle, F4.2 und F4.3 erledigt, F4.4 Spalten der Übersicht, F4.5 eigene Tasks nicht änderbar).
  - F3 aus der Teilabnahme: `docs/fix-m1-abnahme.md` (Task ohne Zuständige, Ersteller-Rolle, Admin wird nicht Mitglied, Grundregel §7.3 über Systemrolle, Ideen in „Meine Tasks“, Meldungen beim Veröffentlichen, Auswahlen, Kleinigkeiten).
