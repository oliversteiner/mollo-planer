# Frontend (Nuxt)

Single-page app für die Projektverwaltung: Nuxt 4 mit Nuxt UI, gebaut als statische Dateien (`nuxt generate`). Entscheidungen und Bau-Reihenfolge: `../docs/frontend-plan.md`.

## Font Awesome Pro

Das Pro-Paket kommt aus dem privaten Register von Font Awesome. Dafür braucht `frontend/.npmrc` den Package Token (fontawesome.com → Account). Die Datei ist lokal und nicht eingecheckt:

```
@fortawesome:registry=https://npm.fontawesome.com/
//npm.fontawesome.com/:_authToken=DEIN_TOKEN
```

Die zweite Zeile beginnt mit `//`. In GitHub steht der Token als Secret `FONTAWESOME_NPM_AUTH_TOKEN`.

## Befehle (in WSL im Ordner `frontend/`)

- `npm install`: Abhängigkeiten installieren
- `npm run dev`: Entwicklungsserver auf `http://localhost:3000` (das Backend läuft auf `http://localhost:8000`)
- `npm run lint`: ESLint
- `npm run typecheck`: TypeScript
- `npm run build`: statische Dateien in `.output/public`

`npm` muss das Linux-npm aus nvm sein, nicht das Windows-npm von PhpStorm (Fehler „UNC-Pfade werden nicht unterstützt“). Prüfen mit `which npm`.
