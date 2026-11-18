/**
 * Verbindung zur App (docs/auftrag-m5.md §2.1): Konfiguration und ein kleiner MCP-Client.
 * Keine Abhängigkeit: Node `fetch` gegen `POST <url>/mcp`, JSON-RPC wie ein MCP-Client.
 * Dieses Modul liegt bewusst nicht in `core`: das Frontend nutzt `core` und braucht kein Netz.
 * Der Zugang steht nie in einer Ausgabe oder Fehlermeldung.
 */
import { existsSync, readFileSync, statSync, writeFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { dirname, join, relative, resolve, sep } from 'node:path';

export const CONFIG_NAME = '.mollo-planer';

/** Ein Fehler mit Text für Menschen und dem Exit-Code, mit dem das Werkzeug endet. */
export class RemoteError extends Error {
  /** kind: auth (Zugang), rate (zu viele Anfragen), network (Netz, Zeit), other. */
  constructor(message, { exit = 6, errors = null, kind = 'other' } = {}) {
    super(message);
    this.exit = exit;
    this.errors = errors;
    this.kind = kind;
  }
}

/**
 * Sucht `.mollo-planer` vom Ordner aufwärts bis zur Git-Wurzel (oder zur Wurzel des Dateisystems).
 * Liefert { path, data } oder null.
 */
export function findConfig(startDir) {
  let dir = resolve(startDir);
  for (;;) {
    const candidate = join(dir, CONFIG_NAME);
    if (existsSync(candidate)) {
      let data;
      try {
        data = JSON.parse(readFileSync(candidate, 'utf8'));
      } catch {
        throw new RemoteError(`${candidate} ist keine gültige JSON-Datei.`, { exit: 1 });
      }
      return { path: candidate, data };
    }
    if (existsSync(join(dir, '.git')) || dirname(dir) === dir) {
      return null;
    }
    dir = dirname(dir);
  }
}

/** Ist die Konfiguration in Git eingecheckt oder nicht ignoriert? Ohne Git: nein. */
export function configIsExposed(configPath) {
  const dir = dirname(configPath);
  const run = (...args) => spawnSync('git', args, { cwd: dir, encoding: 'utf8' });
  const inside = run('rev-parse', '--is-inside-work-tree');
  if (inside.error || inside.status !== 0) {
    return false;
  }
  if (run('ls-files', '--error-unmatch', configPath).status === 0) {
    return true;
  }
  return run('check-ignore', '-q', configPath).status !== 0;
}

/** Ist die Konfiguration für Gruppe oder andere lesbar? Unter Windows gibt es diese Rechte nicht. */
export function configIsReadableByOthers(configPath) {
  if (process.platform === 'win32') {
    return false;
  }
  return (statSync(configPath).mode & 0o077) !== 0;
}

/** Schlüssel der Datei in `projects`: Pfad relativ zur Konfigurationsdatei, mit „/“. */
export function projectKey(configPath, filePath) {
  return relative(dirname(configPath), resolve(filePath)).split(sep).join('/');
}

/**
 * Löst URL, Zugang und Projekt auf. Vorrang: Option bzw. Umgebung, dann `.mollo-planer`.
 * Es gibt keine Option für den Zugang (Shell-Verlauf).
 */
export function resolveSettings({ env, cwd, filePath, projectOption, warn }) {
  const config = findConfig(filePath ? dirname(resolve(cwd, filePath)) : cwd);
  const data = config?.data ?? {};

  const url = (env.MOLLO_PLANER_URL || data.url || '').replace(/\/+$/, '');
  const token = env.MOLLO_PLANER_TOKEN || data.token || '';
  const mapped = config && filePath ? data.projects?.[projectKey(config.path, resolve(cwd, filePath))] : undefined;
  const project = projectOption ?? (env.MOLLO_PLANER_PROJECT || undefined) ?? mapped;

  if (config && data.token && !env.MOLLO_PLANER_TOKEN && configIsExposed(config.path)) {
    warn(`Warnung: ${config.path} enthält den Zugang, ist aber in Git eingecheckt oder nicht in .gitignore. Zugang schützen.`);
  }
  if (config && data.token && configIsReadableByOthers(config.path)) {
    warn(`Warnung: ${config.path} enthält den Zugang und ist für andere Benutzer lesbar. chmod 600 ${config.path}`);
  }
  if (!url) {
    throw new RemoteError('Keine Adresse der App. MOLLO_PLANER_URL setzen oder „url“ in .mollo-planer eintragen.', { exit: 1 });
  }
  if (!token) {
    throw new RemoteError('Kein Zugang. MOLLO_PLANER_TOKEN setzen oder „token“ in .mollo-planer eintragen (Profil › Zugang für Claude).', { exit: 1 });
  }

  return { url, token, project: project === undefined ? undefined : String(project), configPath: config?.path ?? null };
}

/** Trägt die Zuordnung Datei → Projekt in eine vorhandene `.mollo-planer` ein. Den Zugang rührt es nicht an. */
export function rememberProject(configPath, filePath, projectId) {
  const data = JSON.parse(readFileSync(configPath, 'utf8'));
  const key = projectKey(configPath, filePath);
  if (String(data.projects?.[key]) === String(projectId)) {
    return false;
  }
  data.projects = { ...(data.projects ?? {}), [key]: Number(projectId) };
  writeFileSync(configPath, `${JSON.stringify(data, null, 2)}\n`);
  return true;
}

/** Ein Werkzeug des Servers meldete einen Fehler (`isError`). `payload` ist das JSON, falls es eines war. */
export class ToolError extends RemoteError {
  constructor(text, payload) {
    super(text, { exit: 2 });
    this.payload = payload;
  }
}

/** MCP-Client für `POST <url>/mcp`. `fetch` ist austauschbar (Tests). */
export class McpClient {
  constructor({ url, token, fetch: fetchImpl = globalThis.fetch, timeoutMs = 30_000 }) {
    this.endpoint = `${url}/mcp`;
    this.url = url;
    this.token = token;
    this.fetch = fetchImpl;
    this.timeoutMs = timeoutMs;
    this.session = null;
    this.initialized = false;
    this.id = 0;
  }

  async rpc(method, params) {
    const headers = {
      'Authorization': `Bearer ${this.token}`,
      'Accept': 'application/json, text/event-stream',
      'Content-Type': 'application/json',
    };
    if (this.session) {
      headers['Mcp-Session-Id'] = this.session;
    }

    let response;
    try {
      response = await this.fetch(this.endpoint, {
        method: 'POST',
        headers,
        body: JSON.stringify({ jsonrpc: '2.0', id: ++this.id, method, params }),
        signal: AbortSignal.timeout(this.timeoutMs),
      });
    } catch (error) {
      const timedOut = error?.name === 'TimeoutError' || error?.name === 'AbortError';
      throw new RemoteError(timedOut
        ? `Zeitüberschreitung nach ${Math.round(this.timeoutMs / 1000)} s bei ${this.url}.`
        : `Keine Verbindung zu ${this.url}: ${error?.cause?.code ?? error?.message ?? 'unbekannter Fehler'}.`, { kind: 'network' });
    }

    if (response.status === 401 || response.status === 403) {
      throw new RemoteError('Zugang ungültig oder abgelaufen. Neuer Zugang: Profil › Zugang für Claude.', { kind: 'auth' });
    }
    if (response.status === 429) {
      throw new RemoteError('Zu viele Anfragen. In einer Minute erneut versuchen.', { kind: 'rate' });
    }
    if (!response.ok) {
      throw new RemoteError(`Die App antwortet mit HTTP ${response.status} (${this.url}).`, { kind: response.status >= 500 ? 'network' : 'other' });
    }

    const session = response.headers?.get?.('mcp-session-id');
    if (session) {
      this.session = session;
    }

    let body;
    try {
      body = await response.json();
    } catch {
      throw new RemoteError(`Unerwartete Antwort der App (${this.url}): kein JSON.`);
    }
    if (body.error) {
      throw new RemoteError(`Fehler der App: ${body.error.message ?? 'unbekannt'}.`, { exit: 2 });
    }
    return body.result;
  }

  async initialize() {
    if (this.initialized) {
      return;
    }
    await this.rpc('initialize', {
      protocolVersion: '2025-06-18',
      capabilities: {},
      clientInfo: { name: 'mollo-planer', version: '2.0.0' },
    });
    this.initialized = true;
  }

  /** Ruft ein Werkzeug auf und gibt das JSON seiner Antwort zurück. Bei `isError` wird ein ToolError geworfen. */
  async call(name, args) {
    await this.initialize();
    const result = await this.rpc('tools/call', { name, arguments: args });
    const text = (result?.content ?? []).filter((part) => part.type === 'text').map((part) => part.text).join('\n');
    let payload = null;
    try {
      payload = JSON.parse(text);
    } catch {
      payload = null;
    }
    if (result?.isError) {
      throw new ToolError(text, payload);
    }
    if (payload === null) {
      throw new RemoteError('Unerwartete Antwort der App: kein JSON im Werkzeug-Ergebnis.', { exit: 2 });
    }
    return payload;
  }
}
