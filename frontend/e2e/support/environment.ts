import { tmpdir } from 'node:os'
import { join } from 'node:path'

export const BACKEND_URL = 'http://localhost:8100'
export const FRONTEND_URL = 'http://localhost:3100'
export const MAILPIT_URL = process.env.MAILPIT_URL ?? 'http://localhost:8025'
export const DATABASE = join(tmpdir(), 'mollo-planer-e2e.sqlite')
/** The compiled GraphQL schema of the tests, a file of its own (never bootstrap/cache of the development machine). */
export const SCHEMA_CACHE = join(tmpdir(), 'mollo-planer-e2e-lighthouse-schema.php')

/** Password and second factor of the seeded accounts. Only for the throwaway database of these tests. */
export const E2E_PASSWORD = 'e2e-password-12345'
export const E2E_TOTP_SECRET = 'JBSWY3DPEHPK3PXP'
export const E2E_RECOVERY_CODES = ['e2e-recovery-1', 'e2e-recovery-2', 'e2e-recovery-3']

/**
 * The environment of the backend of the browser tests. Everything that could reach something real is set here:
 * a SQLite file of its own, the queue runs at once (the mail is there when the test looks), no network check of
 * passwords, sessions in the database (so ending them can be seen). The mail goes to the Mailpit of the development
 * machine (the addresses of the tests are unique, nothing else of it is touched).
 *
 * APP_ENV is "production" like on the server: Lighthouse then reads the compiled schema (global-setup builds it before
 * the first request), as production does, so a schema that does not match the code (a resolver class that is gone)
 * fails here too. Only the seeding runs as "local" (the seeder refuses production). Nothing else had to change: no HTTPS is assumed (cookies are not forced to be secure, no redirect),
 * the debug mode is off already.
 */
export const backendEnv: Record<string, string> = {
  APP_ENV: 'production',
  LIGHTHOUSE_SCHEMA_CACHE_ENABLE: 'true',
  LIGHTHOUSE_SCHEMA_CACHE_PATH: SCHEMA_CACHE,
  APP_DEBUG: 'false',
  APP_URL: BACKEND_URL,
  FRONTEND_URL,
  DB_CONNECTION: 'sqlite',
  DB_DATABASE: DATABASE,
  SESSION_DRIVER: 'database',
  CACHE_STORE: 'database',
  QUEUE_CONNECTION: 'sync',
  MAIL_MAILER: 'smtp',
  MAIL_HOST: '127.0.0.1',
  MAIL_PORT: '1025',
  MAIL_USERNAME: '',
  MAIL_PASSWORD: '',
  MAIL_ENCRYPTION: '',
  PASSWORD_UNCOMPROMISED_CHECK: 'false',
  // The seeding runs as "local": the accounts of this throwaway database must not go to the snapshots of the
  // developer's own accounts (Auftrag 45).
  LOCAL_ACCOUNTS_KEEP: 'false',
  SANCTUM_STATEFUL_DOMAINS: 'localhost:3100',
  // Frontend and API are both on "localhost" (other ports): the cookie of the session and the relying party of the
  // passkeys are "localhost". The virtual authenticator verifies the user (the app asks for it).
  SESSION_DOMAIN: 'localhost',
  PASSKEYS_RELYING_PARTY_ID: 'localhost',
  E2E_PASSWORD,
  E2E_TOTP_SECRET,
  E2E_RECOVERY_CODES: E2E_RECOVERY_CODES.join(','),
  CORS_ALLOWED_ORIGINS: '',
  LOG_CHANNEL: 'stderr',
  ANTHROPIC_API_KEY: ''
}
