/**
 * The state of the access of an agent account, for the indicator in the list of users
 * (docs/verwaltung-agenten-anfasser.md §3.2): none, active, running out soon or expired.
 */
export type AgentAccessState = 'none' | 'active' | 'expiring' | 'expired'

/** An access that runs out within this time counts as "running out". */
export const EXPIRING_WITHIN_DAYS = 14

interface TokenWithExpiry {
  expiresAt?: string | null
}

export function agentAccessState(tokens: readonly TokenWithExpiry[] | null | undefined, now: Date = new Date()): AgentAccessState {
  if (!tokens?.length) {
    return 'none'
  }

  const valid = tokens.filter(token => !token.expiresAt || new Date(token.expiresAt) > now)

  if (!valid.length) {
    return 'expired'
  }

  // One access without an end is enough: the agent can work as long as it exists.
  if (valid.some(token => !token.expiresAt)) {
    return 'active'
  }

  const latest = Math.max(...valid.map(token => new Date(token.expiresAt!).getTime()))
  const limit = now.getTime() + EXPIRING_WITHIN_DAYS * 24 * 60 * 60 * 1000

  return latest < limit ? 'expiring' : 'active'
}
