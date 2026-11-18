import type { ApiErrorKind } from './api'
import { pendingAccountPath, safeRedirect } from './api'

/**
 * What an account page does with the answer of the viewer check (`useViewer().load()`), in one place for
 * the login, the setup of the first factor, the page of a waiting account and the page of the confirmation link.
 * Pure, so it can be tested without Nuxt; `useAccountFlow` carries the outcome out.
 */

/** When the viewer was asked. */
export type Occasion
  /** The page was just opened: a half-finished session is the normal case, so nothing is said. */
  = | 'opened'
  /** The person just sent the form (password, code, passkey). */
    | 'submitted'
  /** A step is done (first factor set up, address confirmed) or the person asked to check again. */
    | 'continued'

export type Failure = 'unauthorized' | 'csrf' | 'network' | 'unknown'

export type AccountOutcome
  /** Go on to this page. */
  = | { kind: 'go', to: string, replace: boolean }
  /** Stay where you are, without a message. */
    | { kind: 'stay' }
  /** No session, or one that did not use the second factor: sign in (again). */
    | { kind: 'signed-out' }
  /** After a password: the account is protected by a passkey, sign in with it. */
    | { kind: 'passkey-needed' }
  /** Something went wrong: say what, stay. */
    | { kind: 'failed', failure: Failure }

export interface OutcomeContext {
  occasion: Occasion
  /** The address the person is on, with the query. */
  fullPath: string
  /** The `redirect` query of the page (where the person wanted to go). */
  redirect: unknown
}

export const SETUP_PATH = '/setup-security'

export function accountOutcome(problem: ApiErrorKind | null, context: OutcomeContext): AccountOutcome {
  const { occasion, fullPath } = context

  if (problem === null) {
    return { kind: 'go', to: safeRedirect(context.redirect), replace: false }
  }

  if (problem === 'setup-required') {
    return fullPath.split('?')[0] === SETUP_PATH ? { kind: 'stay' } : { kind: 'go', to: SETUP_PATH, replace: false }
  }

  const pendingPath = pendingAccountPath(problem)

  if (pendingPath) {
    return pendingPath === fullPath ? { kind: 'stay' } : { kind: 'go', to: pendingPath, replace: fullPath.startsWith('/account-pending') }
  }

  if (occasion === 'opened') {
    return { kind: 'stay' }
  }

  if (problem === 'reauth-required') {
    return occasion === 'submitted' ? { kind: 'passkey-needed' } : { kind: 'signed-out' }
  }

  if (problem === 'unauthenticated') {
    return occasion === 'submitted' ? { kind: 'failed', failure: 'unknown' } : { kind: 'signed-out' }
  }

  if (problem === 'unauthorized' || problem === 'csrf' || problem === 'network') {
    return { kind: 'failed', failure: problem }
  }

  return { kind: 'failed', failure: 'unknown' }
}
