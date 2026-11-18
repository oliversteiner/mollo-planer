# Fix M3 (F6): Befunde der Abnahme M3

Stand: 2026-11-18, Planung. Geprüft in Chrome an `dev` bis `3592f4a` mit frischen Demo-Daten. Rolle: Auftrag für die Umsetzung (Bob). Ein Commit je Punkt, `umsetzung.md` „Stand“ nachführen. Ergebnisse: `docs/abnahme-m3.md`.

Alles Kleinigkeiten; M3 ist sonst in Ordnung.

## F6.1 Doppelter Label-Name: Fehler am falschen Feld

**Befund:** Vorlage oder Projekt-Labels bearbeiten, bei zwei Labels denselben **englischen** Namen eintragen: Die Meldung „Jeder Name darf in einer Sprache nur einmal vorkommen.“ erscheint am **deutschen** Feld der zweiten Zeile. Der Server meldet den Pfad `input.labels.1.names` ohne Sprache; der Editor hängt ihn an das erste Namensfeld.
**Umsetzung:** Server meldet den Pfad mit Index des Namens (`input.labels.1.names.1.name`) bzw. der Sprache; der Editor zeigt die Meldung am Feld dieser Sprache. Gleiches für „Der Name in der Sprache DE fehlt.“ (Feld der Pflichtsprache). Test für beide Pfade; gilt für `createTemplate`, `updateTemplate`, `updateProjectLabels`.

## F6.2 Einzahl in Hinweisen

**Befund:** „Bei 1 Labels gibt es keinen Namen in Englisch …“ (Dialog „Neues Projekt“) und „1 Label wird gelöscht und von 1 Tasks entfernt.“ (Labels speichern).
**Umsetzung:** Pluralformen in `frontend/i18n/locales/{de,en}.json` für `templateFallback`, `fallbackTemplate` (Anzahl Labels) und in `removeDescription` die Anzahl Tasks („von 1 Task“ / „von 3 Tasks“, EN „from 1 task“). Test der Texte (Vitest) mit 1 und 2.

## F6.3 Meldung bei mehr als 30 Labels

**Befund:** „Das Feld input.labels darf höchstens 30 Einträge haben.“ nennt den technischen Feldnamen.
**Umsetzung:** Eigene Meldung DE/EN aus `PlanerLimits` („Höchstens 30 Labels.“ / „At most 30 labels.“) für `createTemplate`, `updateTemplate`, `updateProjectLabels`; im Editor ist „Label hinzufügen“ ab 30 deaktiviert mit diesem Hinweis.

## Ablauf

F6.1 → F6.2 → F6.3, Prüfungen, im Browser ansehen, dann anhalten. Keine Datenbank neu aufsetzen nötig.

## Nachprüfung (2026-11-18, Planung)

F6.1, F6.2, F6.3 in Chrome nachgeprüft: alle ✅. Details in `docs/abnahme-m3.md`.
