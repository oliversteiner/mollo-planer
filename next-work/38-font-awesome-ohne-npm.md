# 38 · Font Awesome nicht mehr bei jedem Build herunterladen

Planer: planer.mollo.dev · Projekt 2 «Mollo Planer» · ToDo `t-374` (Status der Teile dort nachführen).

Status: umgesetzt bis auf Schritte von Oliver (Commit `806c342` auf `chore/font-awesome-lokal`, PR #34 nach `dev`, nicht gemergt). Offen für Oliver: privates Repo `mollo-pro-assets` anlegen und befüllen, Secret `PRO_ASSETS_TOKEN`, Browser-Tests und TEST-Prüfung danach. Teil C (Web Awesome) siehe Abschlussmeldung.

(Ursprünglich: Status: offen. **Dringend** (verursacht Kosten). Auftrag von Oliver über Stuart (2026-11-23). Branch von `dev` (`chore/font-awesome-lokal`), PR nach `dev`, nicht mergen.)

## Ausgangslage

Font Awesome verrechnet «Package Bandwidth – Overages»: Gebühren für Downloads aus dem privaten npm-Register (`npm.fontawesome.com`). Gemessen von Stuart am 2026-11-23:

* `@fortawesome/fontawesome-pro` 7.3.1: **115 MB** pro Download (Tarball), 959 MB entpackt (alle Stile, Webfonts, Sprites, `metadata/` mit 115 MB).
* Der Planer braucht davon nur **Duotone Light** (4804 SVGs, rund 10 MB) und für die Suche einen Katalog (528 KB, aus `metadata/icon-families.json` erzeugt, `frontend/scripts/icon-catalog.*`).
* `npm ci` läuft im November in GitHub Actions mehr als 100-mal: `frontend.yml` und `deploy.yml` (zwei Jobs), dazu Club DB. Der npm-Cache von `setup-node` hilft nur teilweise, denn ein Cache gilt pro Branch und GitHub löscht ihn nach 7 Tagen ohne Nutzung bzw. über 10 GB (Planer heute 3,6 GB). Dazu kommen lokale Installationen und Worktrees. Grob gerechnet sind das weit über 10 GB Downloads im Monat.

Font Awesome erlaubt Pro-Symbole selbst auszuliefern. Ins **private** Repo des Planers dürfen sie (ins öffentliche Starter-Kit nicht, dort gilt Font Awesome Free, siehe `docs/starter-kit.md`).

## Ziel

Kein Build, kein Test und kein Deploy lädt `@fortawesome/fontawesome-pro` mehr herunter. Die Symbole kommen aus dem Repo.


## ⚠️ Änderung 2026-11-23 17:30: Das Repo ist öffentlich

Gefunden von Bob, bestätigt von Stuart: `oliversteiner/mollo-planer` ist **PUBLIC** und soll es bleiben. **Pro-Symbole dürfen nie in dieses Repo**, auch nicht als Teilmenge, auch nicht der Katalog (er stammt aus den Pro-Metadaten). Teil A unten («Symbole ins Repo») gilt **so nicht mehr**; stattdessen:

Stand geprüft: Bisher wurde nichts aus Font Awesome Pro committet (Git-Historie aller Branches durchsucht: keine SVGs ausser `logo.svg`, kein Katalog). `frontend/vendor/` liegt auf `chore/font-awesome-lokal` nur ungetrackt vor: **nicht committen.**

### A′. Pro-Symbole aus einem privaten Repo

* **Privates Repo** `oliversteiner/mollo-pro-assets` (Oliver legt es an; Name änderbar). Inhalt: `fontawesome/<version>/duotone-light/*.svg`, `catalog.json`, `LICENSE.txt`, `README.md`. Erzeugt mit `icons-update` aus `~/www/_vendor/fontawesome-pro-<version>-web/`. Später nutzbar für Club DB und weitere Apps.
* **Im Planer:** `frontend/vendor/` in `.gitignore`. Der Build sucht die Symbole dort.
* **Holen:** `npm run icons:fetch` lädt den passenden Stand (Tag oder Pfad mit Version) aus dem privaten Repo nach `frontend/vendor/fontawesome/<version>/`. Lokal über die Anmeldung von `gh`/git, in CI über ein Secret `PRO_ASSETS_TOKEN` (fine-grained, **nur Lesen, nur dieses eine Repo**; Oliver legt es an) mit `actions/checkout` (`repository`, `token`, `path`). Das sind rund 10 MB von GitHub statt 115 MB von Font Awesome; mit `actions/cache` (Schlüssel: Version) meist gar kein Download.
* **Fallback Font Awesome Free:** Fehlen die Pro-Symbole (Fork, fremde Mitwirkende, PR aus einem Fork ohne Secrets, Starter-Kit), nutzt der Build `@fortawesome/fontawesome-free` (öffentliches npm, keine Kosten bei Font Awesome) mit einem passenden Stil (z. B. `solid`) und einem Katalog aus dessen Metadaten. Namen, die es in Free nicht gibt, zeigen das Ersatzsymbol (`circle-question`, wie heute). Quelle und Stil an **einer** Stelle als Konfiguration (deckt Auftrag 36 D ab).
* **Deploy nie mit Free:** Die Deploy-Jobs (TEST und PROD) brechen ab, wenn die Pro-Symbole fehlen. Kein stiller Wechsel auf Free in einer echten Umgebung.
* **Wächter:** Ein CI-Schritt und ein Test schlagen fehl, wenn im Repo eine Datei mit dem Kopf `Font Awesome Pro` (Kommentar in jeder Pro-SVG) oder ein Pfad `frontend/vendor/` getrackt ist. Dazu ein Hinweis in `CLAUDE.md`/`AGENTS.md` für alle Agenten.
* Lokale Entwicklung bei Oliver und den Agenten: `npm run icons:fetch` einmal nach dem Klonen; ohne Zugriff läuft es mit Free.

### Was aus den übrigen Teilen bleibt

B (npm-Paket und Font-Awesome-Token entfernen), C (Web Awesome Pro melden), D (Caches) gelten weiter. Ergänzung zu C: `@web.awesome.me/webawesome-pro` braucht ebenfalls einen Token; ein Fork kann den Planer heute also gar nicht bauen. Nur melden, nicht ändern (Thema Starter-Kit).

### Für Oliver vor dem Merge

1. Privates Repo `mollo-pro-assets` anlegen.
2. Fine-grained Token (nur Lesen, nur dieses Repo) als Secret `PRO_ASSETS_TOKEN` im Planer-Repo.
3. Danach: Secret `FONTAWESOME_NPM_AUTH_TOKEN` im Planer löschen, npm-Token bei Font Awesome widerrufen (wenn Club DB auch umgestellt ist).

## A. Symbole ins Repo (ersetzt durch A′, nur noch als Hintergrund)

* Neuer Ordner, z. B. `frontend/vendor/fontawesome/<version>/`, mit
  * `duotone-light/*.svg` (nur dieser Stil),
  * `catalog.json` (der heutige Suchkatalog),
  * `LICENSE.txt` aus dem Paket und einer kurzen `README.md` (Herkunft, Version, wie man aktualisiert).
* **Quelle (Entscheid Oliver, 2026-11-23):** Oliver lädt Font Awesome Pro einmal von Hand herunter (ZIP «For the Web» von fontawesome.com) und entpackt es ausserhalb der Repos, z. B. `~/www/_vendor/fontawesome-pro-<version>-web/`. Dieser Ordner wird **nie** committet (959 MB) und ist nur auf dem Mac. Club DB nutzt denselben Ordner.
* Ein Skript (z. B. `npm run icons:update -- <pfad-zum-ordner>`) erzeugt daraus den Vendor-Ordner im Repo: Version aus dem Paket lesen, nur `svgs-full/duotone-light` kopieren (Pfad im ZIP prüfen, er kann von npm abweichen), Katalog aus `metadata/icon-families.json` erzeugen, `LICENSE.txt` kopieren. Fehlt der Ordner oder der Stil, bricht es mit klarer Meldung ab. Es läuft nur von Hand bei einem Update, nie im Build und nie in CI.
* Nur die erzeugte Teilmenge (rund 10 MB) liegt im Repo; CI, Server, WSL-Rechner und Agenten brauchen den grossen Ordner nicht.
* `nuxt.config.ts`: `publicAssets` und der Katalog lesen aus dem Vendor-Ordner. `ensureIconCatalog` beim Build fällt weg.
* Pfad mit Version (deckt Auftrag 36, Teil C, ab): `/icons/<version>/duotone-light/…` und `/icons/<version>/catalog.json`.

* Geprüft von Stuart (2026-11-23): Ordner `~/www/_vendor/fontawesome-pro-7.3.1-web/` ist da. Das ZIP hat **kein** `package.json`; die Version steht im Kopf von `css/all.css` («Font Awesome Pro 7.3.1»), dort lesen. `svgs-full/duotone-light/` (4804 Dateien) und `metadata/icon-families.json` sind identisch mit dem npm-Paket 7.3.1, die Pfade gelten wie bisher.

## B. Abhängigkeit entfernen

* `@fortawesome/fontawesome-pro` aus `package.json` und `package-lock.json` entfernen. Prüfen, dass nichts sonst daraus importiert (CSS, JS, Tests).
* In `frontend.yml` und `deploy.yml` die Zeilen für `FONTAWESOME_NPM_AUTH_TOKEN` und `npm.fontawesome.com` in `.npmrc` entfernen. Das Secret selbst löscht Oliver in GitHub, nicht Bob.
* `frontend/.npmrc` und die Doku (`README`, `CLAUDE.md`, Einrichtung lokal) anpassen: Für die Entwicklung braucht es keinen Font-Awesome-Token mehr.

## C. Web Awesome Pro prüfen, nicht ändern

`@web.awesome.me/webawesome-pro` kommt aus einem anderen Register (`npm.cloudsmith.io`, 36 MB entpackt). Messen und melden: Grösse des Tarballs, ob dort auch Bandbreite verrechnet wird (Konto bei webawesome.com, Oliver), und ob die Caches in CI wirken. Nicht ändern ohne Auftrag.

## D. Caches in CI

* In allen Workflows mit `npm ci` prüfen, dass der npm-Cache greift (Log «Cache restored»), auch auf Feature-Branches. Falls sinnvoll: `restore-keys` so setzen, dass ein Branch den Cache von `dev`/`main` nutzt.
* Melden, wie viele `npm ci` pro Push laufen; doppelte Installationen in einem Workflow zusammenlegen, wenn das ohne Umbau geht.

## Prüfen

* `npm ci` in einem frischen Klon ohne Font-Awesome-Token läuft durch.
* `npm run build`/`generate`, `npm test`, `npm run typecheck`, Browser-Tests: Symbole erscheinen (Menü, Karten, Auswahlfeld mit Suche).
* Auf TEST nach dem Deploy: `/icons/<version>/duotone-light/rocket.svg` und der Katalog antworten mit 200.
* In der Abschlussmeldung: Grösse des Vendor-Ordners, Grösse des Repos vorher/nachher.

## Reihenfolge

Vor 41 und 36, weil jede Stunde CI Geld kostet. Auftrag 36 baut danach auf dem Vendor-Ordner auf.
