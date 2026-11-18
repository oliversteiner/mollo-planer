# 25 · Korrekturen und Aufräumen nach dem Testlauf

Status: erledigt (A 6228b77, B c96aa7e, C 851fd5d, D dba125e, E und Docs in diesem Commit; auf `chore/aufraeumen-1-0`); wartet auf Oliver
Reihenfolge: **nach Auftrag 24** (Fable), vor dem Tag `v1.0.0` und vor Auftrag 16. Branch `chore/aufraeumen-1-0` **von `fix/onboarding-nacharbeit`** (Auftrag 24, oberster Branch des Stapels). Nichts mergen und nichts auf `dev` oder `main` pushen, bis Oliver es sagt.
Anlass: lokaler Testlauf durch Stuart am 2026-11-18 auf Olivers Mac (`test/auth-flow-e2e`): Backend 1315/1316 grün (SQLite/MariaDB), Pint, Lint, Typecheck, Vitest 518 grün; Browser-Tests 1. Lauf 20 von 21 (S3 rot), 2. Lauf 21 von 21; Larastan 195 Fehler.
Prozess: Kein UI-Verhalten ändert sich. A ist ein Test, B bis D sind Code-Qualität, E ist Ablage. Ein Commit je Teil.

## A · Browser-Test S3 wackelt (hoch)

- **Befund:** `e2e/auth-flow.spec.ts:197` wartet nach «Freigeben» auf den Toast «… ist freigegeben». Im 1. Lauf war der Toast nicht mehr sichtbar, das Konto stand aber schon unter den Mitgliedern (Freigabe hat funktioniert). Zeitproblem im Test, nicht in der App.
- **Soll:** Den Erfolg am **bleibenden** Ergebnis prüfen: Die Zeile der Person verschwindet aus «Wartet auf Freigabe» und erscheint in der Mitgliederliste. Den Toast höchstens zusätzlich und nur, wenn er sicher noch sichtbar ist (z. B. die Live-Region auf den Text prüfen, bevor er ausläuft). Keine längeren festen Wartezeiten, kein `retries` in der Konfiguration, um das zu überdecken.
- Alle anderen Szenarien auf dasselbe Muster durchsehen (Erfolg nur über einen Toast geprüft) und gleich behandeln. Liste der geänderten Stellen in der Abschlussmeldung.
- **Abnahme:** `npm run test:e2e` **dreimal hintereinander** grün; die drei Ergebnisse in der Abschlussmeldung.

## B · Larastan in den neuen Dateien (mittel)

Acht Meldungen in Dateien der Aufträge 18 bis 23 (`composer analyse`, Level 5):

- `Console/Commands/UserEmail.php:51, 63, 81`: `email_verified_at` wird als `string|null` erkannt. Ursache prüfen (fehlt die `@property`-Angabe oder der Cast in `User`?) und am Modell beheben, nicht mit `@var` an der Aufrufstelle.
- `Console/Commands/UserTwoFactor.php:48`: `match` ohne Fall für andere Werte. Die Aktion ist vorher geprüft; das dem Werkzeug zeigen (z. B. eigener Typ oder Enum für die Aktionen), nicht mit `default` ein stilles Verhalten erfinden.
- `Models/User.php:37`: `@use`-Angabe passt nicht zum Trait. `:112`, `:122`: unnötiges `?->` vor `??`.
- `Services/UserSecurity.php:38`: die Rückgabe-Angabe von `status()` stimmt nicht mit dem Rückgabewert überein (Typen der Passkey-Felder angleichen).
- Verhalten ändert sich nicht. Die Tests bleiben unverändert grün.

## C · Larastan im übrigen Code und in der CI (mittel)

- Die übrigen 187 Meldungen liegen in älterem Code (u. a. `Planer/Import.php` 37, `Planer/Export.php` 19, `Services/TrashListing.php`, `Services/Deletion.php`, `Assistant/AssistantPrompt.php` je 10). **Nicht jetzt umbauen.**
- Stattdessen: eine **Baseline** erzeugen (`phpstan-baseline.neon`, in `phpstan.neon` eingebunden), sodass `composer analyse` grün ist, und den Schritt `composer analyse` in `.github/workflows/backend.yml` aufnehmen. Ab dann fällt jede **neue** Meldung in der CI auf.
- Die Baseline nach Datei und Anzahl in `docs/umsetzung.md` (Abschnitt Stand oder eigener kurzer Abschnitt «Larastan-Baseline») festhalten, damit sie später gezielt abgebaut werden kann. Den Abbau plant Stuart als eigenen Auftrag.
- Melden, falls eine der alten Meldungen nach einem echten Fehler aussieht (falscher Typ zur Laufzeit, nicht nur fehlende Angabe): diese **nicht** in die Baseline, sondern einzeln in der Abschlussmeldung nennen.

## D · Doppelte Auto-Imports im Frontend (niedrig)

- `nuxt typecheck` warnt: `normalizeText` und `matchesSearch` werden von `app/composables/useRoleFilters.ts` (Zeile 9, `export { … }`) und von `app/utils/text-search.ts` angeboten. Den erneuten Export in `useRoleFilters.ts` entfernen; Stellen, die ihn nutzen, auf `utils/text-search` (oder den Auto-Import) umstellen.
- Abnahme: `npm run typecheck` ohne diese zwei Warnungen.

## E · Ablage aufräumen (niedrig)

- `next-work/_to_delete/` (zwei Entwürfe von Fable zu Auftrag 22, ungetrackt, ersetzt durch 22 und 23): löschen. Bob darf löschen, weil Oliver das angewiesen hat.
- Erledigte und abgenommene Aufträge **01 bis 15 und 18 bis 23** sowie **20**: nach `next-work/erledigt/` verschieben (`git mv`), nicht löschen. Das README von `next-work/` um diesen Ordner ergänzen («abgenommene Aufträge liegen in `erledigt/`»). Offen bleiben 16, 24, 25 (und 17, sobald es geschrieben ist).
- `docs/onboarding-leitfaden.md` (Fable, ungetrackt): mit diesem Auftrag committen, unverändert.
- `frontend/test-results/` und der Build-Ordner `.nuxt-e2e`: prüfen, dass beide in `.gitignore` stehen; sonst ergänzen.
- **Nicht** anfassen: `.mcp.json`, `.claude/`, lokale Git-Branches. Für die Branches nur eine Liste in die Abschlussmeldung: Branches, deren Ziel auf GitHub weg ist (`gone`), und Branches, die schon in `test/auth-flow-e2e` enthalten sind. Oliver entscheidet und löscht selbst.

## Abnahme

- `composer test`, `composer test:mariadb`, `./vendor/bin/pint --test`, `composer analyse` grün; im Frontend `npm run lint`, `npm run typecheck`, `npm test` grün; `npm run test:e2e` dreimal grün.
- Commits: `Test: … (task 25 A)`, `Chore: … (task 25 B)` usw.
- `docs/umsetzung.md` (Stand) nachführen. Oben `Status: erledigt (<Commits>)`, dann anhalten.
