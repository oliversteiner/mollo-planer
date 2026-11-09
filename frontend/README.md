# Frontend (Nuxt)

Single-page app für die Projektverwaltung: Nuxt 4 mit Web Awesome Pro (Web Components, Theme „Matter“), gebaut als statische Dateien (`nuxt generate`). Entscheidungen und Bau-Reihenfolge: `../docs/frontend-plan.md`, Umstellung der Oberfläche: `../docs/frontend-webawesome-plan.md`.

## Font Awesome Pro

Das Pro-Paket kommt aus dem privaten Register von Font Awesome. Dafür braucht `frontend/.npmrc` den Package Token (fontawesome.com → Account). Die Datei ist lokal und nicht eingecheckt:

```
@fortawesome:registry=https://npm.fontawesome.com/
//npm.fontawesome.com/:_authToken=DEIN_TOKEN
```

Die zweite Zeile beginnt mit `//`. In GitHub steht der Token als Secret `FONTAWESOME_NPM_AUTH_TOKEN`.

## Web Awesome Pro

Web Awesome Pro kommt aus einem eigenen privaten Register (Cloudsmith), getrennt von Font Awesome. Der Token steht nie in der Datei, sondern in der Umgebungsvariable `WEBAWESOME_NPM_TOKEN` (Konto auf webawesome.com → Workspace → Installationsanleitung). `frontend/.npmrc` bleibt lokal und nicht eingecheckt:

```
@web.awesome.me:registry=https://npm.cloudsmith.io/fortawesome/webawesome-pro/
//npm.cloudsmith.io/fortawesome/webawesome-pro/:_authToken=${WEBAWESOME_NPM_TOKEN}
```

Installieren mit `WEBAWESOME_NPM_TOKEN="…" npm install` (oder die Variable mit direnv setzen). Die Version ist exakt gepinnt (`3.14.0`, ohne `^`). In GitHub steht der Token als Secret `WEBAWESOME_NPM_AUTH_TOKEN`. Die Skills `webawesome` und `webawesome-design` liegen in `frontend/.claude/skills/` (aus dem Paket kopiert).

## Befehle (im Ordner `frontend/`)

- `npm install`: Abhängigkeiten installieren
- `npm run dev`: Entwicklungsserver auf `http://localhost:3000` (das Backend läuft auf `http://localhost:8000`, änderbar mit `NUXT_PUBLIC_API_BASE`)
- `npm run lint`: ESLint
- `npm run typecheck`: TypeScript
- `npm test`: Tests (Vitest): `tests/unit` ohne Nuxt, `tests/nuxt` für Komponenten und Middleware in einer Nuxt-Umgebung
- `npm run codegen`: GraphQL-Typen neu erzeugen (nach `composer schema:frontend` im Backend oder einer neuen Abfrage). Während der Entwicklung am besten `npm run codegen -- --watch` in einem zweiten Terminal: Eine neue oder geänderte `graphql(...)`-Abfrage ohne Codegen führt im Browser zum Apollo-Fehler 115 („Expecting a parsed GraphQL document“).
- `npm run build`: statische Dateien in `.output/public`

Node 24 aus nvm verwenden (`nvm use`, liest `.nvmrc`). Nuxt 4.6 läuft nicht auf Node 23. Prüfen mit `node -v`.
