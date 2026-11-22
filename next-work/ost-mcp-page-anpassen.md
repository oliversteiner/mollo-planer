**Requirements**

**Ziel**

Die Anleitung auf der MCP-Seite zeigt in Kapitel 02 „Verbindung“ zusätzlich, wie Agenten in JetBrains-IDEs (AI Assistant und Junie) mit dem Planer-MCP-Server verbunden werden.

**Scope**

**In Scope**
- Neue Abschnitte „JetBrains AI Assistant“ und „JetBrains Junie“ in Kapitel 02 der deutschen und der englischen Seite.
- Gleiche Struktur und Sicherheitshinweise wie bei Claude Desktop und Claude Code.

**Out of Scope**
- Änderungen an anderen Kapiteln, am Layout (`mcp.css`) oder an der Navigation.
- Änderungen am MCP-Server, an den Werkzeugen oder an den Endpunkten.

**User Stories**

- Als Entwickler in einer JetBrains-IDE möchte ich eine Schritt-für-Schritt-Anleitung finden, damit ich den Planer-MCP-Server ohne Umwege einrichte.
- Als Junie-Nutzer möchte ich wissen, wo Endpunkt und Token eingetragen werden, damit das Token nicht im Projekt landet.

**Functional Requirements**

- Kapitel 02 enthält die Überschriften „JetBrains AI Assistant“ und „JetBrains Junie“ (`h3`, wie die bestehenden Claude-Abschnitte).
- AI Assistant: Eintrag über Settings → Tools → AI Assistant → Model Context Protocol (MCP) → Add → As JSON, mit `mcp-remote` und dem Token als Platzhalter in `env` (gleiches Muster wie bei Claude Desktop).
- Junie: Eintrag über Settings → Tools → Junie → MCP Settings oder direkt in `~/.junie/mcp/mcp.json` mit `url` und `headers` (`Authorization: Bearer …`).
- Hinweis: Das Token gehört nur in die Benutzerdatei `~/.junie/mcp/mcp.json`, nie in die projektweite `.junie/mcp/mcp.json`, die versioniert werden kann.
- Hinweis: Unter Windows startet `npx` über `cmd /c`.
- Hinweis: Menüpfade können je nach IDE-Version abweichen; nach dem Eintragen die Konfiguration übernehmen (Apply) und den Client neu starten.
- Beide Sprachfassungen enthalten dieselben Beispiele; der Endpunkt bleibt `https://api.planer.mollo.dev/mcp` (TEST).

**Non-Functional Requirements**

- Statisches HTML ohne Skripte, ohne externe Ressourcen, ohne `v-html`.
- Die bestehenden Prüfungen aus `mcp-page.test.ts` bleiben erfüllt.

**Technical Design**

**Current Implementation**

- `frontend/public/mcp/index.html` (DE) und `frontend/public/mcp/en/index.html` (EN): statische Seiten. Kapitel 02 heißt `#einrichten` (DE) bzw. `#setup` (EN) und enthält die Abschnitte „Claude Desktop“ und „Claude Code“; das Konfigurationsbeispiel steht in `<pre><code>` mit dem Platzhalter `&lt;dein-agent-token&gt;`.
- `frontend/public/mcp/mcp.css`: gemeinsames Stylesheet, keine Änderung nötig.
- `frontend/tests/unit/mcp-page.test.ts`: prüft Pflichtstrings in beiden Sprachen, kein `<script>`, keine externen `src`/`href`.
- Die projektweite `.junie/mcp/mcp.json` enthält nur den Eintrag `laravel-boost` und kein Token.

**Key Decisions**

- **AI Assistant über `mcp-remote`:** AI Assistant dokumentiert keine verlässliche Header-Eingabe für ein Bearer-Token. Der Weg über `mcp-remote` ist das bereits genutzte Muster aus Claude Desktop.
- **Junie mit `url` und `headers`:** Junie dokumentiert Remote-Server mit `url` und `headers`. Das Token steht nur in der Benutzerdatei.
- **Projekt-`mcp.json` bleibt unverändert:** Sie enthält kein Token und wird nicht angepasst.

**Proposed Changes**

- DE-Seite: In Kapitel 02 nach „Claude Code“ zwei neue Abschnitte `<h3>JetBrains AI Assistant</h3>` und `<h3>JetBrains Junie</h3>` mit Menüpfad, JSON-Beispiel, Token-Hinweis und Neustart-Hinweis.
- EN-Seite: identische Struktur in Englisch.
- Die Beispiele bleiben statisches HTML; Sonderzeichen im JSON werden als `&lt;`/`&gt;` geschrieben.

**Data Models / Contracts**

Konfiguration für AI Assistant (Eintrag „As JSON“):

```json
{
  "mcpServers": {
    "mollo-planer": {
      "command": "npx",
      "args": ["-y", "mcp-remote", "https://api.planer.mollo.dev/mcp", "--header", "Authorization:${AUTH_TOKEN}"],
      "env": { "AUTH_TOKEN": "Bearer <dein-agent-token>" }
    }
  }
}
```

Konfiguration für Junie (`~/.junie/mcp/mcp.json`):

```json
{
  "mcpServers": {
    "mollo-planer": {
      "url": "https://api.planer.mollo.dev/mcp",
      "headers": { "Authorization": "Bearer <dein-agent-token>" }
    }
  }
}
```

**File Structure**

- Geändert: `frontend/public/mcp/index.html`, `frontend/public/mcp/en/index.html`, `frontend/tests/unit/mcp-page.test.ts`.

**Risks**

- **Veraltete Menüpfade:** JetBrains ändert Menüs je nach Version. Mitigation: Hinweis, dass Pfade abweichen können.
- **Token in versionierter Datei:** Mitigation: Hinweis auf die Benutzerdatei und Platzhalter in allen Beispielen.
- **Direkte `url` in AI Assistant:** nicht als gesichert dokumentiert. Mitigation: `mcp-remote` als Weg für AI Assistant.

**Testing**

**Validation Approach**

Automatisierte Prüfung über den bestehenden Vitest-Test `frontend/tests/unit/mcp-page.test.ts`, ergänzt um JetBrains-Prüfungen. Ausführung mit `npm test` im Ordner `frontend/` (Timeout 60–120 s).

**Key Scenarios**

- Beide Seiten enthalten in Kapitel 02 die Überschriften „JetBrains AI Assistant“ (EN: „JetBrains AI Assistant“) und „JetBrains Junie“.
- Beide Seiten nennen `mcp-remote`, `headers` und den Pfad `~/.junie/mcp/mcp.json`.
- Die JSON-Beispiele enthalten nur den Platzhalter `<dein-agent-token>`, kein echtes Token.

**Edge Cases**

- Die neuen Beispiele führen weder zu `<script>`-Tags noch zu externen `src`/`href`.
- Die Anker `#einrichten` (DE) und `#setup` (EN) bleiben erhalten.

**Test Changes**

- `mcp-page.test.ts`: neue Prüfungen für die JetBrains-Überschriften, `mcp-remote`, `headers` und `~/.junie/mcp/mcp.json` in DE und EN.
- Keine neuen Testdateien.