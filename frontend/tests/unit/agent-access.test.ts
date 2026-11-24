import { describe, expect, it } from 'vitest'
import { agentAccessState } from '../../app/utils/agent-access'

const now = new Date('2026-11-24T12:00:00Z')
const inDays = (days: number) => new Date(now.getTime() + days * 86_400_000).toISOString()

describe('agentAccessState', () => {
  it('is none without an access', () => {
    expect(agentAccessState([], now)).toBe('none')
    expect(agentAccessState(null, now)).toBe('none')
  })

  it('is active for an access without an end or with an end far away', () => {
    expect(agentAccessState([{ expiresAt: null }], now)).toBe('active')
    expect(agentAccessState([{ expiresAt: inDays(60) }], now)).toBe('active')
  })

  it('is expiring when the latest end is within 14 days', () => {
    expect(agentAccessState([{ expiresAt: inDays(13) }], now)).toBe('expiring')
    expect(agentAccessState([{ expiresAt: inDays(15) }], now)).toBe('active')
  })

  it('is expired when all accesses are over', () => {
    expect(agentAccessState([{ expiresAt: inDays(-1) }, { expiresAt: inDays(-30) }], now)).toBe('expired')
  })

  it('looks at the accesses still valid when they are mixed', () => {
    expect(agentAccessState([{ expiresAt: inDays(-1) }, { expiresAt: inDays(5) }], now)).toBe('expiring')
    expect(agentAccessState([{ expiresAt: inDays(-1) }, { expiresAt: null }], now)).toBe('active')
    expect(agentAccessState([{ expiresAt: inDays(5) }, { expiresAt: null }], now)).toBe('active')
  })
})
