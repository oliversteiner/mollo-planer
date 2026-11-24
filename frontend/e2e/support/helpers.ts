import { execFileSync } from 'node:child_process'
import type { Browser, BrowserContext, Page } from '@playwright/test'
import { expect } from '@playwright/test'
import * as OTPAuth from 'otpauth'
import { backendEnv, E2E_PASSWORD, E2E_RECOVERY_CODES, E2E_TOTP_SECRET, MAILPIT_URL } from './environment'

export const PASSWORD = E2E_PASSWORD
export const TOTP_SECRET = E2E_TOTP_SECRET
const RECOVERY_CODE = E2E_RECOVERY_CODES[0]!

/** A unique address for one test: the tests share a database and a mailbox. */
export function uniqueEmail(label: string): string {
  return `${label}.${Date.now().toString(36)}${Math.floor(Math.random() * 1000)}@e2e.test`
}

/** The code the authenticator app of a secret shows now. */
function totp(secret: string): string {
  return new OTPAuth.TOTP({ secret: OTPAuth.Secret.fromBase32(secret.replace(/\s/g, '')), digits: 6, period: 30, algorithm: 'SHA1' }).generate()
}

/** Runs an artisan command in the backend of the tests (same database, same environment). */
export function artisan(...args: string[]): string {
  return execFileSync('php', ['artisan', ...args, '--no-interaction'], {
    cwd: '../backend',
    env: { ...process.env, ...backendEnv },
    encoding: 'utf8'
  })
}

/** The throttles count per address and minute: tests that send many requests start with a clean slate. */
export function resetThrottles(): void {
  artisan('cache:clear')
}

// ─── Mail ────────────────────────────────────────────────────────

export interface Mail {
  id: string
  subject: string
  to: string
  text: string
  created: string
}

async function mailpit<T>(path: string): Promise<T> {
  const response = await fetch(`${MAILPIT_URL}/api/v1${path}`)

  if (!response.ok) {
    throw new Error(`Mailpit ${path}: ${response.status}. Is Mailpit running (${MAILPIT_URL})?`)
  }

  return await response.json() as T
}

/** All mails to an address, newest first. */
export async function mailsTo(email: string): Promise<Mail[]> {
  const found = await mailpit<{ messages: { ID: string, Subject: string, Created: string, To: { Address: string }[] }[] }>(`/search?query=${encodeURIComponent(`to:${email}`)}&limit=20`)

  return await Promise.all(found.messages.map(async (message) => {
    const full = await mailpit<{ Text: string }>(`/message/${message.ID}`)

    return { id: message.ID, subject: message.Subject, to: message.To[0]?.Address ?? '', text: full.Text, created: message.Created }
  }))
}

/** Waits for a mail to an address whose subject matches, and returns it. */
export async function waitForMail(email: string, subject: RegExp, { after = 0 }: { after?: number } = {}): Promise<Mail> {
  let found: Mail | undefined

  await expect.poll(async () => {
    found = (await mailsTo(email)).find(mail => subject.test(mail.subject) && Date.parse(mail.created) >= after)

    return found !== undefined
  }, { message: `no mail to ${email} with a subject like ${subject}`, timeout: 15_000 }).toBe(true)

  return found!
}

/** The first link of a mail that matches the pattern. */
export function linkIn(mail: Mail, pattern: RegExp): string {
  const link = mail.text.match(pattern)?.[0]

  if (!link) {
    throw new Error(`no link ${pattern} in the mail "${mail.subject}":\n${mail.text}`)
  }

  return link
}

// ─── Browser ─────────────────────────────────────────────────────

/** The warnings of the browser console over the whole run (Web Awesome and Lit in development mode): listed once, not filtered. */
export const consoleWarnings = new Set<string>()

export interface Watch {
  /** Console errors (not the line the browser writes for every 4xx/5xx) and server errors (5xx). */
  problems: string[]
  /** Every response with a status of 400 or more, "METHOD status path". */
  failures: string[]
}

/** Collects what a scenario must not produce: errors in the console and answers of the server with 5xx. */
export function watch(page: Page): Watch {
  const result: Watch = { problems: [], failures: [] }

  page.on('console', (message) => {
    if (message.type() === 'warning') {
      consoleWarnings.add(message.text().slice(0, 240))
    }

    if (message.type() === 'error' && !/Failed to load resource/.test(message.text())) {
      result.problems.push(`console: ${message.text()}`)
    }
  })
  page.on('pageerror', error => result.problems.push(`page error: ${error.message}`))
  page.on('response', async (response) => {
    const url = new URL(response.url())

    if (response.status() >= 400 && !url.pathname.startsWith('/_nuxt') && !url.pathname.startsWith('/__nuxt')) {
      // The lock of the API answers 403 with a code (setup required, account not ready, strong authentication): name it.
      const code = response.status() === 403 && url.pathname === '/api'
        ? await response.json().then((body: { errors?: { extensions?: { code?: string } }[] }) => body.errors?.[0]?.extensions?.code ?? '').catch(() => '')
        : ''
      result.failures.push(`${response.request().method()} ${response.status()} ${url.pathname}${code ? ` ${code}` : ''}`)

      if (response.status() >= 500) {
        result.problems.push(`server error: ${response.request().method()} ${response.status()} ${url.pathname}`)
      }
    }
  })

  return result
}

/** The failures a scenario allows (for example a 422 for a wrong password), everything else is reported. */
export function unexpected(watched: Watch, allowed: RegExp[] = []): string[] {
  return [...watched.problems, ...watched.failures.filter(failure => !allowed.some(pattern => pattern.test(failure)))]
}

export async function fillField(page: Page, name: string, value: string): Promise<void> {
  await page.locator(`wa-input[name="${name}"]`).locator('input').fill(value)
}

export async function register(page: Page, name: string, email: string, password = PASSWORD): Promise<void> {
  await page.goto('/register')
  // The page is a SPA: what is typed before it has started is lost when it starts.
  await page.waitForLoadState('networkidle')
  await fillField(page, 'name', name)
  await fillField(page, 'email', email)
  await fillField(page, 'password', password)
  await fillField(page, 'password_confirmation', password)
  await page.getByRole('button', { name: 'Konto anlegen' }).click()
}

export async function login(page: Page, email: string, password = PASSWORD): Promise<void> {
  await page.goto('/login')
  await page.waitForLoadState('networkidle')
  await fillField(page, 'email', email)
  await fillField(page, 'password', password)
  await page.getByRole('button', { name: 'Anmelden', exact: true }).click()
}

/** The 6 digits of the second factor step. */
export async function enterCode(page: Page, code: string): Promise<void> {
  await page.locator('wa-otp-input').locator('input').first().focus()
  await page.keyboard.type(code)
}

export async function logout(page: Page): Promise<void> {
  await page.evaluate(async (apiBase) => {
    await fetch(`${apiBase}/sanctum/csrf-cookie`, { credentials: 'include' })
    const token = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) ?? [])[1] ?? '')
    await fetch(`${apiBase}/logout`, { method: 'POST', credentials: 'include', headers: { 'X-XSRF-TOKEN': token, 'Accept': 'application/json' } })
  }, 'http://localhost:8100')
}

/** A virtual authenticator (Chromium, WebAuthn): passkeys without a device. */
export async function addVirtualAuthenticator(page: Page): Promise<void> {
  const client = await page.context().newCDPSession(page)

  await client.send('WebAuthn.enable')
  await client.send('WebAuthn.addVirtualAuthenticator', {
    options: { protocol: 'ctap2', transport: 'internal', hasResidentKey: true, hasUserVerification: true, isUserVerified: true, automaticPresenceSimulation: true }
  })
}

// ─── Accounts and codes ──────────────────────────────────────────

let lastCode: { secret: string, code: string } | null = null

/** A code the server has not seen yet: Fortify refuses a code a second time, so wait for the next 30 seconds. */
export async function freshTotp(secret: string): Promise<string> {
  let code = totp(secret)

  if (lastCode?.secret === secret && lastCode.code === code) {
    await new Promise(resolve => setTimeout(resolve, 30_500 - (Date.now() % 30_000)))
    code = totp(secret)
  }

  lastCode = { secret, code }

  return code
}

export interface AccountOptions {
  /** Which second factor the account has. "none": it has to set one up at the next login. */
  factor?: 'app' | 'none' | 'passkey-only' | 'app-and-passkey'
  verified?: boolean
  approved?: boolean
  locale?: 'de' | 'en'
}

/** An account made directly in the database (for scenarios that are not about the onboarding itself). */
export function seedAccount(email: string, options: AccountOptions = {}): { email: string, secret: string } {
  const { factor = 'app', verified = true, approved = true, locale = 'de' } = options
  const hasApp = factor === 'app' || factor === 'app-and-passkey'
  const hasPasskey = factor === 'passkey-only' || factor === 'app-and-passkey'
  const secret = TOTP_SECRET

  artisan('tinker', `--execute=${[
    '$u = new App\\Models\\User(["name" => "Test Person", "email" => "' + email + '", "password" => "' + PASSWORD + '"]);',
    '$u->role = App\\Enums\\UserRole::MITGLIED;',
    '$u->forceFill(["locale" => "' + locale + '", "email_verified_at" => ' + (verified ? 'now()' : 'null') + ', "approved_at" => ' + (approved ? 'now()' : 'null') + (hasApp ? ', "two_factor_secret" => Laravel\\Fortify\\Fortify::currentEncrypter()->encrypt("' + secret + '"), "two_factor_recovery_codes" => Laravel\\Fortify\\Fortify::currentEncrypter()->encrypt(json_encode(["' + RECOVERY_CODE + '-' + Date.now() + '"])), "two_factor_confirmed_at" => now()' : '') + ']);',
    '$u->save();',
    hasPasskey ? '$u->passkeys()->forceCreate(["name" => "Old key", "credential_id" => "old-" . $u->id, "credential" => []]);' : '',
    'echo "ok";'
  ].join(' ')}`)

  return { email, secret }
}

/** What the database says about an account, for the checks that the page cannot show. */
export function accountState(email: string): { exists: boolean, verified: boolean, approved: boolean, app: boolean, passkeys: number, locale: string | null } {
  const out = artisan('tinker', `--execute=${[
    '$u = App\\Models\\User::where("email", "' + email + '")->first();',
    'echo "STATE" . json_encode($u ? ["exists" => true, "verified" => $u->email_verified_at !== null, "approved" => $u->approved_at !== null, "app" => $u->two_factor_confirmed_at !== null, "passkeys" => $u->passkeys()->count(), "locale" => $u->locale] : ["exists" => false, "verified" => false, "approved" => false, "app" => false, "passkeys" => 0, "locale" => null]);'
  ].join(' ')}`)

  return JSON.parse(out.split('STATE')[1]!.trim())
}

/** The admin: logged in once, the session is reused by the scenarios that need an admin. */
let adminState: Awaited<ReturnType<BrowserContext['storageState']>> | null = null

export async function openAdmin(browser: Browser): Promise<{ context: BrowserContext, page: Page, watched: Watch }> {
  if (adminState) {
    const context = await browser.newContext({ storageState: adminState })
    const page = await context.newPage()

    return { context, page, watched: watch(page) }
  }

  const context = await browser.newContext()
  const page = await context.newPage()

  await login(page, 'admin@e2e.test')
  await expect(page.getByRole('heading', { name: 'Zweiter Faktor' })).toBeVisible()
  await enterCode(page, await freshTotp(TOTP_SECRET))
  await expect(page.getByRole('heading', { name: /Guten (Morgen|Tag|Abend)/ })).toBeVisible()
  adminState = await context.storageState()

  return { context, page, watched: watch(page) }
}

/** The dialog that asks for the password before the security of the account is changed. */
export function passwordDialog(page: Page) {
  return page.locator('wa-dialog').filter({ hasText: 'Bitte bestätige dein Passwort' }).filter({ has: page.locator('input[type="password"]') })
}

/** Answers the password dialog if it comes (after the registration it does, see scenario 16b); returns whether it came. */
export async function confirmPasswordIfAsked(page: Page): Promise<boolean> {
  const dialog = passwordDialog(page)
  const came = await page.getByText('Bitte bestätige dein Passwort').waitFor({ state: 'visible', timeout: 3000 }).then(() => true).catch(() => false)

  if (came) {
    await dialog.locator('input[type="password"]').fill(PASSWORD)
    await dialog.getByRole('button', { name: 'Bestätigen' }).click()
    await expect(page.getByText('Bitte bestätige dein Passwort')).toBeHidden()
  }

  return came
}

/** A link to confirm the address that ran out five minutes ago (made as the app makes it, the validity moved into the past). */
export function expiredVerificationLink(email: string): string {
  const out = artisan('tinker', '--execute=' + [
    '$u = App\\Models\\User::where("email", "' + email + '")->first();',
    'Illuminate\\Support\\Facades\\URL::forceRootUrl("http://localhost:8100");',
    'echo "LINK" . Illuminate\\Support\\Facades\\URL::temporarySignedRoute("verification.verify", now()->subMinutes(5), ["id" => $u->id, "hash" => sha1($u->getEmailForVerification())]);'
  ].join(' '))

  return out.split('LINK')[1]!.trim()
}

/** The admin resets the second factor of a person in the interface (the dialog asks whether the request was checked). */
export async function resetTwoFactorInUi(adminPage: Page, email: string, name = 'Test Person'): Promise<void> {
  await adminPage.goto('/admin/users')
  const row = adminPage.locator('li').filter({ hasText: email })

  await row.locator(`wa-button[aria-label="Aktionen für ${name}"]`).click()
  await row.locator('wa-dropdown-item', { hasText: '2FA zurücksetzen' }).click()
  await expect(adminPage.getByText(`Hast du auf anderem Weg geprüft, dass die Anfrage wirklich von ${name} kommt (Anruf, persönlich)?`)).toBeVisible()
  await expectAtMostOneDialog(adminPage)
  await adminPage.getByRole('button', { name: 'Ja, zurücksetzen' }).click()
  // The result that stays: the row of the person now says that they have no second factor (a toast is gone after seconds).
  await expect(row).toContainText('Kein zweiter Faktor')
}

/** The admin releases a person: the row leaves "Wartet auf Freigabe" and shows in the members with its menu. */
export async function approveInUi(adminPage: Page, email: string): Promise<void> {
  const row = adminPage.locator('li').filter({ hasText: email })

  await row.getByRole('button', { name: 'Freigeben' }).click()
  await expect(row.getByRole('button', { name: 'Freigeben' })).toHaveCount(0)
  await expect(row.locator('wa-button[aria-label^="Aktionen für"]')).toHaveCount(1)
}

/** The admin rejects a person (with the question before): the row is gone. */
export async function rejectInUi(adminPage: Page, email: string): Promise<void> {
  await adminPage.locator('li').filter({ hasText: email }).getByRole('button', { name: 'Ablehnen' }).click()
  await expectAtMostOneDialog(adminPage)
  await adminPage.getByRole('button', { name: 'Ablehnen' }).last().click()
  await expect(adminPage.locator('li').filter({ hasText: email })).toHaveCount(0)
}

/**
 * Rule R11 (Auftrag 55): no dialog on top of another one, anywhere. Call it after a step that opens or closes a dialog.
 * A dialog that closes fades out for a moment and still counts as open, so this looks again for a while: two that stay
 * are stacked and fail.
 */
export async function expectAtMostOneDialog(page: Page): Promise<void> {
  await expect.poll(() => page.locator('wa-dialog[open]').count(), { message: 'two dialogs are open on top of each other', timeout: 4000 }).toBeLessThanOrEqual(1)
}

/**
 * The login confirmed the password. This makes the confirmation old (the session is changed in the database of the
 * tests), so the next action that needs it asks for the password again.
 */
export function expirePasswordConfirmation(): void {
  // Sessions live in the database of the tests, as base64 of JSON (config/session.php: serialization json).
  artisan('tinker', '--execute=foreach (DB::table("sessions")->get() as $s) { $d = json_decode(base64_decode($s->payload), true); if (isset($d["auth"]["password_confirmed_at"])) { $d["auth"]["password_confirmed_at"] = 0; DB::table("sessions")->where("id", $s->id)->update(["payload" => base64_encode(json_encode($d))]); } }')
}
