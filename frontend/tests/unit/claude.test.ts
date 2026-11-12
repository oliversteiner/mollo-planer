import { describe, expect, it } from 'vitest'
import { claudeDesktopConfig } from '../../app/utils/claude'

describe('claudeDesktopConfig', () => {
  const config = JSON.parse(claudeDesktopConfig('https://api.planer.mollo.dev/', 'abc|secret')) as {
    mcpServers: Record<string, { command: string, args: string[], env: Record<string, string> }>
  }
  const server = config.mcpServers['mollo-planer']!

  it('starts the mcp-remote bridge against the MCP endpoint', () => {
    expect(server.command).toBe('npx')
    expect(server.args).toEqual(['-y', 'mcp-remote', 'https://api.planer.mollo.dev/mcp', '--header', 'Authorization:${AUTH_TOKEN}'])
  })

  it('keeps the token out of the argument list and passes it in env', () => {
    expect(server.args.join(' ')).not.toContain('abc|secret')
    expect(server.env.AUTH_TOKEN).toBe('Bearer abc|secret')
  })
})
