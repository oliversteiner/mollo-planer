# Planer-Datei im Repository: GitHub Action

Diese Vorlage hält eine Planer-Datei (`*.planer.json`, Format planer-v2) in deinem Repository mit der App im Gleichstand. Nach jeder Änderung in der App stößt die App die Action an, die Action holt den Plan mit `mollo-planer pull` und committet die Datei, wenn sich etwas geändert hat. Die Commit-Nachricht ist `Docs: planer export rev <N>`.

Die Action schreibt nur in eine Richtung (App → Datei). Änderungen aus der Datei in die App trägt ein Mensch oder ein Agent lokal mit `mollo-planer push` ein.

## Einrichtung in vier Schritten

### 1. Zugang erzeugen

In der App im **Profil** unter **Zugang für Claude** einen Zugang erzeugen (zum Beispiel „GitHub Action Podcast“). Der Zugang wird nur einmal angezeigt. Er handelt mit deinen Rechten und braucht das Recht „Planer-Datei exportieren“ im Projekt.

> **Der Zugang läuft nach 90 Tagen ab.** Danach schlägt die Action mit Exit-Code 6 fehl (Meldung „Zugang ungültig oder abgelaufen“). Dann einen neuen Zugang erzeugen und das Secret ersetzen.

### 2. Secrets und Variablen im Repository setzen

*Settings › Secrets and variables › Actions*

| Art | Name | Wert |
|---|---|---|
| Secret | `MOLLO_PLANER_URL` | Adresse der **API** der App: `https://api.planer.example.com` (produktiv) oder `https://api.planer-test.example.com` (Testumgebung), ohne `/api` am Ende |
| Secret | `MOLLO_PLANER_TOKEN` | der Zugang aus Schritt 1 |
| Variable | `PLANER_PROJECT` | die Nummer des Projekts in der App (`list_projects`, oder die Adresse im Browser: `/projects/<Nummer>`) |
| Variable | `PLANER_FILE` | Pfad der Datei im Repository, zum Beispiel `docs/podcast.planer.json` |
| Variable (optional) | `MOLLO_PLANER_REF` | Version des Werkzeugs: Branch, Tag oder Commit von mollo-planer. Standard `main`. Für ruhige Läufe einen Tag eintragen. |
| Variable (optional) | `MOLLO_PLANER_REPOSITORY` | Repository des Werkzeugs, Standard `oliversteiner/mollo-planer` |

Unter *Settings › Actions › General › Workflow permissions* muss „Read and write permissions“ gelten (die Action schreibt mit `GITHUB_TOKEN` in das Repository).

### 3. Datei kopieren

`planer-export.yml` nach `.github/workflows/planer-export.yml` kopieren und committen. Die Datei `PLANER_FILE` muss es nicht geben, der erste Lauf legt sie an. Läuft der Branch geschützt, muss `github-actions[bot]` direkt pushen dürfen.

Das Werkzeug (`tools/planer` und `spec` aus mollo-planer) wird bei jedem Lauf ohne Token ausgecheckt, weil mollo-planer öffentlich ist. Ein npm-Paket gibt es nicht.

### 4. Webhook in der App einrichten

In der App: **Projekteinstellungen › Benachrichtigung bei Änderungen**:

- Art **GitHub**
- Repository: `besitzer/name` deines Repositories
- Token: ein **fein eingeschränktes** GitHub-Token (*Settings › Developer settings › Fine-grained tokens*): nur dieses Repository, Berechtigung **Contents: Read and write** (damit `repository_dispatch` angenommen wird)

Mit **Testen** schickt die App sofort ein Ereignis; in *Actions* im Repository erscheint ein Lauf „planer-export“. Danach schickt die App nach Änderungen höchstens alle fünf Minuten ein Ereignis. Ohne Webhook holt der stündliche Lauf den Plan trotzdem.

## Beispiel: ein fremdes Repository (Club DB)

Für das Repository `club-db`, Datei `docs/club-db.planer.json`:

1. Zugang: in der App im Profil einen Zugang „GitHub Action Club DB“ erzeugen (das Konto braucht im Projekt das Recht «Planer-Datei exportieren»).
2. Im Repository `club-db` unter *Settings › Secrets and variables › Actions*: Secrets `MOLLO_PLANER_URL` = `https://api.planer.example.com` und `MOLLO_PLANER_TOKEN`; Variablen `PLANER_PROJECT` = Nummer des Projekts, `PLANER_FILE` = `docs/club-db.planer.json`, `MOLLO_PLANER_REF` = ein Tag von mollo-planer (nicht `main`, damit ein Lauf nicht von einer Änderung am Werkzeug überrascht wird).
3. `planer-export.yml` nach `.github/workflows/` kopieren, committen. Der erste Lauf (*Actions › planer-export › Run workflow*) legt die Datei an und committet sie.
4. Webhook in der App (Schritt 4 oben): Repository `<besitzer>/club-db`, Token nur für dieses Repository mit *Contents: Read and write*.

In diesem Repository selbst ändert sich dafür nichts. Die Umstellung der Skripte, die heute `docs/version-club-db.md` lesen, ist Sache des Repositories `club-db`.

## Wenn die Action fehlschlägt

| Meldung | Bedeutung und Lösung |
|---|---|
| „Lokale Änderungen in …“ (Exit 3) | Jemand hat die Datei mit `"changed": true` eingecheckt (lokal mit einem Helfer geändert, noch nicht eingetragen). Lokal `mollo-planer push` (oder `sync`) ausführen und die Datei committen. Die Action überschreibt sie nie. |
| „Zugang ungültig oder abgelaufen“ (Exit 6) | Zugang abgelaufen oder widerrufen. Neuen Zugang erzeugen, Secret `MOLLO_PLANER_TOKEN` ersetzen. |
| „Keine Verbindung zu …“ (Exit 6) | `MOLLO_PLANER_URL` stimmt nicht oder die App ist nicht erreichbar. |
| „Kein Projekt“ (Exit 1) | Variable `PLANER_PROJECT` fehlt. |
| Push abgelehnt | Branch-Schutz: `github-actions[bot]` darf nicht direkt pushen. |

## Lokal weiterarbeiten

```bash
export MOLLO_PLANER_URL=https://api.planer.example.com      # Testumgebung: https://api.planer-test.example.com
export MOLLO_PLANER_TOKEN=…            # nie ins Repository (oder in .mollo-planer, dann mit chmod 600)
git pull
node tools/planer/cli/bin/mollo-planer.mjs pull docs/podcast.planer.json --project 7
node tools/planer/cli/bin/mollo-planer.mjs add task --todo t-3 --title "Mikrofone testen"
node tools/planer/cli/bin/mollo-planer.mjs push docs/podcast.planer.json     # zeigt zuerst den Trockenlauf
```

Mehr dazu in `tools/planer/skills/planer/SKILL.md` (Abschnitt „Mit der App abgleichen“) und in `docs/claude-connector.md`.
