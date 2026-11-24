import { expect, test } from '@playwright/test'
import type { Page } from '@playwright/test'
import { artisan, enterCode, freshTotp, login, PASSWORD, resetThrottles, TOTP_SECRET } from './support/helpers'

/**
 * The access dialog of an agent (Auftrag 52): after «Zugang erzeugen» it stays open and shows the token until
 * «Fertig», whether the password was confirmed a moment ago (no prompt) or the prompt appears; a cancelled prompt makes
 * nothing and brings the dialog back. Agents are made here with unique names: the tests share one database.
 */
async function signIn(page: Page): Promise<void> {
  // Three sign-ins of the same account in a row would meet the throttle of the login.
  resetThrottles()
  await login(page, 'admin@e2e.test', PASSWORD)
  await expect(page.getByRole('heading', { name: 'Zweiter Faktor' })).toBeVisible()
  await enterCode(page, await freshTotp(TOTP_SECRET))
  await expect(page).toHaveURL(/\/$/)
}

/** The login confirmed the password; this makes the confirmation old, so the next action asks for it again. */
function expirePasswordConfirmation(): void {
  // Sessions live in the database of the tests, as base64 of JSON (config/session.php: serialization json).
  artisan('tinker', '--execute=foreach (DB::table("sessions")->get() as $s) { $d = json_decode(base64_decode($s->payload), true); if (isset($d["auth"]["password_confirmed_at"])) { $d["auth"]["password_confirmed_at"] = 0; DB::table("sessions")->where("id", $s->id)->update(["payload" => base64_encode(json_encode($d))]); } }')
}

/** The dialog as a whole (visible or not): its own content sits in the shadow root, so it is found by role. */
function accessDialog(page: Page, agent: string) {
  return page.getByRole('dialog', { name: `Zugang für ${agent}` })
}

/** The content of the dialog: slotted into the element, so it is reached through the element, not through the role. */
function accessContent(page: Page, agent: string) {
  return page.locator(`wa-dialog[label="Zugang für ${agent}"]`)
}

function passwordPrompt(page: Page) {
  return page.getByRole('dialog', { name: 'Passwort bestätigen' })
}

function passwordContent(page: Page) {
  return page.locator('wa-dialog[label="Passwort bestätigen"]')
}

async function createAgent(page: Page, name: string, email: string): Promise<void> {
  await page.goto('/admin/users')
  await page.getByRole('button', { name: 'Agent anlegen' }).first().click()
  await page.locator('#agent-form wa-input').nth(0).locator('input').fill(name)
  await page.locator('#agent-form wa-input').nth(1).locator('input').fill(email)
  await page.getByRole('button', { name: 'Anlegen', exact: true }).click()
}

async function makeAccess(page: Page, agent: string, accessName: string): Promise<void> {
  await expect(accessDialog(page, agent)).toBeVisible()
  const content = accessContent(page, agent)
  await content.locator('.access-form wa-input input').fill(accessName)
  await content.getByRole('button', { name: 'Zugang erzeugen' }).click()
}

test.describe('Zugangs-Dialog eines Agenten', () => {
  test.beforeEach(async ({ context }) => {
    await context.grantPermissions(['clipboard-read', 'clipboard-write'])
  })

  test('neuer Agent: Zugang erzeugen zeigt den Token, Kopieren, Fertig, Zugang in der Liste', async ({ page }) => {
    test.setTimeout(120_000)
    const agent = `Agent ${Date.now().toString(36)}`

    await signIn(page)
    await createAgent(page, agent, `${agent.replace(' ', '.').toLowerCase()}@e2e.test`)
    await makeAccess(page, agent, `${agent} Server`)

    const dialog = accessContent(page, agent)
    await expect(dialog.getByText('Wird nicht mehr angezeigt.')).toBeVisible()
    // The dialog stays: give a late wa-hide of its own showing and hiding the time to arrive.
    await page.waitForTimeout(1000)
    await expect(dialog.getByText('Wird nicht mehr angezeigt.')).toBeVisible()

    const token = await dialog.locator('.access-token input').inputValue()
    expect(token).toMatch(/^\d+\|/)
    await dialog.locator('wa-button[aria-label="Zugang kopieren"]').click()
    expect(await page.evaluate(() => navigator.clipboard.readText())).toBe(token)

    await dialog.locator('.wa-stack').getByRole('button', { name: 'Fertig', exact: true }).first().click()
    await expect(dialog.getByText('Wird nicht mehr angezeigt.')).toBeHidden()
    await expect(dialog.locator('.access-name', { hasText: `${agent} Server` })).toBeVisible()

    await page.keyboard.press('Escape')
    await expect(accessDialog(page, agent)).toBeHidden()
    await expect(page.locator('.users-row', { hasText: agent }).getByRole('button', { name: 'Zugang aktiv' })).toBeVisible()
  })

  test('bestehender Agent mit Passwort-Dialog: nach der Bestätigung ist der Token sichtbar', async ({ page }) => {
    test.setTimeout(120_000)
    const agent = `Agent ${Date.now().toString(36)}`

    await signIn(page)
    await createAgent(page, agent, `${agent.replace(' ', '.').toLowerCase()}@e2e.test`)
    await expect(accessDialog(page, agent)).toBeVisible()
    await page.keyboard.press('Escape')
    await expect(accessDialog(page, agent)).toBeHidden()

    expirePasswordConfirmation()
    await page.locator('.users-row', { hasText: agent }).getByRole('button', { name: 'Zugang einrichten' }).click()
    await makeAccess(page, agent, `${agent} Laptop`)

    await expect(passwordPrompt(page)).toBeVisible()
    const prompt = passwordContent(page)
    await prompt.locator('wa-input input').fill(PASSWORD)
    await prompt.getByRole('button', { name: 'Bestätigen' }).click()

    const dialog = accessContent(page, agent)
    await expect(dialog.getByText('Wird nicht mehr angezeigt.')).toBeVisible()
    await page.waitForTimeout(1000)
    await expect(dialog.getByText('Wird nicht mehr angezeigt.')).toBeVisible()
    expect(await dialog.locator('.access-token input').inputValue()).toMatch(/^\d+\|/)

    // The same agent once more, now with a fresh confirmation: no prompt, and the token is shown just the same.
    await dialog.locator('.wa-stack').getByRole('button', { name: 'Fertig', exact: true }).first().click()
    await makeAccess(page, agent, `${agent} Server`)
    await expect(passwordPrompt(page)).toBeHidden()
    await expect(dialog.getByText('Wird nicht mehr angezeigt.')).toBeVisible()
    await page.waitForTimeout(1000)
    await expect(dialog.getByText('Wird nicht mehr angezeigt.')).toBeVisible()
  })

  test('abgebrochene Passwortabfrage: nichts erzeugt, der Dialog ist wieder da', async ({ page }) => {
    test.setTimeout(120_000)
    const agent = `Agent ${Date.now().toString(36)}`

    await signIn(page)
    await createAgent(page, agent, `${agent.replace(' ', '.').toLowerCase()}@e2e.test`)
    await expect(accessDialog(page, agent)).toBeVisible()

    expirePasswordConfirmation()
    await makeAccess(page, agent, `${agent} Abbruch`)

    await expect(passwordPrompt(page)).toBeVisible()
    await passwordContent(page).getByRole('button', { name: 'Abbrechen' }).click()

    await expect(accessDialog(page, agent)).toBeVisible()
    const dialog = accessContent(page, agent)
    await expect(dialog.getByText('Noch kein Zugang.')).toBeVisible()
    await expect(dialog.getByText('Wird nicht mehr angezeigt.')).toBeHidden()
  })
})
