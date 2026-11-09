# Frontend (Nuxt)

Single-page app für die Projektverwaltung: Nuxt 4 mit Nuxt UI, gebaut als statische Dateien (`nuxt generate`). Entscheidungen und Bau-Reihenfolge: `../docs/frontend-plan.md`.

## Font Awesome Pro

Das Pro-Paket kommt aus dem privaten Register von Font Awesome. Dafür braucht `frontend/.npmrc` den Package Token (fontawesome.com → Account). Die Datei ist lokal und nicht eingecheckt:

```
@fortawesome:registry=https://npm.fontawesome.com/
//npm.fontawesome.com/:_authToken=DEIN_TOKEN
```

Die zweite Zeile beginnt mit `//`. In GitHub steht der Token als Secret `FONTAWESOME_NPM_AUTH_TOKEN`.

## Befehle (im Ordner `frontend/`)

- `npm install`: Abhängigkeiten installieren
- `npm run dev`: Entwicklungsserver auf `http://localhost:3000` (das Backend läuft auf `http://localhost:8000`, änderbar mit `NUXT_PUBLIC_API_BASE`)
- `npm run lint`: ESLint
- `npm run typecheck`: TypeScript
- `npm test`: Tests (Vitest): `tests/unit` ohne Nuxt, `tests/nuxt` für Komponenten und Middleware in einer Nuxt-Umgebung
- `npm run codegen`: GraphQL-Typen neu erzeugen (nach `composer schema:frontend` im Backend oder einer neuen Abfrage). Während der Entwicklung am besten `npm run codegen -- --watch` in einem zweiten Terminal: Eine neue oder geänderte `graphql(...)`-Abfrage ohne Codegen führt im Browser zum Apollo-Fehler 115 („Expecting a parsed GraphQL document“).
- `npm run build`: statische Dateien in `.output/public`

Node 24 aus nvm verwenden (`nvm use`, liest `.nvmrc`). Nuxt 4.6 läuft nicht auf Node 23. Prüfen mit `node -v`.
