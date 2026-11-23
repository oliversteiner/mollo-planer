---
paths:
  - 'backend/graphql/**'
---

# Graphql

## Schema-Änderungen immer mit composer schema:frontend abschließen
Nach jeder Änderung am GraphQL-Schema (backend/graphql/*.graphql) muss `composer schema:frontend` im Ordner backend/ laufen. Das schreibt frontend/graphql/schema.graphql, das die IDE-Konfiguration (graphql.config.yml) und das Frontend-Codegen nutzen. Danach im Frontend `npm run codegen`.
