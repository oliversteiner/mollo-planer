/**
 * Helpers for talking to the backend that do not depend on Nuxt, so they
 * can be tested on their own.
 */

/** Error codes of the backend lock (EnsureStrongAuthentication). */
export const SETUP_REQUIRED = 'SECOND_FACTOR_SETUP_REQUIRED'
export const STRONG_AUTH_REQUIRED = 'STRONG_AUTHENTICATION_REQUIRED'
/** Self-registered account: mail not confirmed yet, or not approved by an admin (EnsureEmailVerified, EnsureApproved). */
export const ACCOUNT_NOT_READY = 'ACCOUNT_NOT_READY'

/**
 * Value of a cookie, URL-decoded. The backend sets XSRF-TOKEN; the browser
 * must send it back as header X-XSRF-TOKEN (Laravel expects it decoded).
 */
export function readCookie(cookies: string, name: string): string | null {
  for (const part of cookies.split(';')) {
    const [key, ...value] = part.trim().split('=')

    if (key === name) {
      return decodeURIComponent(value.join('='))
    }
  }

  return null
}

export type ApiErrorKind
  /** No second factor yet: lead to the setup. */
  = | 'setup-required'
  /** A factor exists but this session did not use it: log in again. */
    | 'reauth-required'
  /** Not logged in (or session expired). */
    | 'unauthenticated'
  /** Logged in, but not allowed (policy). */
    | 'unauthorized'
  /** Input rejected; messages per field in `fields`. */
    | 'validation'
  /** CSRF token expired (419): fetch a new cookie and retry. */
    | 'csrf'
  /** Self-registered account whose mail address is not confirmed yet. */
    | 'unverified'
  /** Self-registered account waiting for an admin's approval. */
    | 'awaiting-approval'
  /** Backend not reachable. */
    | 'network'
    | 'unknown'

export interface ApiError {
  kind: ApiErrorKind
  /** First message from the backend, already in the request's language. */
  message: string | null
  /** Validation messages per field, for example "input.title". */
  fields: Record<string, string[]>
}

interface GraphQLErrorLike {
  message?: string
  extensions?: {
    code?: string
    reason?: string
    category?: string
    validation?: Record<string, string[]>
  }
}

/** Page for an account that cannot use the app yet, or null for other states. */
export function pendingAccountPath(kind: ApiErrorKind | null): string | null {
  if (kind === 'unverified') {
    return '/account-pending?reason=email'
  }
  if (kind === 'awaiting-approval') {
    return '/account-pending?reason=approval'
  }

  return null
}

/**
 * Sorts a failed request into what the app should do. Accepts the HTTP
 * status (if any) and the GraphQL errors of the response body.
 */
export function classifyApiError(status: number | null, errors: GraphQLErrorLike[] = []): ApiError {
  const first = errors[0]
  const message = first?.message ?? null
  const fields = Object.assign({}, ...errors.map(error => error.extensions?.validation ?? {})) as Record<string, string[]>
  const code = errors.map(error => error.extensions?.code).find(Boolean)
  const reason = errors.map(error => error.extensions?.reason).find(Boolean)
  const categories = errors.map(error => error.extensions?.category)

  const kind: ApiErrorKind = (() => {
    if (code === SETUP_REQUIRED) {
      return 'setup-required'
    }
    if (code === STRONG_AUTH_REQUIRED) {
      return 'reauth-required'
    }
    if (code === ACCOUNT_NOT_READY) {
      return reason === 'email' ? 'unverified' : 'awaiting-approval'
    }
    if (status === 419) {
      return 'csrf'
    }
    if (status === 401 || categories.includes('authentication') || message === 'Unauthenticated.') {
      return 'unauthenticated'
    }
    if (status === 403 || categories.includes('authorization') || message === 'This action is unauthorized.') {
      return 'unauthorized'
    }
    if (status === 422 || Object.keys(fields).length > 0) {
      return 'validation'
    }
    if (status === 0) {
      return 'network'
    }

    return 'unknown'
  })()

  return { kind, message, fields }
}

interface RestErrorBody {
  message?: string
  code?: string
  errors?: Record<string, string[]>
}

/**
 * Fortify and the lock answer REST requests with { message, code? } or,
 * for validation, { message, errors: { field: [...] } }. Same sorting as
 * for GraphQL.
 */
export function classifyRestError(status: number | null, body: unknown): ApiError {
  const rest = (body && typeof body === 'object' ? body : {}) as RestErrorBody

  return classifyApiError(status, [{
    message: rest.message,
    extensions: { code: rest.code, validation: rest.errors }
  }])
}

interface ApolloErrorLike {
  graphQLErrors?: readonly GraphQLErrorLike[]
  networkError?: (Error & { statusCode?: number, result?: unknown }) | null
}

/**
 * Same sorting for an Apollo error (from a query, a mutation or the error
 * link). Accepts anything thrown or returned, so callers need no cast.
 * The lock answers with HTTP 403 and GraphQL errors in the body.
 */
export function classifyApolloError(error: unknown): ApiError {
  const failure = (error && typeof error === 'object' ? error : {}) as ApolloErrorLike
  const network = failure.networkError ?? null
  const status = network ? (network.statusCode ?? 0) : null
  const result = network?.result
  const bodyErrors = result && typeof result === 'object' && 'errors' in result && Array.isArray(result.errors)
    ? result.errors as GraphQLErrorLike[]
    : []

  return classifyApiError(status, failure.graphQLErrors?.length ? [...failure.graphQLErrors] : bodyErrors)
}

/**
 * Only paths inside this app are allowed as target after the login, so a
 * link like /login?redirect=https://evil.example cannot send people away.
 */
export function safeRedirect(target: unknown, fallback = '/'): string {
  if (typeof target !== 'string' || !target.startsWith('/') || target.startsWith('//') || target.startsWith('/\\')) {
    return fallback
  }

  return target
}

/**
 * Leaves out filter arguments that are null. Lighthouse applies @eq also
 * to an explicit null ("column IS NULL"), so { status: null } would find
 * nothing; an argument that is left out means "no filter".
 */
export function withoutNulls<T extends Record<string, unknown>>(variables: T): { [K in keyof T]?: Exclude<T[K], null> } {
  return Object.fromEntries(Object.entries(variables).filter(([, value]) => value !== null)) as { [K in keyof T]?: Exclude<T[K], null> }
}
