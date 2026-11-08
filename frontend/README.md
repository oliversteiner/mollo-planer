# Frontend (Nuxt)

Single-page app für die Projektverwaltung: Nuxt 4 mit Nuxt UI, gebaut als statische Dateien (`nuxt generate`). Entscheidungen und Bau-Reihenfolge: `../docs/frontend-plan.md`.

## Befehle (in WSL im Ordner `frontend/`)

- `npm install`: Abhängigkeiten installieren
- `npm run dev`: Entwicklungsserver auf `http://localhost:3000` (das Backend läuft auf `http://localhost:8000`)
- `npm run lint`: ESLint
- `npm run typecheck`: TypeScript
- `npm run build`: statische Dateien in `.output/public`

`npm` muss das Linux-npm aus nvm sein, nicht das Windows-npm von PhpStorm (Fehler „UNC-Pfade werden nicht unterstützt“). Prüfen mit `which npm`.
