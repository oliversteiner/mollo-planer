import { describe, expect, it } from 'vitest'
import { classifyApiError, classifyRestError, readCookie, SETUP_REQUIRED, STRONG_AUTH_REQUIRED } from '../../app/utils/api'

describe('readCookie', () => {
  it('returns the URL-decoded value, as Laravel expects it in X-XSRF-TOKEN', () => {
    expect(readCookie('locale=de; XSRF-TOKEN=eyJpdiI6%3D%3D; other=1', 'XSRF-TOKEN')).toBe('eyJpdiI6==')
  })

  it('keeps "=" inside the value and returns null for a missing cookie', () => {
    expect(readCookie('a=b=c', 'a')).toBe('b=c')
    expect(readCookie('a=b', 'XSRF-TOKEN')).toBeNull()
  })
})

describe('classifyApiError', () => {
  it('recognises the two codes of the strong-authentication lock (HTTP 403)', () => {
    expect(classifyApiError(403, [{ extensions: { code: SETUP_REQUIRED } }]).kind).toBe('setup-required')
    expect(classifyApiError(403, [{ extensions: { code: STRONG_AUTH_REQUIRED } }]).kind).toBe('reauth-required')
  })

  it('tells "not logged in" from "not allowed" (GraphQL answers with HTTP 200)', () => {
    expect(classifyApiError(null, [{ message: 'Unauthenticated.', extensions: { category: 'authentication' } }]).kind).toBe('unauthenticated')
    expect(classifyApiError(null, [{ message: 'This action is unauthorized.', extensions: { category: 'authorization' } }]).kind).toBe('unauthorized')
  })

  it('collects validation messages per field and keeps the backend message', () => {
    const error = classifyApiError(null, [{
      message: 'Validation failed for the field [createMilestone].',
      extensions: { category: 'validation', validation: { 'input.title': ['Das Feld Titel muss mindestens 2 Zeichen haben.'] } }
    }])

    expect(error.kind).toBe('validation')
    expect(error.fields).toEqual({ 'input.title': ['Das Feld Titel muss mindestens 2 Zeichen haben.'] })
    expect(error.message).toBe('Validation failed for the field [createMilestone].')
  })

  it('treats 419 as an expired CSRF token and status 0 as a network failure', () => {
    expect(classifyApiError(419).kind).toBe('csrf')
    expect(classifyApiError(0).kind).toBe('network')
    expect(classifyApiError(500).kind).toBe('unknown')
  })
})

describe('classifyRestError', () => {
  it('reads Fortify validation errors and the lock code of account routes', () => {
    expect(classifyRestError(422, { message: 'Ungültig.', errors: { email: ['Diese Zugangsdaten sind ungültig.'] } }))
      .toEqual({ kind: 'validation', message: 'Ungültig.', fields: { email: ['Diese Zugangsdaten sind ungültig.'] } })
    expect(classifyRestError(403, { message: 'Bitte melde dich erneut an.', code: STRONG_AUTH_REQUIRED }).kind).toBe('reauth-required')
  })

  it('copes with an empty or non-JSON body', () => {
    expect(classifyRestError(401, null).kind).toBe('unauthenticated')
    expect(classifyRestError(500, 'Server Error').kind).toBe('unknown')
  })
})
