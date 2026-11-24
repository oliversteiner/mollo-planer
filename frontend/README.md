# Mollo Planer Frontend (Nuxt)

Single-page app für die Projektverwaltung: Nuxt 4 mit Web Awesome Pro (Web Components, Theme „Matter“), gebaut als statische Dateien (`nuxt generate`). Entscheidungen und Bau-Reihenfolge: `../docs/frontend-plan.md`, Umstellung der Oberfläche: `../docs/frontend-webawesome-plan.md`.

## Font Awesome Pro

Dieses Repository ist **öffentlich**, die Pro-Symbole sind lizenziert und dürfen **nie** hineinkommen (auch nicht als Teilmenge oder Katalog). Sie liegen im privaten Repository `oliversteiner/mollo-pro-assets` (`fontawesome/<version>/duotone-light/*.svg`, `catalog.json`, Lizenz). Es gibt kein Font-Awesome-npm-Paket und keinen Font-Awesome-Token mehr; `npm ci`, Tests und Build laden nichts von Font Awesome herunter (das Paket war 115 MB, jeder Download kostete Bandbreite).

- **Holen (einmal nach dem Klonen):** `npm run icons:fetch` im Ordner `frontend/`. Es klont den Stand aus `pro-assets.json` (Repository, Tag, Version) mit der Anmeldung von git/gh nach `frontend/vendor/fontawesome/<version>/` (nicht versioniert, in `.gitignore`).
- **Ohne Zugriff** (Fork, fremde Mitwirkende, Pull Request aus einem Fork): Der Build nimmt **Font Awesome Free** (`@fortawesome/fontawesome-free`, Stil «solid») mit einem Katalog aus dessen Metadaten. Namen, die es in Free nicht gibt, zeigen das Ersatzsymbol. Quelle und Stil entscheidet `scripts/icon-source.mjs`.
- **Deployment nie mit Free:** Mit `ICONS_REQUIRE_PRO=1` (gesetzt im Deploy-Job) bricht der Build ab, wenn die Pro-Symbole fehlen.
- **CI:** Mit dem Secret `PRO_ASSETS_TOKEN` (fine-grained, nur Lesen, nur das eine Repository) holt `icons:fetch` die Symbole bei jedem Lauf (rund 10 MB von GitHub, **kein** `actions/cache` und kein Artefakt für lizenzierte Dateien). Ohne das Secret passiert nichts davon und es gilt Free.
- **Wächter:** `npm run icons:check` und der Test `tests/unit/no-pro-assets.test.ts` schlagen fehl, wenn eine Datei mit dem Kopf «Font Awesome Pro» oder etwas in `frontend/vendor/` versioniert ist.
- **Neue Version von Font Awesome:** Pro «For the Web» (ZIP) laden, **außerhalb** der Repositories entpacken (z. B. `~/www/_vendor/fontawesome-pro-<version>-web/`) und `npm run icons:update -- <entpackter Ordner> <Arbeitskopie von mollo-pro-assets>` ausführen. Im privaten Repo committen, taggen (`fontawesome-<version>`), dann `version` und `ref` in `pro-assets.json` setzen.

## Web Awesome Pro

Web Awesome Pro kommt aus einem eigenen privaten Register (Cloudsmith), Der Token steht nie in der Datei, sondern in der Umgebungsvariable `WEBAWESOME_NPM_TOKEN` (Konto auf webawesome.com → Workspace → Installationsanleitung). `frontend/.npmrc` bleibt lokal und nicht eingecheckt:

```
@web.awesome.me:registry=https://npm.cloudsmith.io/fortawesome/webawesome-pro/
//npm.cloudsmith.io/fortawesome/webawesome-pro/:_authToken=${WEBAWESOME_NPM_TOKEN}
```

Installieren mit `WEBAWESOME_NPM_TOKEN="…" npm install` (oder die Variable mit direnv setzen). Die Version ist exakt gepinnt (`3.14.0`, ohne `^`). In GitHub steht der Token als Secret `WEBAWESOME_NPM_AUTH_TOKEN`. Die Skills `webawesome` und `webawesome-design` stammen aus dem Pro-Paket und liegen **nicht im Repository** (öffentlich, Lizenz unklar; Auftrag 42 C): `frontend/.claude/skills/webawesome*/` ist in `.gitignore`, der Wächter `npm run icons:check` schlägt an, wenn sie versioniert werden. Lokal einrichten, nach `npm install` mit dem Token:

```bash
# LOKAL (auf deinem Mac), im Ordner frontend/
mkdir -p .claude/skills
cp -R node_modules/@web.awesome.me/webawesome-pro/dist/skills/webawesome .claude/skills/
cp -R node_modules/@web.awesome.me/webawesome-pro/dist/skills/webawesome-design .claude/skills/
```

`skills-lock.json` bleibt versioniert: sie nennt nur Quellpfade und Prüfsummen, keine lizenzierten Inhalte, und sagt, woher die Skills kommen. Die Dateien bleiben in der Git-Historie; ob die Historie bereinigt wird, entscheidet Oliver nach Klärung der Lizenz.

## Befehle (im Ordner `frontend/`)

- `npm install`: Abhängigkeiten installieren
- `npm run dev`: Entwicklungsserver auf `http://localhost:3000`, im WLAN erreichbar unter `http://<IP des Macs>:3000` (z. B. vom iPad). Das Backend läuft auf `http://localhost:8000`, im Entwicklungsmodus aus dem Host der Seite abgeleitet (`http://<Host>:8000`), sonst mit `NUXT_PUBLIC_API_BASE` (siehe `.env.example`)
- `npm run lint`: ESLint
- `npm run typecheck`: TypeScript
- `npm test`: Tests (Vitest): `tests/unit` ohne Nuxt, `tests/nuxt` für Komponenten und Middleware in einer Nuxt-Umgebung
- `npm run codegen`: GraphQL-Typen neu erzeugen (nach `composer schema:frontend` im Backend oder einer neuen Abfrage). Während der Entwicklung am besten `npm run codegen -- --watch` in einem zweiten Terminal: Eine neue oder geänderte `graphql(...)`-Abfrage ohne Codegen führt im Browser zum Apollo-Fehler 115 („Expecting a parsed GraphQL document“).
- `npm run build`: statische Dateien in `.output/public`

Node 24 aus nvm verwenden (`nvm use`, liest `.nvmrc`). Nuxt 4.6 läuft nicht auf Node 23. Prüfen mit `node -v`.
