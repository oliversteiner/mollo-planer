import { describe, expect, it } from 'vitest'
import type { Occasion } from '../../app/utils/account-outcome'
import { accountOutcome } from '../../app/utils/account-outcome'
import type { ApiErrorKind } from '../../app/utils/api'

function outcome(problem: ApiErrorKind | null, occasion: Occasion, fullPath = '/login', redirect: unknown = undefined) {
  return accountOutcome(problem, { occasion, fullPath, redirect })
}

describe('what an account page does with the answer of the viewer check', () => {
  it('goes on into the app, or to the page the person wanted (never to another site)', () => {
    expect(outcome(null, 'submitted')).toEqual({ kind: 'go', to: '/', replace: false })
    expect(outcome(null, 'opened', '/login', '/projects/3')).toEqual({ kind: 'go', to: '/projects/3', replace: false })
    expect(outcome(null, 'continued', '/setup-security', 'https://evil.example')).toEqual({ kind: 'go', to: '/', replace: false })
  })

  it('leads to the setup, except on the setup page itself', () => {
    expect(outcome('setup-required', 'submitted')).toEqual({ kind: 'go', to: '/setup-security', replace: false })
    expect(outcome('setup-required', 'continued', '/setup-security?redirect=/x')).toEqual({ kind: 'stay' })
    expect(outcome('setup-required', 'opened', '/setup-security')).toEqual({ kind: 'stay' })
  })

  it('leads a waiting account to its page, and stays when it is there already', () => {
    expect(outcome('unverified', 'submitted')).toEqual({ kind: 'go', to: '/account-pending?reason=email', replace: false })
    expect(outcome('awaiting-approval', 'continued', '/account-pending?reason=email')).toEqual({ kind: 'go', to: '/account-pending?reason=approval', replace: true })
    expect(outcome('awaiting-approval', 'continued', '/account-pending?reason=approval')).toEqual({ kind: 'stay' })
  })

  it('says nothing when a page is only opened and the session is half done or gone', () => {
    for (const problem of ['unauthenticated', 'reauth-required', 'network', 'csrf', 'unknown', 'unauthorized'] as const) {
      expect(outcome(problem, 'opened')).toEqual({ kind: 'stay' })
    }
  })

  it('after a password, a session that lacks the second factor means: sign in with the passkey', () => {
    expect(outcome('reauth-required', 'submitted')).toEqual({ kind: 'passkey-needed' })
  })

  it('after a finished step, no session or no strong one means: sign in again', () => {
    expect(outcome('reauth-required', 'continued', '/setup-security')).toEqual({ kind: 'signed-out' })
    expect(outcome('unauthenticated', 'continued', '/setup-security')).toEqual({ kind: 'signed-out' })
  })

  it('a missing session right after a login is a failure, not a normal case', () => {
    expect(outcome('unauthenticated', 'submitted')).toEqual({ kind: 'failed', failure: 'unknown' })
  })

  it('says what went wrong when the person asked for something', () => {
    for (const occasion of ['submitted', 'continued'] as const) {
      expect(outcome('network', occasion)).toEqual({ kind: 'failed', failure: 'network' })
      expect(outcome('csrf', occasion)).toEqual({ kind: 'failed', failure: 'csrf' })
      expect(outcome('unauthorized', occasion)).toEqual({ kind: 'failed', failure: 'unauthorized' })
      expect(outcome('unknown', occasion)).toEqual({ kind: 'failed', failure: 'unknown' })
      expect(outcome('validation', occasion)).toEqual({ kind: 'failed', failure: 'unknown' })
    }
  })
})
