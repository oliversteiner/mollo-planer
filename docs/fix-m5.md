# Fix M5 (F8): Befunde der Abnahme M5

Stand: 2026-11-18, Planung. Geprüft an `dev` bis `9a79a76` mit frischen Demo-Daten. Rolle: Auftrag für die Umsetzung (Bob). Ein Commit je Punkt, `umsetzung.md` „Stand“ nachführen. Ergebnisse: `docs/abnahme-m5.md`.

## F8.1 Zeitangaben eine Stunde zu früh (ganze App)

**Befund:** Webhook-Statuszeile „fehlgeschlagen am 18.11.2026, 02:13“, tatsächlich 03:13 in Zürich. GraphQL liefert `DateTime` als `2026-11-18 02:13:49` (UTC, `app.timezone = UTC`) ohne Zeitzone; `formatDateTime` macht `new Date(value)`, und der Browser liest den Text als Ortszeit. Gleiches gilt für alle anderen Felder vom Typ `DateTime` (Papierkorb „gelöscht am“, Passkeys, Zugänge für Claude, Kommentare, …).
**Umsetzung:**
- Der Skalar `DateTime` gibt ISO 8601 mit Zeitzone aus (`2026-11-18T02:13:49+00:00` oder `…Z`) und nimmt beim Lesen beide Formen an. Ein eigener Skalar ist erlaubt.
- Im Frontend alle Stellen prüfen, die `DateTime` lesen, vergleichen oder relativ anzeigen (`formatDateTime`, „vor x Minuten“, Sortierungen, `lastFailedAt >= lastSentAt` in der Webhook-Karte). Reine Daten (`Date`, Fälligkeiten) bleiben unverändert.
- Postman-Sammlung und `docs/graphql-kontext.md`, falls dort Beispiele stehen.
- Tests: PHP für den Skalar (Ausgabe mit Zone, Eingabe beider Formen); Vitest mit `TZ=Europe/Zurich` für `formatDateTime` und eine relative Angabe.

## F8.2 Meldung nach „Testen“ mit englischem Code

**Befund:** „Die Benachrichtigung ist nicht angekommen (timeout).“ Die Statuszeile darunter übersetzt den Code richtig („Zeitüberschreitung“).
**Umsetzung:** `WebhookTestResult` liefert zusätzlich den Code (`error`), das Frontend baut die Meldung mit `errorText` wie die Statuszeile; oder der Server übersetzt den Code in der Sprache der Anfrage. Gleiche Wörter an beiden Stellen, DE/EN. Test.

## F8.3 Wechsel der Art behält den alten Wert

**Befund:** Gespeichert ist GitHub `mollo-test/planer-test`; Wechsel auf „Allgemein“ zeigt den Repository-Namen im Feld „Adresse“.
**Umsetzung:** Beim Wechsel der Art das Feld leeren; beim Zurückwechseln zur gespeicherten Art deren gespeicherten Wert wieder einsetzen. Geheimnis bzw. Token beim Wechsel Pflicht (wie jetzt). Test.

## Ablauf

F8.1 → F8.2 → F8.3, Prüfungen grün, im Browser ansehen, dann anhalten. Keine Datenbank neu aufsetzen nötig.
