# 42 · Nacharbeiten aus dem Review vom 23.11.2026

Planer: planer.mollo.dev · Projekt 2 «Mollo Planer» · ToDo `t-377` (Status der Teile dort nachführen).

Status: Teil A umgesetzt und gemergt (PR #38, Branch `fix/review-2026-11-23`); Caches `pro-icons-*` gelöscht. Teile B bis E umgesetzt auf Branch `fix/review-2026-11-23-b` (PR nach dev, nicht gemergt; je Teil ein Commit). Zu C: Skills nur aus dem Index genommen und ignoriert, `skills-lock.json` bleibt (nur Pfade und Prüfsummen), Historie nicht umgeschrieben. Zu D: `.junie/AGENTS.md` ist nicht im Repo (nur `.junie/mcp/mcp.json`), die Regeln stehen in `AGENTS.md`. Ursprünglich: Status: offen. Auftrag von Oliver über Stuart (2026-11-23). **Teil A dringend** (vor allem anderen). Branch von `dev` (`fix/review-2026-11-23`), PR nach `dev`, nicht mergen. Je Teil ein Commit.

Review von Stuart über die Commits auf `dev` vom 20. bis 23.11. (u. a. `bf8c87a`, `fd6bb64`, `ee56ca3`, PR #33, PR #34/Auftrag 38). Tests, Larastan, ESLint waren grün; die Punkte unten sind Lücken in Sicherheit, Lizenz und Verhalten.

## A. Pro-Symbole nicht im Actions-Cache (dringend)

**Problem:** Auftrag 38 legt `frontend/vendor/fontawesome` mit `actions/cache` (Schlüssel `pro-icons-<hash von pro-assets.json>`) ab. Das Repo ist öffentlich. Ein Pull Request aus einem Fork führt die Workflow-Datei **seiner** Fassung aus und darf Caches des Basis-Branches wiederherstellen; die Bedingung `if: env.PRO_ASSETS_TOKEN != ''` kann er einfach entfernen. So wären die lizenzierten SVGs abrufbar.

* In `frontend.yml` und `deploy.yml` den Cache-Schritt für die Pro-Symbole **entfernen**. `icons:fetch` holt jedes Mal (rund 10 MB von GitHub, kostenlos), nur wenn `PRO_ASSETS_TOKEN` gesetzt ist.
* Bestehende Caches mit dem Schlüssel `pro-icons-*` löschen (`gh cache list` / `gh cache delete`), auf allen Branches. In der Abschlussmeldung die Liste vor und nach dem Löschen.
* In `docs/deploy-template.md` (Abschnitt Font Awesome) festhalten: **lizenzierte Dateien nie in `actions/cache` oder Artefakte** in diesem Repo.
* Für Oliver (in der Abschlussmeldung aufführen, nicht selbst tun): GitHub → Settings → Actions → General → «Fork pull request workflows from outside collaborators» auf **«Require approval for all outside collaborators»** stellen.

## B. Import: Warnungen in der Oberfläche zeigen

**Problem:** Seit `fd6bb64` ist eine unbekannte oder fremde Person bei `assigneeEmail` eine **Warnung** statt eines Fehlers; der Import läuft weiter und lässt die Zuweisung weg. MCP meldet das. Die Weboberfläche fragt `warnings` aber nicht ab (`frontend/app/graphql/planer.ts`, `components/planer/ImportModal.vue`, `pages/assistant.vue`): Zuweisungen gehen dort **still** verloren.

* `warnings` in den Abfragen des Imports und des Assistenten anfordern.
* In der **Vorschau** (Trockenlauf) und im **Ergebnis** die Warnungen zeigen: je Task Pfad/Titel und die E-Mail-Adresse, Text aus `planer.import.assignee_unknown_warning`. Gleiches Muster wie die Fehleranzeige im selben Dialog (mit `ui-consistency:adjusting`).
* Tests: Nuxt-Test für Vorschau und Ergebnis mit Warnung; Browser-Test optional.

## C. Pro-Dokumentation von Web Awesome aus dem öffentlichen Repo

**Problem:** `frontend/.claude/skills/webawesome/` und `frontend/.claude/skills/webawesome-design/` (rund 150 Dateien) sind aus dem **Pro-Paket** kopiert (`frontend/skills-lock.json`: Quelle `node_modules/@web.awesome.me/webawesome-pro/dist/skills/…`; Lizenz im Kopf: «MIT / Commercial (for Web Awesome Pro)»). Ob das öffentlich liegen darf, ist unklar; bis zur Klärung gilt dieselbe Regel wie bei Font Awesome Pro.

* Beide Ordner mit `git rm -r --cached` aus dem Repo nehmen (lokal bleiben sie), in `frontend/.gitignore` eintragen.
* `frontend/skills-lock.json`: behalten oder ebenfalls ignorieren (Bob entscheidet, Begründung melden). Wie man die Skills lokal wieder installiert, in `frontend/README.md` beschreiben.
* `icons:check` (Wächter aus 38) erweitern oder einen zweiten Wächter: Fehler, wenn unter `.claude/skills/` Dateien aus einem `*-pro`-Paket getrackt sind (z. B. Kopfzeile mit «Web Awesome Pro» oder Pfad `webawesome`).
* Hinweis: Die Dateien bleiben in der Git-Historie. Ob die Historie bereinigt werden muss, entscheidet Oliver nach Klärung der Lizenz; **nicht** ohne Auftrag umschreiben.

## D. `composer.phar` und Aufräumen

* `composer.phar` (Repo-Root, 3,8 MB, seit `bf8c87a`) entfernen und in `.gitignore` aufnehmen. Prüfen, dass kein Skript, kein Workflow und keine Doku es braucht (CI nutzt `composer` aus `setup-php`).
* Leere Datei `docs/MCP Server Optimierung.md` entfernen (oder füllen, falls Junie sie gebraucht hat: in `next-work/MCP Optimierungen.md` nachsehen).
* `.mcp.json`, `.ai/mcp/mcp.json`, `.junie/mcp/mcp.json`: enthalten nur `laravel-boost` ohne Geheimnis. Beibehalten erlaubt; in `CLAUDE.md`/`AGENTS.md` festhalten: **in diese Dateien nie Tokens oder Adressen mit Zugangsdaten**, persönliche MCP-Server nur in `.mcp.local.json` oder der Benutzer-Konfiguration (ignoriert).

## E. Assistent: sichtbare Projekte begrenzen (klein)

`AssistantPrompt::visibleProjects()` (seit `ee56ca3`) listet **alle** sichtbaren Projekte bei jeder Anfrage und fragt je Projekt die Rolle einzeln ab (`Rights::allows` → `roleIdOf`, Mitglieder nicht vorgeladen).
* Mitglieder bzw. Rollen der Person für alle Projekte mit **einer** Abfrage laden (feste Abfragezahl, Test mit Abfragezähler wie in Auftrag 33).
* Obergrenze (z. B. 50, sortiert: zuletzt benutzt, dann Name) mit Hinweis im Prompt «weitere Projekte vorhanden», damit der Prompt für Admins nicht unbegrenzt wächst.
* `list_users`: `%` und `_` in der Suche maskieren (alle drei `like`-Stellen).

## Prüfen

`composer test`, `composer test:mariadb`, `composer analyse`, `npm test`, `npm run typecheck`, `npm run lint`, `npm run icons:check`. CI auf dem PR grün, inkl. Frontend-Workflow ohne Cache-Schritt für Pro.

## Arbeitsweise (für alle Agenten, auch Junie)

Änderungen nur über einen Branch und einen PR nach `dev`, nie direkt auf `dev`, nie mergen ohne Oliver. In `AGENTS.md` und `.junie/AGENTS.md` so festhalten (Teil D).
