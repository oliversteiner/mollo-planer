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

/** The backend takes up to 60 characters for the name of an access (createAgentToken). */
export const ACCESS_NAME_MAX = 60

const UMLAUTS: Record<string, string> = { ä: 'AE', ö: 'OE', ü: 'UE', Ä: 'AE', Ö: 'OE', Ü: 'UE' }

/**
 * The part of an environment variable that names the agent: upper case, ä ö ü as AE OE UE, everything but A–Z and 0–9
 * becomes "_", several "_" become one, none at the edges. "Stuard P" gives "STUARD_P".
 */
export function agentVariablePart(agentName: string): string {
  return agentName
    .replace(/[äöüÄÖÜ]/g, letter => UMLAUTS[letter]!)
    .toUpperCase()
    .replace(/[^A-Z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')
}

/**
 * The suggested name of a new access (Auftrag 55 D): the name of the environment variable the token goes into,
 * `MOLLOPLANER_<AGENT>_TOKEN`. When the agent has an access of that name, `_02`, `_03` … follow. Never longer than 60
 * characters: the agent part is cut, not the frame around it.
 */
export function defaultAccessName(agentName: string, existingNames: readonly string[] = []): string {
  const taken = new Set(existingNames.map(name => name.toUpperCase()))
  const part = agentVariablePart(agentName) || 'AGENT'

  for (let number = 1; ; number++) {
    const suffix = number === 1 ? '' : `_${String(number).padStart(2, '0')}`
    const frame = `MOLLOPLANER_`.length + `_TOKEN`.length + suffix.length
    const name = `MOLLOPLANER_${part.slice(0, ACCESS_NAME_MAX - frame).replace(/_+$/, '')}_TOKEN${suffix}`

    if (!taken.has(name.toUpperCase())) {
      return name
    }
  }
}
