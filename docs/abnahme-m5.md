# Abnahme Meilenstein M5 „Synchronisation“ (T2, T3, W1)

Stand: 2026-11-18, geprüft durch die Planung an `9a79a76` mit frischen Demo-Daten (Chrome; Node-Tests in der Werkzeug-Umgebung ohne PHP). Fixes in `docs/fix-m5.md` (F8). Rolle: Checkliste für Oliver.
Personen: Ben (Owner Website-Relaunch), Chiara (Manager), David (Mitglied). Je Punkt: ✅ passt, ❌ Fehler (→ F8.x), ⏸ für Oliver.

**Ergebnis der Prüfung:** 20 ✅, 5 Befunde (F8.4 wichtig, F8.1 betrifft die ganze App, F8.2, F8.3, F8.5 klein), 4 ⏸ für Oliver.

---

## 1. T2 `pull`, `push`, `sync`, `--watch`

- [x] ✅ `npm test` in `tools/planer`: 173 bestanden, 6 übersprungen (die Integrationstests, ohne PHP hier).
- [x] ✅ Code gegen den Auftrag gelesen (`planer-remote.mjs`, `planer-sync.mjs`): Vorrang der Konfiguration, keine Option `--token`, Warnung bei eingecheckter `.mollo-planer`, `pull` bricht bei `changed` ab (Exit 3), unverändert schreibt nichts, `push` prüft lokal, Trockenlauf, Revisionshinweis (Exit 4), Löschungen nur mit `--yes` (Exit 5), ohne Änderungen nur `changed` entfernen, `sync`, `--watch` mit mindestens 10 s und Weiterlaufen bei 429/Netz.
- [x] ✅ `SKILL.md` „Mit der App abgleichen“, CI-Job `remote` in `tools.yml`.
- [x] ✅ Oliver (Claude im Terminal, `~/planer-test/bericht-m5.md`): `composer test:planer-remote` 6/6 grün, nichts übersprungen.
- [x] ✅ Von Hand gegen die lokale App: `pull` legt an (Rev. 1), zweites `pull` unverändert (mtime gleich), Helfer → `changed`, `pull` bricht ab (Exit 3, Datei unverändert), `push` → „1 neu“, Rev. 1 → 2, `k-new-1` → `k-12`, danach unverändert.
- [x] ✅ Revisionskonflikt aus zweitem Ordner: Exit 4 „Die App ist bei Revision 3, die Datei bei 2.“; `pull --force` verwirft.
- [x] ✅ Löschung ohne `--yes` Exit 5 mit Liste, mit `--yes` eingetragen (Task fehlt im Export; Papierkorb nicht in der App angesehen); `sync` ohne Änderung wie `pull`.
- [x] ✅ Fehlerfälle: falscher Zugang und falsche URL Exit 6 mit Text, ohne Projekt Exit 1; `.mollo-planer` mit `projects` ohne Token findet das Projekt.
- [ ] ❌ `pull --watch` endet nach dem ersten Durchlauf mit Exit 13 („unsettled top-level await“), der Schlaf-Timer ist `unref`. → F8.4
- [ ] ❌ (klein) `.mollo-planer` mit Zugang war für andere lesbar (644), die CLI warnt nicht. → F8.5
- Git-Warnung bei nicht ignorierter `.mollo-planer`: nicht geprüft (Testordner ohne Git), durch Einheitstests abgedeckt.
- Hinweis: Fehlt der Zugang ganz, endet die CLI mit Exit 1 (Aufruf) statt 6; vertretbar, da es ein Konfigurationsfehler ist.

## 2. T3 GitHub Action

- [x] ✅ Vorlage `planer-export.yml` mit README, Test `github-action.test.mjs`, Abschnitt in `claude-connector.md`.
- [ ] ⏸ Echter Lauf in einem Test-Repository (`repository_dispatch` und `workflow_dispatch`, Commit nur bei Änderung).

## 3. W1 Webhook (Chrome, als Ben)

- [x] ✅ Karte „Benachrichtigung bei Änderungen“ unter „Planer-Datei“, Art GitHub/Allgemein mit passenden Feldern und Hinweisen.
- [x] ✅ Prüfung `owner/name`: „falsch“ abgelehnt mit „Das Repository im Format besitzer/name angeben.“
- [x] ✅ Speichern: „Token gespeichert“ mit „Ersetzen“, Knöpfe Speichern, Testen, Entfernen; „Noch nicht gesendet“.
- [x] ✅ GraphQL liefert `hasSecret: true`, der Token steht in keiner Antwort; Speichern ändert die Revision nicht.
- [x] ✅ „Testen“ (GitHub, Test-Token): „Die Benachrichtigung ist nicht angekommen (HTTP 401).“ und Statuszeile „Letzte Benachrichtigung fehlgeschlagen am …: HTTP 401“.
- [ ] ❌ Uhrzeit der Statuszeile eine Stunde zu früh (02:13 statt 03:13): `DateTime` kommt in UTC ohne Zeitzone, das Frontend liest es als Ortszeit. Betrifft alle `DateTime`-Felder der App. → F8.1
- [x] ✅ Art „Allgemein“: Adresse und Geheimnis, Hinweis auf HMAC und `X-Mollo-Signature`; `http://localhost` lokal angenommen.
- [ ] ❌ Beim Wechsel der Art bleibt der alte Wert stehen (Repository-Name im Feld „Adresse“). → F8.3
- [x] ✅ „Testen“ (Allgemein, `http://localhost:8000/api`): Zeitüberschreitung, wie erwartet (`php artisan serve` beantwortet nur eine Anfrage gleichzeitig). Statuszeile „Zeitüberschreitung“.
- [ ] ❌ Die Meldung nach „Testen“ zeigt den Code „(timeout)“ englisch, die Statuszeile darunter „Zeitüberschreitung“. → F8.2
- [x] ✅ Rechte: David (Mitglied) sieht `webhook: null`, Ändern abgelehnt („Dafür fehlt dir die Berechtigung.“); Chiara (Manager) darf.
- [x] ✅ Entfernen: Bestätigung „Benachrichtigung entfernen?“, Meldung „Benachrichtigung entfernt.“, `webhook: null`, Revision unverändert.
- [ ] ⏸ Erfolgreiches Senden an einen Empfänger mit Prüfung der Signatur und Bündelung bei mehreren Änderungen (durch Bobs Tests abgedeckt; von Hand am einfachsten mit dem echten GitHub-Webhook unten).
- [ ] ⏸ Webhook „GitHub“ mit echtem Token gegen das Test-Repository: Änderung in der App → nach höchstens rund 5 Minuten läuft die Action.

## 4. Queue und Betrieb

- [x] ✅ Scheduler-Eintrag `queue:work --stop-when-empty`, Abschnitt „Cron“ in `deploy-template.md`.
- [ ] ⏸ Cron-Eintrag `schedule:run` im Hostpoint-Panel.

---

Ergebnis: _offen_ (nach F8 Nachprüfung durch die Planung; ⏸ bei Oliver)
