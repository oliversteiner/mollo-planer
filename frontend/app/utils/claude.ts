/**
 * Configuration for Claude Desktop: claude_desktop_config.json with the bridge
 * mcp-remote, which sends the personal token as Authorization header. The token
 * is passed in env, not in the argument list, so it does not show up in process
 * listings.
 */
export function claudeDesktopConfig(apiBase: string, token: string): string {
  const url = `${apiBase.replace(/\/$/, '')}/mcp`

  return JSON.stringify({
    mcpServers: {
      'mollo-planer': {
        command: 'npx',
        args: ['-y', 'mcp-remote', url, '--header', 'Authorization:${AUTH_TOKEN}'],
        env: { AUTH_TOKEN: `Bearer ${token}` }
      }
    }
  }, null, 2)
}
