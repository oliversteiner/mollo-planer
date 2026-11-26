import { mkdirSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { expect, test } from '@playwright/test'
import type { Page } from '@playwright/test'
import { enterCode, expectAtMostOneDialog, expirePasswordConfirmation, freshTotp, login, PASSWORD, resetThrottles, TOTP_SECRET } from './support/helpers'

/**
 * Agents and their accesses (Auftrag 55, rule R11: no dialog on top of another one). Oliver's flow: create an agent
 * (the dialog closes and opens nothing), «Zugang einrichten» in its row, the name of the access is suggested, the token
 * stays visible until «Abgelegt» (Auftrag 58, it was «Fertig»). The password is asked for in a field of the dialog that needs it, never in a dialog of
 * its own. The questions of revoking and removing are in the row. After every step at most one dialog is open.
 * Agents are made here with unique names: the tests share one database.
 */

/** Screenshots for the pull request (rule R1), never into the repository. */
const SCREENSHOT_DIR = process.env.SCREENSHOTS_DIR ?? join(tmpdir(), 'mollo-planer-agent-access')

async function screenshot(page: Page, name: string): Promise<void> {
  mkdirSync(SCREENSHOT_DIR, { recursive: true })
  await page.screenshot({ path: join(SCREENSHOT_DIR, `${name}.png`) })
}

async function signIn(page: Page): Promise<void> {
  // Several sign-ins of the same account in a row would meet the throttle of the login.
  resetThrottles()
  await login(page, 'admin@e2e.test', PASSWORD)
  await expect(page.getByRole('heading', { name: 'Zweiter Faktor' })).toBeVisible()
  await enterCode(page, await freshTotp(TOTP_SECRET))
  await expect(page).toHaveURL(/\/$/)
}

/** The dialog as a whole (visible or not): its own content sits in the shadow root, so it is found by role. */
function accessDialog(page: Page, agent: string) {
  return page.getByRole('dialog', { name: `Zugang für ${agent}` })
}

/** The content of the dialog: slotted into the element, so it is reached through the element, not through the role. */
function accessContent(page: Page, agent: string) {
  return page.locator(`wa-dialog[label="Zugang für ${agent}"]`)
}

function uniqueAgent(): string {
  return `Agent ${Date.now().toString(36)}`
}

/** What the dialog suggests as the name of an access for this agent. */
function suggestedName(agent: string, counter = ''): string {
  return `MOLLOPLANER_${agent.toUpperCase().replace(/[^A-Z0-9]+/g, '_')}_TOKEN${counter}`
}

/** Step 1 of Oliver's flow: the agent is made in one dialog, which closes and opens nothing else. */
async function createAgent(page: Page, name: string): Promise<void> {
  await page.goto('/admin/users')
  await page.getByRole('button', { name: 'Agent anlegen' }).first().click()
  await page.locator('#agent-form wa-input').nth(0).locator('input').fill(name)
  await page.getByRole('button', { name: 'Anlegen', exact: true }).click()
  await expect(page.locator('wa-dialog[open]')).toHaveCount(0)
}

/** Step 2: «Zugang einrichten» in the row of the agent opens the dialog of its access. */
async function openAccessDialog(page: Page, agent: string): Promise<void> {
  await page.locator('.users-row', { hasText: agent }).locator('[data-agent-access]').click()
  await expect(accessDialog(page, agent)).toBeVisible()
  await expectAtMostOneDialog(page)
}

function nameInput(page: Page, agent: string) {
  return accessContent(page, agent).locator('.access-form wa-input input')
}

async function makeAccess(page: Page, agent: string): Promise<void> {
  await accessContent(page, agent).locator('.access-form').getByRole('button', { name: 'Zugang erzeugen' }).click()
}

/** The password field in the dialog (never a dialog of its own). */
function passwordField(page: Page, agent: string) {
  return accessContent(page, agent).locator('.password-confirm')
}

async function answerPassword(page: Page, agent: string): Promise<void> {
  const field = passwordField(page, agent)

  await expect(field).toBeVisible()
  await expectAtMostOneDialog(page)
  await field.locator('wa-input input').fill(PASSWORD)
  await field.getByRole('button', { name: 'Bestätigen' }).click()
}

test.describe('Agent anlegen und Zugang einrichten', () => {
  test.beforeEach(async ({ context }) => {
    await context.grantPermissions(['clipboard-read', 'clipboard-write'])
  })

  test('Olivers Ablauf: Agent anlegen, Dialog zu, Zugang einrichten, Name vorbelegt, Token sichtbar, kopieren, Fertig', async ({ page }) => {
    test.setTimeout(120_000)
    const agent = uniqueAgent()

    await signIn(page)
    await createAgent(page, agent)

    // Step 1 is done: no second dialog, the row is marked and «Zugang einrichten» has the focus.
    const row = page.locator('.users-row', { hasText: agent })
    await expect(row).toHaveAttribute('data-new', '')
    await expect(row.locator('[data-agent-access]')).toHaveText('Zugang einrichten')
    await expect(row.locator('[data-agent-access]')).toBeFocused()
    await screenshot(page, '1-agent-created-row')

    await openAccessDialog(page, agent)
    await expect(nameInput(page, agent)).toHaveValue(suggestedName(agent))
    await makeAccess(page, agent)

    const content = accessContent(page, agent)
    await expect(content.getByText('Wird nicht mehr angezeigt.')).toBeVisible()
    // The dialog stays: give a late event of its own showing and hiding the time to arrive.
    await page.waitForTimeout(1000)
    await expect(content.getByText('Wird nicht mehr angezeigt.')).toBeVisible()
    await expectAtMostOneDialog(page)
    await content.locator('.access-token').scrollIntoViewIfNeeded()
    await screenshot(page, '2-token-light')
    await page.emulateMedia({ colorScheme: 'dark' })
    await screenshot(page, '3-token-dark')
    await page.emulateMedia({ colorScheme: 'light' })

    const token = await content.locator('.access-token input').inputValue()
    expect(token).toMatch(/^\d+\|/)
    await content.locator('wa-button[aria-label="Zugang kopieren"]').click()
    expect(await page.evaluate(() => navigator.clipboard.readText())).toBe(token)

    await content.locator('.wa-stack').getByRole('button', { name: 'Abgelegt', exact: true }).click()
    await expect(content.getByText('Wird nicht mehr angezeigt.')).toBeHidden()
    await expect(content.locator('.access-name', { hasText: suggestedName(agent) })).toBeVisible()
    // The next access already has its suggestion: the name is taken.
    await expect(nameInput(page, agent)).toHaveValue(suggestedName(agent, '_02'))

    await page.keyboard.press('Escape')
    await expect(accessDialog(page, agent)).toBeHidden()
    await expect(page.locator('.users-row', { hasText: agent }).getByRole('button', { name: 'Zugang aktiv' })).toBeVisible()
    await screenshot(page, '4-list')
  })

  test('Name des Zugangs: Vorbelegung ändern, leer gibt einen Fehler am Feld und sendet nichts', async ({ page }) => {
    test.setTimeout(120_000)
    const agent = uniqueAgent()

    await signIn(page)
    await createAgent(page, agent)
    await openAccessDialog(page, agent)

    let sent = 0
    await page.route(url => new URL(url).pathname === '/api', async (route) => {
      if ((route.request().postData() ?? '').includes('CreateAgentToken')) {
        sent++
      }
      await route.continue()
    })

    await nameInput(page, agent).fill('')
    await makeAccess(page, agent)
    await expect(accessContent(page, agent).getByText('Name fehlt.')).toBeVisible()
    expect(sent).toBe(0)

    await nameInput(page, agent).fill('Mein Laptop')
    await expect(accessContent(page, agent).getByText('Name fehlt.')).toBeHidden()
    await makeAccess(page, agent)
    await expect(accessContent(page, agent).getByText('Wird nicht mehr angezeigt.')).toBeVisible()
    await expect(accessContent(page, agent).locator('.access-name', { hasText: 'Mein Laptop' })).toBeVisible()
    expect(sent).toBe(1)
    await screenshot(page, '5-name-missing')
  })

  test('Passwort nötig: das Feld erscheint im selben Dialog, danach läuft die Aktion ohne zweiten Klick', async ({ page }) => {
    test.setTimeout(120_000)
    const agent = uniqueAgent()

    await signIn(page)
    await createAgent(page, agent)
    await openAccessDialog(page, agent)

    expirePasswordConfirmation()
    await makeAccess(page, agent)

    await expect(passwordField(page, agent)).toBeVisible()
    await expectAtMostOneDialog(page)
    await expect(accessContent(page, agent).getByText('Wird nicht mehr angezeigt.')).toBeHidden()
    await screenshot(page, '6-password-field')

    await answerPassword(page, agent)

    await expect(accessContent(page, agent).getByText('Wird nicht mehr angezeigt.')).toBeVisible()
    await expect(passwordField(page, agent)).toBeHidden()
    await expectAtMostOneDialog(page)
    expect(await accessContent(page, agent).locator('.access-token input').inputValue()).toMatch(/^\d+\|/)
  })

  test('abgebrochenes Passwortfeld: nichts erzeugt, der Dialog bleibt, das Eingegebene auch', async ({ page }) => {
    test.setTimeout(120_000)
    const agent = uniqueAgent()

    await signIn(page)
    await createAgent(page, agent)
    await openAccessDialog(page, agent)

    expirePasswordConfirmation()
    await nameInput(page, agent).fill('Mein Server')
    await makeAccess(page, agent)
    await expect(passwordField(page, agent)).toBeVisible()
    await passwordField(page, agent).getByRole('button', { name: 'Abbrechen' }).click()

    await expect(passwordField(page, agent)).toBeHidden()
    await expect(accessDialog(page, agent)).toBeVisible()
    await expect(nameInput(page, agent)).toHaveValue('Mein Server')
    await expect(accessContent(page, agent).getByText('Noch kein Zugang.')).toBeVisible()
    await expect(accessContent(page, agent).getByText('Wird nicht mehr angezeigt.')).toBeHidden()
    await expectAtMostOneDialog(page)
  })

  test('falsches Passwort: die Meldung steht am Feld, nichts wird erzeugt', async ({ page }) => {
    test.setTimeout(120_000)
    const agent = uniqueAgent()

    await signIn(page)
    await createAgent(page, agent)
    await openAccessDialog(page, agent)

    expirePasswordConfirmation()
    await makeAccess(page, agent)
    const field = passwordField(page, agent)

    await field.locator('wa-input input').fill('falsches-passwort-1')
    await field.getByRole('button', { name: 'Bestätigen' }).click()

    await expect(field).toBeVisible()
    await expect(field.getByText('Das Passwort ist falsch.')).toBeVisible()
    await expect(field.locator('wa-input')).toHaveAttribute('data-invalid', '')
    await expect(accessContent(page, agent).getByText('Wird nicht mehr angezeigt.')).toBeHidden()
  })

  test('Zugang widerrufen: die Rückfrage steht in der Zeile, abbrechen und bestätigen, auch mit Passwortfeld', async ({ page }) => {
    test.setTimeout(150_000)
    const agent = uniqueAgent()

    await signIn(page)
    await createAgent(page, agent)
    await openAccessDialog(page, agent)
    await makeAccess(page, agent)
    await accessContent(page, agent).locator('.wa-stack').getByRole('button', { name: 'Abgelegt', exact: true }).click()

    const content = accessContent(page, agent)
    const row = content.locator('.access-row', { hasText: suggestedName(agent) })

    // Cancel: the question goes, nothing is revoked.
    await row.locator('wa-button[aria-label^="Zugang"]').click()
    await expect(content.locator('.access-question')).toContainText('widerrufen?')
    await expectAtMostOneDialog(page)
    await screenshot(page, '7-revoke-question')
    await content.locator('.access-question').getByRole('button', { name: 'Abbrechen' }).click()
    await expect(content.locator('.access-question')).toBeHidden()
    await expect(row).toBeVisible()

    // Confirm with an old password confirmation: the field is in the row, one dialog all the time.
    expirePasswordConfirmation()
    await row.locator('wa-button[aria-label^="Zugang"]').click()
    await content.locator('.access-question').getByRole('button', { name: 'Widerrufen' }).click()
    const field = content.locator('.access-question .password-confirm')

    await expect(field).toBeVisible()
    await expectAtMostOneDialog(page)
    await field.locator('wa-input input').fill(PASSWORD)
    await field.getByRole('button', { name: 'Bestätigen' }).click()

    await expect(content.locator('.access-row', { hasText: suggestedName(agent) })).toHaveCount(0)
    await expect(content.getByText('Noch kein Zugang.')).toBeVisible()
    await expectAtMostOneDialog(page)
  })

  test('langsame Antwort: Doppelklick macht einen Zugang, Escape während der Erzeugung verliert ihn nicht', async ({ page }) => {
    test.setTimeout(120_000)
    const agent = uniqueAgent()

    await signIn(page)
    await createAgent(page, agent)
    await openAccessDialog(page, agent)

    // The server takes its time to make the access. The GraphQL endpoint of the app is /api (plugins/api.ts).
    let made = 0
    await page.route(url => new URL(url).pathname === '/api', async (route) => {
      if ((route.request().postData() ?? '').includes('CreateAgentToken')) {
        made++
        await new Promise(resolve => setTimeout(resolve, 2500))
      }
      await route.continue()
    })

    const content = accessContent(page, agent)
    const create = content.locator('.access-form').getByRole('button', { name: 'Zugang erzeugen' })
    await create.dblclick()
    await page.waitForTimeout(300)
    await page.keyboard.press('Escape')

    // The dialog stays and shows the token once the answer is there.
    await expect(content.getByText('Wird nicht mehr angezeigt.')).toBeVisible({ timeout: 15_000 })
    await expect(accessDialog(page, agent)).toBeVisible()
    expect(await content.locator('.access-token input').inputValue()).toMatch(/^\d+\|/)
    expect(made).toBe(1)
    await expect(content.locator('.access-name')).toHaveCount(1)

    // While the token is on screen there is no second one that could replace it: first «Abgelegt».
    await expect(create).toHaveAttribute('disabled', '')
  })
})
