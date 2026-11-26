import { mkdirSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { expect, test } from '@playwright/test'
import type { Page } from '@playwright/test'
import { enterCode, expectAtMostOneDialog, freshTotp, login, PASSWORD, resetThrottles, TOTP_SECRET } from './support/helpers'

/**
 * Auftrag 58 in the real interface (rule R1): an agent is made without an address and shows its identifier
 * («Kennung»); in the dialog of its access the role is fixed, choosing a project assigns it at once and «Fertig» loses
 * nothing (H1, rule R2); one «Fertig» per dialog; under Projekt → Mitglieder an agent gets the role Agent, fixed,
 * a person never sees the role Agent.
 */

/** Screenshots for the acceptance (next-work/abnahme/58/), never into the repository. */
const SCREENSHOT_DIR = process.env.SCREENSHOTS_DIR ?? join(tmpdir(), 'mollo-planer-agent-role')

async function screenshot(page: Page, name: string): Promise<void> {
  mkdirSync(SCREENSHOT_DIR, { recursive: true })
  await page.screenshot({ path: join(SCREENSHOT_DIR, `${name}.png`) })
}

async function signIn(page: Page): Promise<void> {
  resetThrottles()
  await login(page, 'admin@e2e.test', PASSWORD)
  await expect(page.getByRole('heading', { name: 'Zweiter Faktor' })).toBeVisible()
  await enterCode(page, await freshTotp(TOTP_SECRET))
  await expect(page).toHaveURL(/\/$/)
}

function slug(name: string): string {
  return name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')
}

async function createAgent(page: Page, name: string): Promise<void> {
  await page.goto('/admin/users')
  await page.getByRole('button', { name: 'Agent anlegen' }).first().click()
  // No field for an address any more: only the name.
  await expect(page.locator('#agent-form wa-input')).toHaveCount(1)
  await page.locator('#agent-form wa-input').locator('input').fill(name)
  await screenshot(page, 'de-agent-anlegen-ohne-email')
  await page.getByRole('button', { name: 'Anlegen', exact: true }).click()
  await expect(page.locator('wa-dialog[open]')).toHaveCount(0)
}

function content(page: Page, agent: string) {
  return page.locator(`wa-dialog[label="Zugang für ${agent}"]`)
}

test.describe('Agenten: Rolle fest, Kennung statt E-Mail (Auftrag 58)', () => {
  test.beforeEach(async ({ page }) => {
    await signIn(page)
  })

  test('Agent anlegen ohne E-Mail, Kennung in der Liste und im Dialog, Projekt zuweisen ohne Verlust bei Fertig', async ({ page }) => {
    const agent = `Robo ${Date.now().toString(36)}`
    const identifier = `${slug(agent)}@agent.invalid`

    await createAgent(page, agent)
    const row = page.locator('.users-row', { hasText: agent })
    await expect(row).toContainText(`Kennung: ${identifier}`)
    await expect(row.locator(`wa-button[aria-label="Kennung von ${agent} kopieren"]`)).toBeVisible()
    await screenshot(page, 'de-liste-kennung')

    await row.locator('[data-agent-access]').click()
    const dialog = content(page, agent)
    await expect(dialog.getByText('Kennung', { exact: true }).first()).toBeVisible()
    await expect(dialog.locator('wa-input').first()).toHaveJSProperty('value', identifier)

    // A token: under it «Abgelegt», and only one «Fertig» in the whole dialog.
    await dialog.locator('.access-form').getByRole('button', { name: 'Zugang erzeugen' }).click()
    await expect(dialog.getByRole('button', { name: 'Abgelegt', exact: true })).toBeVisible()
    await expect(dialog.getByRole('button', { name: 'Fertig', exact: true })).toHaveCount(1)
    await screenshot(page, 'de-zugang-abgelegt')
    await dialog.getByRole('button', { name: 'Abgelegt', exact: true }).click()

    // No role to choose, no «Zuweisen»: choosing the project assigns it.
    await expect(dialog.getByRole('button', { name: 'Zuweisen', exact: true })).toHaveCount(0)
    await expect(dialog.locator('wa-select[label="Projektrolle"]')).toHaveCount(0)
    await dialog.getByLabel('Projekt zuweisen (Rolle Agent)').first().click()
    const first = page.getByRole('option').first()
    const projectName = (await first.textContent())!.trim()
    await first.click()
    await expect(dialog.locator('.access-row', { hasText: projectName })).toContainText('Agent')
    await screenshot(page, 'de-zugang-projekt-zugewiesen')

    // «Fertig» right after: the project stays (H1, nothing lost without a word).
    await dialog.getByRole('button', { name: 'Fertig', exact: true }).click()
    await expect(page.locator('wa-dialog[open]')).toHaveCount(0)
    await page.reload()
    await page.locator('.users-row', { hasText: agent }).locator('[data-agent-access]').click()
    await expect(content(page, agent).locator('.access-row', { hasText: projectName })).toContainText('Agent')
    await expectAtMostOneDialog(page)
  })

  test('Projekt → Mitglieder: Agent mit fester Rolle «Agent», Mensch ohne «Agent» in der Liste', async ({ page }) => {
    const agent = `Robo ${Date.now().toString(36)}`
    await createAgent(page, agent)

    await page.goto('/projects/1/members')
    const add = page.locator('.members-add')
    const role = add.locator('wa-select')

    // A person (every person of the tests is a member already: the row of Mia, and the form before a choice): no «Agent».
    await page.getByLabel('Projektrolle von Mia Member').first().click()
    await expect(page.getByRole('option', { name: 'Mitglied' })).toBeVisible()
    await expect(page.getByRole('option', { name: 'Agent', exact: true })).toHaveCount(0)
    await screenshot(page, 'de-mitglieder-mensch-ohne-agent')
    await page.keyboard.press('Escape')
    await expect(role).not.toHaveAttribute('disabled', /.*/)

    // An agent: role Agent, fixed, with the reason.
    await page.getByLabel('Person').first().click()
    await page.getByRole('option', { name: new RegExp(agent) }).click()
    await expect(role).toHaveJSProperty('value', 'agent')
    await expect(role).toHaveAttribute('disabled', /.*/)
    await expect(role).toHaveJSProperty('hint', 'Agenten haben immer die Rolle Agent.')
    await screenshot(page, 'de-mitglieder-agent-rolle-fest')

    await add.getByRole('button', { name: 'Hinzufügen' }).click()
    const memberRow = page.locator('.members-row', { hasText: agent })
    await expect(memberRow).toContainText('Kennung:')
    await expect(memberRow.locator('wa-select')).toHaveAttribute('disabled', /.*/)
    await screenshot(page, 'de-mitglieder-agent-in-liste')
  })
})
