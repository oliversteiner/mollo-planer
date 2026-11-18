import { mkdirSync, writeFileSync } from 'node:fs'
import { expect, test } from '@playwright/test'
import type { Page } from '@playwright/test'
import {
  accountState, addVirtualAuthenticator, artisan, confirmPasswordIfAsked, consoleWarnings, expiredVerificationLink, passwordDialog, resetTwoFactorInUi, enterCode, fillField, freshTotp, linkIn, login, logout, mailsTo,
  openAdmin, PASSWORD, register, resetThrottles, seedAccount, TOTP_SECRET, uniqueEmail, unexpected, waitForMail, watch
} from './support/helpers'

/**
 * The whole way from the account to the work area, scenarios 1 to 16 of next-work/20-anmelde-ui-tests.md.
 * Each scenario: the page it ends on, the message it shows, no error in the console, no unexpected 4xx/5xx.
 * What the app does differently from the task is not fixed here: a scenario that finds a deviation is marked
 * with test.fail() and the reason, and the deviation goes into the report to the planning.
 */

/** The API answers 403 with a code while an account is locked (second factor, confirmation, release): expected while it is shown. */
const LOCKED = /(GET|POST) 403 \/api (SECOND_FACTOR_SETUP_REQUIRED|ACCOUNT_NOT_READY|STRONG_AUTHENTICATION_REQUIRED)/
/** Asking for the state without a session (the viewer, the password confirmation) or logging out of a session that is gone. */
const NO_SESSION = /(GET|POST) 401 \/(api|logout|user\/confirmed-password-status)/

test.beforeEach(() => {
  resetThrottles()
})

// The warnings of the console, once for the whole run: Web Awesome and Lit in development mode. Reported, not filtered.
test.afterAll(() => {
  mkdirSync('test-results', { recursive: true })
  writeFileSync('test-results/console-warnings.txt', `${[...consoleWarnings].join('\n')}\n`)
})

/** Text of the page about the confirmation, without the address: for comparing two registrations. */
async function pendingView(page: Page, email: string): Promise<string> {
  await expect(page).toHaveURL(/\/account-pending\?reason=email/)
  await expect(page.getByText(email, { exact: false }).first()).toBeVisible()

  return (await page.locator('wa-card').innerText()).split(email).join('<address>').replace(/\s+/g, ' ')
}

/** Registers, confirms the address with the link of the mail, and arrives at "secure your account". */
async function registerAndConfirm(page: Page, email: string, name = 'Eva Neu'): Promise<void> {
  const before = Date.now() - 1000

  await register(page, name, email)
  await expect(page).toHaveURL(/\/account-pending/)
  const mail = await waitForMail(email, /bestätige deine E-Mail-Adresse/, { after: before })

  await page.goto(linkIn(mail, /http:\/\/localhost:8100\/email\/verify\/\S+/))
  await page.getByRole('button', { name: 'E-Mail-Adresse bestätigen' }).click()
  await expect(page).toHaveURL(/\/setup-security/)
}

async function setUpPasskey(page: Page): Promise<void> {
  await addVirtualAuthenticator(page)
  await page.locator('.setup-choice', { hasText: 'Passkey einrichten' }).click()
  await page.getByRole('button', { name: 'Passkey hinzufügen' }).click()
  // After the registration the password is asked once (scenario 16b); after a login it is not.
  await confirmPasswordIfAsked(page)
}

test.describe('Registrierung', () => {
  test('S1 Pflichtfelder, zu kurzes Passwort, neutrale Antwort bei vergebener Adresse', async ({ page }) => {
    const watched = watch(page)
    await page.goto('/register')

    // Required fields: nothing is sent, the page stays.
    let sent = 0
    page.on('request', (request) => {
      if (request.method() === 'POST' && request.url().endsWith('/register')) {
        sent += 1
      }
    })
    await page.getByRole('button', { name: 'Konto anlegen' }).click()
    await page.waitForTimeout(500)
    expect(sent).toBe(0)
    await expect(page).toHaveURL(/\/register$/)

    // Too short a password (11 characters): the field says so, the page stays.
    const email = uniqueEmail('kurz')
    await fillField(page, 'name', 'Eva Neu')
    await fillField(page, 'email', email)
    await fillField(page, 'password', 'kurz1234567')
    await fillField(page, 'password_confirmation', 'kurz1234567')
    await page.getByRole('button', { name: 'Konto anlegen' }).click()
    await expect(page).toHaveURL(/\/register$/)
    await expect(page.locator('wa-card')).toContainText(/mindestens 12 Zeichen/i)

    // Too long a password: 73 bytes, and 40 characters with umlauts (80 bytes): a clear message at the field, no 500.
    for (const long of ['a'.repeat(73), 'ä'.repeat(40)]) {
      await fillField(page, 'password', long)
      await fillField(page, 'password_confirmation', long)
      await page.getByRole('button', { name: 'Konto anlegen' }).click()
      await expect(page.locator('wa-card')).toContainText('höchstens 72')
      await expect(page).toHaveURL(/\/register$/)
    }

    // The checks above used up the throttle of the registration (5 a minute): a clean slate for the two that follow.
    resetThrottles()

    // A new address and a taken one (other case, spaces at the edge) end on the same page with the same text.
    const fresh = uniqueEmail('neu')
    await register(page, 'Eva Neu', fresh)
    const freshView = await pendingView(page, fresh)

    await logout(page)
    const before = Date.now() - 1000
    await register(page, 'Jemand Anders', ' ADMIN@e2e.TEST ')
    const takenView = await pendingView(page, 'ADMIN@e2e.TEST')

    expect(takenView).toBe(freshView)
    await expect(page.getByRole('button', { name: 'Abmelden' })).toHaveCount(0)
    expect(await accountState('admin@e2e.test')).toMatchObject({ exists: true, approved: true })
    const notice = await waitForMail('admin@e2e.test', /Du hast schon ein Konto/, { after: before })
    expect(notice.text).toContain('melde dich an')

    expect(unexpected(watched, [/POST 422 \/register/, LOCKED, NO_SESSION])).toEqual([])
  })
})

test.describe('Drossel', () => {
  test('S1b Die sechste Registrierung in einer Minute: verständliche Meldung, kein Absturz', async ({ page }) => {
    const watched = watch(page)
    await page.goto('/register')

    const statuses = await page.evaluate(async ({ api, password, emails }) => {
      await fetch(`${api}/sanctum/csrf-cookie`, { credentials: 'include' })
      const result: number[] = []

      for (const email of emails) {
        const token = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) ?? [])[1] ?? '')
        const response = await fetch(`${api}/register`, {
          method: 'POST',
          credentials: 'include',
          headers: { 'X-XSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json' },
          body: JSON.stringify({ name: 'Eva Neu', email, password, password_confirmation: password })
        })
        result.push(response.status)
        // The registration made a new session and a new token: read it again.
        const next = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) ?? [])[1] ?? '')
        await fetch(`${api}/logout`, { method: 'POST', credentials: 'include', headers: { 'X-XSRF-TOKEN': next, 'Accept': 'application/json' } })
      }

      return result
    }, { api: 'http://localhost:8100', password: PASSWORD, emails: Array.from({ length: 5 }, (_, i) => uniqueEmail(`drossel${i}`)) })
    expect(statuses).toEqual([201, 201, 201, 201, 201])

    await fillField(page, 'name', 'Eva Neu')
    await fillField(page, 'email', uniqueEmail('drossel6'))
    await fillField(page, 'password', PASSWORD)
    await fillField(page, 'password_confirmation', PASSWORD)
    await page.getByRole('button', { name: 'Konto anlegen' }).click()

    await expect(page.getByText('Zu viele Versuche. Versuch es in einer Minute nochmals.').first()).toBeVisible()
    await expect(page).toHaveURL(/\/register$/)
    expect(unexpected(watched, [/POST 429 \/register/])).toEqual([])
  })
})

test.describe('E-Mail bestätigen', () => {
  test('S2 Seite «E-Mail bestätigen», Link, Klick, dann «Konto absichern»', async ({ page }) => {
    const watched = watch(page)
    const email = uniqueEmail('s2')
    const before = Date.now() - 1000

    await register(page, 'Eva Neu', email)
    await expect(page.getByRole('heading', { name: 'E-Mail-Adresse bestätigen' })).toBeVisible()
    await expect(page.locator('wa-card')).toContainText('Ein Link in der Mail gilt 60 Minuten.')
    const mail = await waitForMail(email, /bestätige deine E-Mail-Adresse/, { after: before })
    expect(mail.text).toContain('Der Link ist 60 Minuten gültig.')
    expect(mail.text).toContain('Wenn du dich nicht registriert hast, klick nicht auf den Link')

    await page.goto(linkIn(mail, /http:\/\/localhost:8100\/email\/verify\/\S+/))
    await expect(page.getByRole('button', { name: 'E-Mail-Adresse bestätigen' })).toBeVisible()
    expect(accountState(email).verified).toBe(false)

    await page.getByRole('button', { name: 'E-Mail-Adresse bestätigen' }).click()
    await expect(page).toHaveURL(/\/setup-security/)
    expect(accountState(email).verified).toBe(true)
    await expect(page.getByRole('heading', { name: 'Konto absichern' })).toBeVisible()

    expect(unexpected(watched, [LOCKED, NO_SESSION])).toEqual([])
  })

  test('S3 Der Admin gibt frei: Mail an die Person, «Erneut prüfen» führt auf die Arbeitsfläche', async ({ page, browser }) => {
    const watched = watch(page)
    const email = uniqueEmail('s3')

    await registerAndConfirm(page, email)
    await setUpPasskey(page)
    await page.getByRole('button', { name: 'Weiter' }).click()
    await expect(page.getByRole('heading', { name: 'Freigabe ausstehend' })).toBeVisible()

    const admin = await openAdmin(browser)
    await admin.page.goto('/admin/users')
    const row = admin.page.locator('li').filter({ hasText: email })
    const before = Date.now() - 1000
    await row.getByRole('button', { name: 'Freigeben' }).click()
    await expect(admin.page.getByText('ist freigegeben', { exact: false }).first()).toBeVisible()
    expect(unexpected(admin.watched)).toEqual([])
    await admin.context.close()

    const mail = await waitForMail(email, /ist freigegeben/, { after: before })
    expect(mail.text).toContain('Dein Konto ist freigegeben. Du kannst dich jetzt anmelden.')

    await page.getByRole('button', { name: 'Erneut prüfen' }).click()
    await expect(page.getByRole('heading', { name: /Guten (Morgen|Tag|Abend)/ })).toBeVisible()

    expect(unexpected(watched, [LOCKED, NO_SESSION])).toEqual([])
  })
})

test.describe('Konto absichern', () => {
  test('S4 und S13 Passkey: Erklärung, Name, Abschlussschritt, «Weiter» führt zu «Freigabe ausstehend»', async ({ page }) => {
    const watched = watch(page)
    await registerAndConfirm(page, uniqueEmail('s4'))

    await addVirtualAuthenticator(page)
    await page.locator('.setup-choice', { hasText: 'Passkey einrichten' }).click()
    await expect(page.locator('wa-card')).toContainText('Schritt 1 von 2: Faktor einrichten')
    await expect(page.locator('wa-card')).toContainText('Ein Passkey ist ein Schlüssel auf deinem Gerät')
    await expect(page.locator('wa-input').locator('input').first()).not.toHaveValue('')

    await page.getByRole('button', { name: 'Passkey hinzufügen' }).click()
    await confirmPasswordIfAsked(page)
    await expect(page.locator('wa-card')).toContainText('Dein Passkey ist eingerichtet.')
    await expect(page.locator('wa-card')).toContainText('Schritt 2 von 2: Abschluss')
    await expect(page.locator('wa-card')).toContainText('Richte einen zweiten Weg ein')
    await page.getByRole('button', { name: 'Weiter' }).click()
    await expect(page.getByRole('heading', { name: 'Freigabe ausstehend' })).toBeVisible()

    expect(unexpected(watched, [LOCKED, NO_SESSION])).toEqual([])
  })

  test('S13b Abbruch des Passkey-Dialogs: Meldung, die Seite bleibt', async ({ page }) => {
    const watched = watch(page)
    await registerAndConfirm(page, uniqueEmail('s13'))
    const client = await page.context().newCDPSession(page)
    await client.send('WebAuthn.enable')
    await client.send('WebAuthn.addVirtualAuthenticator', {
      options: { protocol: 'ctap2', transport: 'internal', hasResidentKey: true, hasUserVerification: true, isUserVerified: false, automaticPresenceSimulation: true }
    })

    await page.locator('.setup-choice', { hasText: 'Passkey einrichten' }).click()
    await page.getByRole('button', { name: 'Passkey hinzufügen' }).click()
    await confirmPasswordIfAsked(page)

    await expect(page.getByText('Kein Passkey angelegt. Versuch es nochmals oder nimm die Authenticator-App.').first()).toBeVisible()
    await expect(page).toHaveURL(/\/setup-security/)
    await expect(page.locator('wa-card')).not.toContainText('Dein Passkey ist eingerichtet.')
    expect(unexpected(watched, [LOCKED, NO_SESSION])).toEqual([])
  })

  test('S5 Authenticator-App und Wiederherstellungscodes', async ({ page }) => {
    const watched = watch(page)
    await registerAndConfirm(page, uniqueEmail('s5'))

    await page.locator('.setup-choice', { hasText: 'Authenticator-App' }).click()
    await expect(page.locator('wa-card')).toContainText('Schritt 1 von 2: Faktor einrichten')
    await page.getByRole('button', { name: 'Authenticator-App einrichten' }).last().click()
    await confirmPasswordIfAsked(page)
    const secret = (await page.locator('.two-factor-key').innerText()).trim()
    await enterCode(page, await freshTotp(secret))
    await page.getByRole('button', { name: 'Bestätigen' }).click()

    await expect(page.locator('wa-card')).toContainText('Schritt 2 von 2: Codes sichern')
    await expect(page.locator('.recovery-codes li')).toHaveCount(8)
    const done = page.getByRole('button', { name: 'Fertig' })
    await expect(done).toBeDisabled()
    await page.getByText('Ich habe die Codes gespeichert.').click()
    await expect(done).toBeEnabled()
    await done.click()
    await expect(page.getByRole('heading', { name: 'Freigabe ausstehend' })).toBeVisible()

    expect(unexpected(watched, [LOCKED, NO_SESSION, /POST 422 \/user\/confirmed-two-factor-authentication/])).toEqual([])
  })
})

test.describe('Anmelden', () => {
  test('S6 Abmelden, Anmelden mit Passwort und Code, mit Wiederherstellungscode, mit Passkey', async ({ page }) => {
    const watched = watch(page)
    const app = uniqueEmail('s6app')
    seedAccount(app, { factor: 'app' })
    const recovery = artisan('tinker', '--execute=echo json_decode(Laravel\\Fortify\\Fortify::currentEncrypter()->decrypt(App\\Models\\User::where("email","' + app + '")->first()->two_factor_recovery_codes))[0];').trim()

    // Password and the code of the app.
    await login(page, app)
    await enterCode(page, await freshTotp(TOTP_SECRET))
    await expect(page.getByRole('heading', { name: /Guten (Morgen|Tag|Abend)/ })).toBeVisible()
    await logout(page)

    // Password and a recovery code.
    await login(page, app)
    await page.getByRole('button', { name: 'Wiederherstellungscode verwenden' }).click()
    await fillField(page, 'recovery_code', recovery)
    await page.getByRole('button', { name: 'Bestätigen' }).click()
    await expect(page.getByRole('heading', { name: /Guten (Morgen|Tag|Abend)/ })).toBeVisible()
    await logout(page)

    // The same recovery code only once.
    await login(page, app)
    await page.getByRole('button', { name: 'Wiederherstellungscode verwenden' }).click()
    await fillField(page, 'recovery_code', recovery)
    await page.getByRole('button', { name: 'Bestätigen' }).click()
    await expect(page.locator('wa-card')).toContainText(/ungültig|nicht|falsch/i)

    expect(unexpected(watched, [/POST 422 \/two-factor-challenge/, LOCKED, NO_SESSION])).toEqual([])
  })

  test('S6b Anmelden mit dem Passkey', async ({ page, browser }) => {
    const email = uniqueEmail('s6pk')
    await registerAndConfirm(page, email)
    await setUpPasskey(page)
    await page.getByRole('button', { name: 'Weiter' }).click()
    await expect(page.getByRole('heading', { name: 'Freigabe ausstehend' })).toBeVisible()
    const admin = await openAdmin(browser)
    await admin.page.goto('/admin/users')
    await admin.page.locator('li').filter({ hasText: email }).getByRole('button', { name: 'Freigeben' }).click()
    await expect(admin.page.getByText('ist freigegeben', { exact: false }).first()).toBeVisible()
    expect(unexpected(admin.watched)).toEqual([])
    await admin.context.close()

    await logout(page)
    await page.goto('/login')
    await page.getByRole('button', { name: 'Mit Passkey anmelden' }).click()

    await expect(page.getByRole('heading', { name: /Guten (Morgen|Tag|Abend)/ })).toBeVisible()
  })

  test('S10 Konto nur mit Passkey: nach dem Passwort der Hinweis, der Passkey-Knopf ist die Hauptaktion', async ({ page }) => {
    const watched = watch(page)
    const email = uniqueEmail('s10')
    seedAccount(email, { factor: 'passkey-only' })

    // Only opened: no hint.
    await page.goto('/login')
    await expect(page.locator('wa-card')).not.toContainText('mit einem Passkey geschützt')

    await fillField(page, 'email', email)
    await fillField(page, 'password', PASSWORD)
    await page.getByRole('button', { name: 'Anmelden', exact: true }).click()

    await expect(page.locator('wa-card')).toContainText('Dein Konto ist mit einem Passkey geschützt. Melde dich mit dem Passkey an.')
    await expect(page).toHaveURL(/\/login/)
    const passkeyButton = page.locator('wa-button', { hasText: 'Mit Passkey anmelden' })
    expect(await passkeyButton.evaluate(element => (element as HTMLElement & { variant: string }).variant)).toBe('brand')
    const submit = page.locator('wa-button').filter({ hasText: 'Anmelden' }).filter({ hasNotText: 'Passkey' })
    expect(await submit.evaluate(element => (element as HTMLElement & { appearance: string }).appearance)).toBe('outlined')

    expect(unexpected(watched, [LOCKED, NO_SESSION])).toEqual([])
  })
})

test.describe('Passwort vergessen', () => {
  test('S7 Mail, Link, neues Passwort, Link nach Gebrauch und nach Ablauf ungültig', async ({ page }) => {
    const watched = watch(page)
    const email = uniqueEmail('s7')
    seedAccount(email, { factor: 'app' })

    async function requestMail(): Promise<string> {
      const before = Date.now() - 1000
      await page.goto('/forgot-password')
      await fillField(page, 'email', email)
      await page.getByRole('button', { name: 'Link schicken' }).click()
      await expect(page.locator('wa-card')).toContainText('Wenn die Adresse bei uns bekannt ist')
      const mail = await waitForMail(email, /Passwort/, { after: before })

      return linkIn(mail, /http:\/\/localhost:3100\/reset-password\S+/)
    }

    const link = await requestMail()
    await page.goto(link)
    await fillField(page, 'password', 'neues-passwort-98765')
    await fillField(page, 'password_confirmation', 'neues-passwort-98765')
    await page.getByRole('button', { name: 'Passwort speichern' }).click()
    await expect(page.getByText('Dein Passwort ist geändert').first()).toBeVisible()
    await login(page, email, 'neues-passwort-98765')
    await expect(page.getByRole('heading', { name: 'Zweiter Faktor' })).toBeVisible()
    await enterCode(page, await freshTotp(TOTP_SECRET))
    await expect(page.getByRole('heading', { name: /Guten (Morgen|Tag|Abend)/ })).toBeVisible()
    await logout(page)

    // Used: the same link does nothing any more, and says so in words.
    await page.goto(link)
    await fillField(page, 'password', 'noch-ein-passwort-1234')
    await fillField(page, 'password_confirmation', 'noch-ein-passwort-1234')
    await page.getByRole('button', { name: 'Passwort speichern' }).click()
    await expect(page.locator('wa-card')).toContainText('ungültig oder abgelaufen')

    // Expired: the token is aged by two hours in the database.
    resetThrottles()
    const second = await requestMail()
    artisan('tinker', '--execute=DB::table("password_reset_tokens")->where("email","' + email + '")->update(["created_at" => now()->subHours(2)]); echo "aged";')
    await page.goto(second)
    await fillField(page, 'password', 'wieder-ein-passwort-5678')
    await fillField(page, 'password_confirmation', 'wieder-ein-passwort-5678')
    await page.getByRole('button', { name: 'Passwort speichern' }).click()
    await expect(page.locator('wa-card')).toContainText('ungültig oder abgelaufen')

    expect(unexpected(watched, [/POST 422 \/reset-password/])).toEqual([])
  })

  test('S14 Bekannte und unbekannte Adresse zeigen denselben Text', async ({ page }) => {
    const watched = watch(page)
    const known = uniqueEmail('s14')
    seedAccount(known, { factor: 'app' })
    const texts: string[] = []

    for (const email of [known, uniqueEmail('unbekannt')]) {
      await page.goto('/forgot-password')
      await fillField(page, 'email', email)
      await page.getByRole('button', { name: 'Link schicken' }).click()
      await expect(page.locator('wa-card')).toContainText('Wenn die Adresse bei uns bekannt ist')
      texts.push((await page.locator('wa-card').innerText()).replace(/\s+/g, ' '))
    }

    expect(texts[0]).toBe(texts[1])
    expect((await mailsTo(known)).length).toBeGreaterThan(0)
    expect(unexpected(watched, [])).toEqual([])
  })
})

test.describe('Konto zurücksetzen und ablehnen', () => {
  test('S8 Zurückgesetzter zweiter Faktor: die Anmeldung führt wieder durch «Konto absichern»', async ({ page, browser }) => {
    const watched = watch(page)
    const email = uniqueEmail('s8')
    seedAccount(email, { factor: 'app-and-passkey' })
    const admin = await openAdmin(browser)
    await resetTwoFactorInUi(admin.page, email)
    expect(unexpected(admin.watched)).toEqual([])
    await admin.context.close()
    // The old passkey is gone with the reset: nothing of it works any more.
    expect(accountState(email)).toMatchObject({ app: false, passkeys: 0 })

    await login(page, email)
    await expect(page).toHaveURL(/\/setup-security/)
    await setUpPasskey(page)
    await page.getByRole('button', { name: 'Weiter' }).click()
    await expect(page.getByRole('heading', { name: /Guten (Morgen|Tag|Abend)/ })).toBeVisible()
    expect(unexpected(watched, [LOCKED, NO_SESSION])).toEqual([])
  })

  test('S9 Abgelehnt und nicht bestätigt: richtige Zielseite, kein Absturz', async ({ page, browser }) => {
    const watched = watch(page)
    const rejected = uniqueEmail('s9rej')
    const waiting = uniqueEmail('s9wait')

    // Rejected by an admin: the account is gone, the person gets a mail, signing in says so in words.
    seedAccount(rejected, { factor: 'none', approved: false })
    const admin = await openAdmin(browser)
    await admin.page.goto('/admin/users')
    const before = Date.now() - 1000
    await admin.page.locator('li').filter({ hasText: rejected }).getByRole('button', { name: 'Ablehnen' }).click()
    await admin.page.getByRole('button', { name: 'Ablehnen' }).last().click()
    await expect(admin.page.getByText('wurde abgelehnt').first()).toBeVisible()
    expect(unexpected(admin.watched)).toEqual([])
    await admin.context.close()
    expect(accountState(rejected).exists).toBe(false)
    const mail = await waitForMail(rejected, /Deine Registrierung/, { after: before })
    expect(mail.text).toContain('wurde nicht freigegeben und gelöscht')
    await login(page, rejected)
    await expect(page.locator('wa-card')).toContainText(/Zugangsdaten|stimmen nicht/)
    await expect(page).toHaveURL(/\/login/)

    // Not confirmed: the page about the confirmation.
    await logout(page)
    seedAccount(waiting, { factor: 'none', verified: false, approved: false })
    await login(page, waiting)
    await expect(page).toHaveURL(/\/account-pending\?reason=email/)
    await expect(page.getByRole('heading', { name: 'E-Mail-Adresse bestätigen' })).toBeVisible()

    expect(unexpected(watched, [/POST 422 \/login/, LOCKED, NO_SESSION])).toEqual([])
  })
})

test.describe('Link im zweiten Browserprofil', () => {
  test('S11 Der Link zeigt den Knopf, bestätigt erst nach dem Klick, meldet nicht an; ein veränderter Link zeigt die Fehlerseite', async ({ page, browser }) => {
    const email = uniqueEmail('s11')
    const before = Date.now() - 1000
    await register(page, 'Eva Neu', email)
    const mail = await waitForMail(email, /bestätige deine E-Mail-Adresse/, { after: before })
    const link = linkIn(mail, /http:\/\/localhost:8100\/email\/verify\/\S+/)

    const second = await browser.newContext()
    const other = await second.newPage()
    const watched = watch(other)
    await other.goto(link)
    await expect(other.getByRole('button', { name: 'E-Mail-Adresse bestätigen' })).toBeVisible()
    expect(accountState(email).verified).toBe(false)

    await other.getByRole('button', { name: 'E-Mail-Adresse bestätigen' }).click()
    await expect(other.locator('wa-card')).toContainText('Deine E-Mail-Adresse ist bestätigt.')
    await expect(other.locator('wa-card')).toContainText('Melde dich jetzt an und richte deinen zweiten Faktor ein.')
    await expect(other.getByRole('button', { name: 'Anmelden' })).toBeVisible()
    expect(accountState(email).verified).toBe(true)
    // Nobody was logged in by it.
    await other.goto('/')
    await expect(other).toHaveURL(/\/login/)

    // A changed link: the error page with "send again".
    const broken = link.replace(/signature=(.)/, 'signature=0$1')
    await other.goto(broken)
    await expect(other.locator('wa-card')).toContainText('Der Link ist ungültig oder abgelaufen.')
    await expect(other.getByRole('button', { name: 'Bestätigungsmail erneut senden' })).toBeVisible()

    // An expired link (made five minutes in the past): the same error page, and "send again" works without a session.
    await other.goto(expiredVerificationLink(email))
    await expect(other.locator('wa-card')).toContainText('Der Link ist ungültig oder abgelaufen.')
    await fillField(other, 'email', email)
    await other.getByRole('button', { name: 'Bestätigungsmail erneut senden' }).click()
    await expect(other.locator('wa-card')).toContainText('Wenn ein Konto mit dieser Adresse auf Bestätigung wartet')

    expect(unexpected(watched, [NO_SESSION, LOCKED])).toEqual([])
    await second.close()
  })
})

test.describe('Mails von Freigabe und Ablehnung', () => {
  test('S12 Registrierung im englischen Frontend, Entscheid des Admins im deutschen: die Mails an die Person sind englisch, auch nach dem Löschen', async ({ browser }) => {
    const approved = uniqueEmail('s12ok')
    const rejected = uniqueEmail('s12no')
    const reset = uniqueEmail('s12re')
    seedAccount(reset, { factor: 'app', locale: 'en' })

    // Two people register in the English frontend and confirm their address there.
    for (const email of [approved, rejected]) {
      const context = await browser.newContext({ locale: 'en-US' })
      const page = await context.newPage()
      const before = Date.now() - 1000

      await page.goto('/register')
      await fillField(page, 'name', 'Eve New')
      await fillField(page, 'email', email)
      await fillField(page, 'password', PASSWORD)
      await fillField(page, 'password_confirmation', PASSWORD)
      await page.getByRole('button', { name: 'Create account' }).click()
      await expect(page.getByRole('heading', { name: 'Confirm email address' })).toBeVisible()
      const mail = await waitForMail(email, /confirm your email address/i, { after: before })
      await page.goto(linkIn(mail, /http:\/\/localhost:8100\/email\/verify\/\S+/))
      await page.getByRole('button', { name: 'Confirm email address' }).click()
      await expect(page).toHaveURL(/\/setup-security/)
      await context.close()
    }

    // The admin decides in the German interface.
    const admin = await openAdmin(browser)
    await admin.page.goto('/admin/users')
    const before = Date.now() - 1000
    await admin.page.locator('li').filter({ hasText: approved }).getByRole('button', { name: 'Freigeben' }).click()
    await expect(admin.page.getByText('ist freigegeben', { exact: false }).first()).toBeVisible()
    await admin.page.locator('li').filter({ hasText: rejected }).getByRole('button', { name: 'Ablehnen' }).click()
    await admin.page.getByRole('button', { name: 'Ablehnen' }).last().click()
    await expect(admin.page.getByText('wurde abgelehnt').first()).toBeVisible()
    await resetTwoFactorInUi(admin.page, reset)
    expect(unexpected(admin.watched)).toEqual([])
    await admin.context.close()

    const approval = await waitForMail(approved, /released/, { after: before })
    expect(approval.text).toContain('Your account is released. You can sign in now.')
    const rejection = await waitForMail(rejected, /Your registration/, { after: before })
    expect(rejection.text).toContain('was not released and has been deleted')
    expect(accountState(rejected).exists).toBe(false)
    const resetMail = await waitForMail(reset, /second factor was reset/, { after: before })
    expect(resetMail.text).toContain('Your second factor was reset by an admin.')
    expect(accountState(approved).locale).toBe('en')
  })
})

test.describe('Admin', () => {
  test('S15 Der Admin setzt den zweiten Faktor zurück: Rückfrage zur Identität, Mail an die Person', async ({ browser }) => {
    const email = uniqueEmail('s15')
    seedAccount(email, { factor: 'app' })
    const admin = await openAdmin(browser)
    const before = Date.now() - 1000

    await resetTwoFactorInUi(admin.page, email)
    expect(unexpected(admin.watched)).toEqual([])
    await admin.context.close()

    expect(accountState(email).app).toBe(false)
    const mail = await waitForMail(email, /zweiter Faktor wurde zurückgesetzt/, { after: before })
    expect(mail.text).toContain('Dein zweiter Faktor wurde von einem Admin zurückgesetzt.')
    expect(mail.text).toContain('Warst du das nicht, melde dich sofort.')
  })
})

test.describe('Passwort', () => {
  test('S16 Nach der Anmeldung mit Passwort fragt «Konto absichern» das Passwort nicht nochmals ab', async ({ page }) => {
    const watched = watch(page)
    const email = uniqueEmail('s16')
    seedAccount(email, { factor: 'none' })

    await login(page, email)
    await expect(page).toHaveURL(/\/setup-security/)
    await addVirtualAuthenticator(page)
    await page.locator('.setup-choice', { hasText: 'Passkey einrichten' }).click()
    await page.getByRole('button', { name: 'Passkey hinzufügen' }).click()

    await expect(page.locator('wa-card')).toContainText('Dein Passkey ist eingerichtet.')
    await expect(passwordDialog(page)).toHaveCount(0)
    expect(unexpected(watched, [LOCKED, NO_SESSION])).toEqual([])
  })

  test('S16b Auch nach der Registrierung fragt «Konto absichern» das Passwort nicht nochmals ab', async ({ page }) => {
    await registerAndConfirm(page, uniqueEmail('s16b'))
    await addVirtualAuthenticator(page)
    await page.locator('.setup-choice', { hasText: 'Passkey einrichten' }).click()
    await page.getByRole('button', { name: 'Passkey hinzufügen' }).click()

    await expect(page.locator('wa-card')).toContainText('Dein Passkey ist eingerichtet.')
    await expect(passwordDialog(page)).toHaveCount(0)
  })
})

test.describe('Englisch', () => {
  test('Ein Durchlauf auf Englisch: Registrierung bis zur Seite «Confirm email address»', async ({ browser }) => {
    const context = await browser.newContext({ locale: 'en-US' })
    const page = await context.newPage()
    const watched = watch(page)
    const email = uniqueEmail('en')
    const before = Date.now() - 1000

    await page.goto('/register')
    await fillField(page, 'name', 'Eve New')
    await fillField(page, 'email', email)
    await fillField(page, 'password', PASSWORD)
    await fillField(page, 'password_confirmation', PASSWORD)
    await page.getByRole('button', { name: 'Create account' }).click()

    await expect(page.getByRole('heading', { name: 'Confirm email address' })).toBeVisible()
    await expect(page.locator('wa-card')).toContainText('We sent a mail to')
    const mail = await waitForMail(email, /confirm your email address/i, { after: before })
    expect(mail.text).toContain('The link is valid for 60 minutes.')
    expect(accountState(email).locale).toBe('en')

    expect(unexpected(watched, [LOCKED, NO_SESSION])).toEqual([])
    await context.close()
  })
})
