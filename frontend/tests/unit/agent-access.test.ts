import { describe, expect, it } from 'vitest'
import { ACCESS_NAME_MAX, agentAccessState, agentVariablePart, defaultAccessName } from '../../app/utils/agent-access'

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

describe('defaultAccessName', () => {
  it('names the variable after the agent', () => {
    expect(defaultAccessName('Stuard P')).toBe('MOLLOPLANER_STUARD_P_TOKEN')
    expect(defaultAccessName('Bob')).toBe('MOLLOPLANER_BOB_TOKEN')
  })

  it('turns umlauts into AE OE UE and every other character into one underscore', () => {
    expect(agentVariablePart('Jörg Müller-Äpfel')).toBe('JOERG_MUELLER_AEPFEL')
    expect(agentVariablePart('  --Claude   Club DB!!  ')).toBe('CLAUDE_CLUB_DB')
    expect(agentVariablePart('Agent 007')).toBe('AGENT_007')
  })

  it('counts up when the agent already has an access of that name', () => {
    expect(defaultAccessName('Bob', ['MOLLOPLANER_BOB_TOKEN'])).toBe('MOLLOPLANER_BOB_TOKEN_02')
    expect(defaultAccessName('Bob', ['MOLLOPLANER_BOB_TOKEN', 'MOLLOPLANER_BOB_TOKEN_02'])).toBe('MOLLOPLANER_BOB_TOKEN_03')
    expect(defaultAccessName('Bob', ['mølloplaner_bob_token', 'Mollo'])).toBe('MOLLOPLANER_BOB_TOKEN')
    expect(defaultAccessName('Bob', ['molloplaner_bob_token'])).toBe('MOLLOPLANER_BOB_TOKEN_02')
  })

  it('stays within 60 characters by cutting the agent part, with and without a counter', () => {
    const long = 'Ein sehr langer Name eines Agenten, der nie in eine Variable passt'
    const first = defaultAccessName(long)
    const second = defaultAccessName(long, [first])

    expect(first.length).toBeLessThanOrEqual(ACCESS_NAME_MAX)
    expect(first.startsWith('MOLLOPLANER_EIN_SEHR')).toBe(true)
    expect(first.endsWith('_TOKEN')).toBe(true)
    expect(second.length).toBeLessThanOrEqual(ACCESS_NAME_MAX)
    expect(second.endsWith('_TOKEN_02')).toBe(true)
    expect(second).not.toBe(first)
  })

  it('has a fallback for a name without letters', () => {
    expect(defaultAccessName('!!!')).toBe('MOLLOPLANER_AGENT_TOKEN')
  })
})
