# Auftrag: Fix M6 (F9)

Status: offen
Stand: 2026-11-18, `dev` bis `5a5d55a`.
Dokument: `docs/fix-m6.md` (maßgeblich). Ergebnisse der Abnahme: `docs/abnahme-m6.md`.

1. F9.1 `importPlan` mit `appendOnly`: Rechte je Änderung statt `planer.import`; Trockenlauf in `draftPlan` mit derselben Prüfung. (wichtig)
2. F9.2 `appendOnly` ohne `projectId` wirkungslos: neue Projekte aus Vorlagen über den Assistenten. (wichtig)
3. F9.4 Milestone nie still wählen: Prompt-Regel und Prüfung in `DraftProcessor`.
4. F9.7 Chat-Zustand je Person, beim Abmelden löschen.
5. F9.3 Einstieg „Mit dem Assistenten ergänzen“ im Projekt.
6. F9.5 „In Ideen ablegen“ öffnet „Idee anlegen“ mit Titelvorschlag.
7. F9.6 Texte (Mehrzahl, „Eingetragen: …“, keine IDs in Nachrichten, „Entwurf“ statt „Datei“).
8. F9.8 Hinweis auf der Karte KI-Kontext.

Abschluss: ein Commit je Punkt, Prüfungen grün, im Browser angesehen, `docs/umsetzung.md` „Stand“ nachgeführt, oben `Status: erledigt (<Commits>)`, anhalten.
