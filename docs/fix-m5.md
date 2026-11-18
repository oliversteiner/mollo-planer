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

## F8.4 `pull --watch` endet nach dem ersten Durchlauf (wichtig)

**Befund** (CLI-Prüfung durch Oliver, `~/planer-test/bericht-m5.md`): `mp pull --watch --interval 10` macht das erste `pull` und endet nach etwa 1 s mit Exit 13 und „Warning: Detected unsettled top-level await … bin/mollo-planer.mjs:291“. Änderungen aus der App kommen nicht mehr an.
**Ursache (gelesen):** `syncContext()` in `bin/mollo-planer.mjs` legt den Schlaf-Timer mit `setTimeout(…).unref?.()` an; der Timer hält die Event-Loop nicht am Leben, Node beendet mit offenem `await`. Die Einheitstests mit Zeit-Attrappe sehen das nicht.
**Umsetzung:** Den Timer nicht `unref`en (Strg-C beendet über das vorhandene `stopped`/Aufwecken). Test, der den echten Einstiegspunkt als Kindprozess startet: läuft nach 2 Intervallen noch, holt eine Änderung (gegen den Integrations-Server oder einen kleinen lokalen HTTP-Server), endet auf SIGINT mit Exit 0.

## F8.5 Hinweis auf lesbaren Zugang in `.mollo-planer` (klein)

**Befund:** `.mollo-planer` mit Zugang hatte Rechte 644 (für alle Benutzer des Rechners lesbar). Die CLI warnt nur bei Git.
**Umsetzung:** Enthält `.mollo-planer` einen `token` und ist sie für Gruppe oder andere lesbar (nicht unter Windows), Warnung auf stderr: „… ist für andere lesbar. `chmod 600 <pfad>`.“ `pull --project`, das die Datei ergänzt, behält die Rechte. README und `SKILL.md` erwähnen `chmod 600`. Test.

## Ablauf

F8.4 → F8.1 → F8.2 → F8.3 → F8.5, Prüfungen grün, im Browser ansehen, dann anhalten. Keine Datenbank neu aufsetzen nötig.
