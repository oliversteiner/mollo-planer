import { mkdirSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import type { Page } from '@playwright/test'
import { expect, test } from '@playwright/test'
import { enterCode, freshTotp, login, PASSWORD, resetThrottles, TOTP_SECRET } from './support/helpers'

/**
 * The navigation of the help (Auftrag 66 E), Zen on the help (G6), the command palette in Zen (D) and the quiet line for
 * a missing right (M5). Test data only. Screenshots (R1) go to SCREENSHOTS_DIR, never into the repository.
 */
const SHOTS = process.env.SCREENSHOTS_DIR ?? join(tmpdir(), 'mollo-planer-help')

async function signIn(page: Page, email: string): Promise<void> {
  resetThrottles()
  await login(page, email, PASSWORD)
  await expect(page.getByRole('heading', { name: 'Zweiter Faktor' })).toBeVisible()
  await enterCode(page, await freshTotp(TOTP_SECRET))
  await expect(page).toHaveURL(/\/$/)
}

async function shot(page: Page, name: string): Promise<void> {
  mkdirSync(SHOTS, { recursive: true })
  await page.addStyleTag({ content: '#nuxt-devtools-anchor { display: none !important; }' })
  await page.waitForTimeout(400)
  await page.screenshot({ path: join(SHOTS, `${name}.png`) })
}

test.describe('help navigation', () => {
  test.setTimeout(120_000)

  test('previous and next over the areas, the ends, the contents with the focus on the chapter', async ({ page }) => {
    await signIn(page, 'admin@e2e.test')
    await page.goto('/help/task-anlegen')
    await expect(page.getByRole('heading', { name: 'Task anlegen und bearbeiten', level: 1 })).toBeVisible()

    // Wide: the contents are a column next to the text.
    await expect(page.locator('.help-aside .help-toc-link[aria-current="page"]')).toHaveText('Task anlegen und bearbeiten')
    await shot(page, 'de-breit-kapitel')

    await page.locator('#help-next').click()
    await expect(page).toHaveURL(/\/help\/tasks-ueber-mcp$/)
    await page.locator('#help-previous').click()
    await expect(page).toHaveURL(/\/help\/task-anlegen$/)

    // The contents open from the bar; the focus is on the chapter and goes back to «Inhalt» with Escape.
    await page.locator('#help-toc').click()
    await expect(page.locator('wa-drawer .help-toc-link[aria-current="page"]')).toBeFocused()
    await shot(page, 'de-inhalt-offen')
    await page.keyboard.press('Escape')
    await expect(page.locator('#help-toc')).toBeFocused()

    // The last chapter is the appendix; it has no «next».
    await page.goto('/help/anhang-schreibregeln')
    await expect(page.getByRole('heading', { name: 'Schreibregeln', level: 1 })).toBeVisible()
    await expect(page.locator('#help-next')).toHaveCount(0)
    await expect(page.locator('#help-previous')).toBeVisible()
    await shot(page, 'de-letztes-kapitel')

    // The first chapter has no «previous».
    await page.goto('/help/agent-anlegen')
    await page.locator('#help-previous').click()
    await page.goto('/help')
    await expect(page.getByRole('heading', { name: 'Über dieses Handbuch' })).toBeVisible()
  })

  test('Zen stays on the help, the bar stays, the column goes; the palette has a row of its own', async ({ page }) => {
    await signIn(page, 'admin@e2e.test')
    await page.goto('/help/task-anlegen')
    await expect(page.getByRole('heading', { name: 'Task anlegen und bearbeiten', level: 1 })).toBeVisible()

    await page.getByRole('button', { name: 'Zen', exact: true }).click()
    await expect(page).toHaveURL(/\/help\/task-anlegen$/)
    await expect(page.locator('.help-nav')).toBeVisible()
    await expect(page.locator('#help-toc')).toBeVisible()
    await expect(page.locator('.help-aside')).toBeHidden()
    await shot(page, 'de-zen-kapitel')

    await page.keyboard.press('Control+k')
    const row = page.locator('.command-zen-row .command-item')
    await expect(row).toHaveText(['Zen-Ansicht verlassen', 'Stimmung'])
    await shot(page, 'de-zen-palette')
    await page.keyboard.press('Escape')

    await page.locator('#help-toc').click()
    await expect(page.locator('wa-drawer .help-toc-link[aria-current="page"]')).toBeVisible()
    await shot(page, 'de-zen-inhalt')
  })

  test('narrow window: direction and shortened title, the whole title in the label', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 })
    await signIn(page, 'admin@e2e.test')
    await page.goto('/help/tasks-ueber-mcp')
    await expect(page.locator('.help-nav')).toBeVisible()
    await expect(page.locator('.help-aside')).toBeHidden()
    await expect(page.locator('#help-next')).toHaveAttribute('aria-label', /^Nächstes Kapitel: /)
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
    await shot(page, 'de-schmal-390')
  })

  test('a missing right is a quiet line with the next step', async ({ page }) => {
    await signIn(page, 'member@e2e.test')
    await page.goto('/help/agent-anlegen')
    await expect(page.locator('.help-right')).toContainText('Frag eine Person, die Benutzer verwalten darf.')
    await shot(page, 'de-recht-fehlt')
  })
})
