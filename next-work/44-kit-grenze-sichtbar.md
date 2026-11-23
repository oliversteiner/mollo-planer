# 44 · Vor dem Starter-Kit: Grenze zwischen Basis und Fachcode sichtbar machen

Planer: planer.mollo.dev · Projekt 2 «Mollo Planer» · ToDo `t-383` (Status der Teile dort nachführen).

Status: offen. Auftrag von Oliver über Stuart (2026-11-23). Nach 42 und 40; R1 darf mit 41 zusammen laufen. Konzept: `docs/starter-kit.md`. Kein Verhalten ändern; je Schritt ein kleiner PR, Tests grün.

## Befund (Stuart, Stand `dev`, 2026-11-23)

Der Code ist in gutem Zustand (1717 Tests, Larastan sauber); ein grosses Refactoring ist **nicht** nötig. Vor dem Auslagern stören aber vier Stellen, an denen Basis und Fachcode ineinandergreifen:

1. **Flache Ordner:** `app/Services` (23 Klassen) und `app/Support` (14) mischen Basis (`UserSecurity`, `UserInvitation`, `AgentAccess`, `Rights`, `PasswordPolicy`, `StrongAuthentication`, `DeploymentEnvironment` …) und Fach (`ProjectStatistics`, `KanbanOrdering`, `DocumentService` …). Gleich im Frontend: `components/`, `composables/`, `pages/`.
2. **Rechte kennen das Projekt:** `App\Support\Rights` arbeitet direkt mit `App\Models\Project` (`roleOf`, Archiv, Cap). Die Basis braucht «ein Bereich mit Mitgliedern und Rollen», nicht «Projekt».
3. **Schale mit festem Menü:** `layouts/default.vue` (451 Zeilen) enthält Navigation und Projektliste fest verdrahtet. Kit und native Apps brauchen das Menü als Daten (Vertrag im Konzept).
4. **Ein GraphQL-Schema für alles:** `graphql/types.graphql`, `queries.graphql`, `mutations.graphql` mischen Konto/Rechte und Fach. Dazu `Services\Deletion` (Papierkorb) fast ganz Fach, `AppServiceProvider` registriert beides.

Nicht anfassen: `Planer\Import` (1363 Zeilen), RichText, Assistent. Gross, aber reiner Fachcode und für das Kit ohne Belang.

## Schritte

* **R1 · Landkarte und Wächter (zuerst, ohne Verschieben):** `docs/starter-kit-landkarte.md`: jede Klasse, jedes Composable, jede Seite, jeder GraphQL-Typ als **Basis**, **Identität**, **Fach** oder **gemischt** (mit Grund). Architekturtest (Pest `arch()` bzw. PHPUnit): `App\Core\…` importiert nichts aus Fach-Namensräumen. Liste der «gemischten» ist die Arbeitsliste für R2 bis R5.
* **R2 · Menü als Daten:** Einträge von Haupt- und Benutzermenü aus einer Registrierung (Schlüssel, Bezeichnung, Symbol, Recht, Ziel); der Planer meldet seine Einträge an. `default.vue` rendert nur. GraphQL-Feld für native Apps erst mit dem Vertrag, hier nur das Frontend.
* **R3 · Rechte über einen Vertrag:** Schnittstelle (z. B. `RightsScope`: Rolle einer Person, archiviert?, Art des Bereichs), `Project` implementiert sie; `Rights` kennt nur noch die Schnittstelle. Keine Änderung an Tabellen.
* **R4 · Schema teilen:** GraphQL-Dateien nach Basis und Fach trennen (`#import`), gedrucktes Schema bleibt byte-gleich (Test).
* **R5 · Umziehen:** die als Basis eingestuften Klassen nach `App\Core\…` und das Frontend nach `layers/core` verschieben, in mehreren kleinen PRs (Konto und Sicherheit, Rechte und Rollen, Agenten und Tokens, Schale, Mails). Nur Namensräume und Pfade, kein Verhalten. PHPStan-Baseline mitziehen.

## Prüfen je Schritt

Alle Tests, `test:mariadb`, Larastan, Frontend-Prüfungen, Browser-Tests der Anmeldung. Nach R5: Architekturtest ohne Ausnahmen.
