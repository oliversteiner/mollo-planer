import { mkdirSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import type { Page } from '@playwright/test'
import { expect, test } from '@playwright/test'
import { artisan, enterCode, freshTotp, login, PASSWORD, resetThrottles, TOTP_SECRET } from './support/helpers'

/**
 * «Nach Version ordnen», the turned display and «Preise anzeigen» on the project page (Auftrag 47). Test data only, in
 * the throwaway database of the browser tests. Screenshots (R1) go to SCREENSHOTS_DIR, never into the repository.
 */
const SHOTS = process.env.SCREENSHOTS_DIR ?? join(tmpdir(), 'mollo-planer-project-order')

/** Milestones stored out of order: 2.0, Backlog (no version), 1.20, 1.3, 2.10. */
function seed(name: string): string {
  const out = artisan('tinker', `--execute=${[
    `$admin = App\\Models\\User::where("email", "admin@e2e.test")->first();`,
    `$member = App\\Models\\User::where("email", "member@e2e.test")->first();`,
    `$p = App\\Models\\Project::factory()->withMember($admin, App\\Enums\\ProjectRole::OWNER)->withMember($member, App\\Enums\\ProjectRole::GUEST)->create(["name" => "${name}"]);`,
    `$make = function ($title, $version, $price, $position) use ($p) { $m = App\\Models\\Milestone::factory()->for($p)->create(["title" => $title, "position" => $position]); $m->forceFill(["release_version" => $version, "release_status" => $version ? App\\Enums\\ReleaseStatus::PLANNED : null, "price_cents" => $price])->saveQuietly(); return $m; };`,
    `$make("Zweiter Wurf", "2.0", 500000, 0);`,
    `$make("Backlog", null, null, 1);`,
    `$make("Feinschliff", "1.20", 120000, 2);`,
    `$make("Grundlagen", "1.3", 80000, 3);`,
    `$make("Ausbau", "2.10", 300000, 4);`,
    `echo "PROJECT:" . $p->id;`
  ].join(' ')}`)

  return out.split('PROJECT:')[1]!.trim()
}

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

/** The titles of the milestones on the page, top to bottom. */
const titles = (page: Page) => page.locator('.milestone-title').allTextContents()

test.describe('order of the milestones', () => {
  test.describe.configure({ mode: 'serial' })
  test.setTimeout(90_000)

  const run = Date.now().toString(36)
  let id = ''

  test.beforeAll(() => {
    id = seed(`Reihenfolge ${run}`)
  })

  test('shows the newest first by default and turns the display; the choice stays after a reload', async ({ page }) => {
    await signIn(page, 'admin@e2e.test')
    await page.goto(`/projects/${id}`)

    // Stored order: Zweiter Wurf, Backlog, Feinschliff, Grundlagen, Ausbau; newest on top turns it round.
    await expect.poll(() => titles(page)).toEqual(['Ausbau', 'Grundlagen', 'Feinschliff', 'Backlog', 'Zweiter Wurf'])
    await shot(page, 'de-neueste-oben')

    await page.getByRole('button', { name: 'Neueste oben' }).click()
    await expect.poll(() => titles(page)).toEqual(['Zweiter Wurf', 'Backlog', 'Feinschliff', 'Grundlagen', 'Ausbau'])

    await page.reload()
    await expect.poll(() => titles(page)).toEqual(['Zweiter Wurf', 'Backlog', 'Feinschliff', 'Grundlagen', 'Ausbau'])
    await page.getByRole('button', { name: 'Älteste oben' }).click()
    await expect.poll(() => titles(page)).toEqual(['Ausbau', 'Grundlagen', 'Feinschliff', 'Backlog', 'Zweiter Wurf'])
  })

  test('«Nach oben» goes up on the screen also when the newest is on top', async ({ page }) => {
    await signIn(page, 'admin@e2e.test')
    await page.goto(`/projects/${id}`)

    await page.locator('wa-button[aria-label="Aktionen für Milestone «Grundlagen»"]').click()
    await expect(page.getByRole('menuitem', { name: 'Nach oben' })).toBeVisible()
    await page.getByRole('menuitem', { name: 'Nach oben' }).click()
    await expect.poll(() => titles(page)).toEqual(['Grundlagen', 'Ausbau', 'Feinschliff', 'Backlog', 'Zweiter Wurf'])

    // Put it back: the same milestone one place down on the screen.
    await page.locator('wa-button[aria-label="Aktionen für Milestone «Grundlagen»"]').click()
    await page.getByRole('menuitem', { name: 'Nach unten' }).click()
    await expect.poll(() => titles(page)).toEqual(['Ausbau', 'Grundlagen', 'Feinschliff', 'Backlog', 'Zweiter Wurf'])
  })

  test('previews the order by version, applies it and takes it back with «Rückgängig»', async ({ page }) => {
    await signIn(page, 'admin@e2e.test')
    await page.goto(`/projects/${id}`)
    // A new browser starts with the newest first; the preview and the undo are easier to read in the stored order.
    await page.getByRole('button', { name: 'Neueste oben' }).click()

    const order = page.getByRole('button', { name: 'Nach Version ordnen' })

    await expect(order).toBeEnabled()
    await shot(page, 'de-taste-frei')
    await order.click()

    // The content is slotted into the wa-dialog (light DOM); the host itself has no box, so look at what it holds.
    const dialog = page.locator('wa-dialog[label="Milestones nach Version ordnen"]')
    const rows = dialog.locator('.order-row')

    await expect(rows.first()).toBeVisible()
    await expect(dialog.getByText('5 Milestones werden verschoben.')).toBeVisible()
    await expect(rows.allTextContents()).resolves.toEqual([
      expect.stringContaining('Grundlagen'),
      expect.stringContaining('Feinschliff'),
      expect.stringContaining('Zweiter Wurf'),
      expect.stringContaining('Ausbau'),
      expect.stringContaining('Backlog')
    ])
    await shot(page, 'de-vorschau')

    await dialog.getByRole('button', { name: 'Neu ordnen' }).click()
    await expect(rows.first()).toBeHidden()
    // The message first: it goes away by itself after a few seconds.
    // The text is also in a live region for screen readers: the toast itself is the strong element.
    await expect(page.getByRole('strong').filter({ hasText: 'Milestones nach Version geordnet' })).toBeVisible()
    await shot(page, 'de-geordnet-mit-rueckgaengig')
    await expect.poll(() => titles(page)).toEqual(['Grundlagen', 'Feinschliff', 'Zweiter Wurf', 'Ausbau', 'Backlog'])

    // Ordered now: the button is off and says why.
    await expect(order).toBeDisabled()

    await page.locator('.toast-action').click()
    await expect.poll(() => titles(page)).toEqual(['Zweiter Wurf', 'Backlog', 'Feinschliff', 'Grundlagen', 'Ausbau'])
    await expect(order).toBeEnabled()
  })

  test('a guest (no right to edit milestones) sees no button', async ({ page }) => {
    await signIn(page, 'member@e2e.test')
    await page.goto(`/projects/${id}`)

    await expect(page.locator('.milestone-title').first()).toBeVisible()
    await expect(page.getByRole('button', { name: 'Nach Version ordnen' })).toHaveCount(0)
  })

  test('«Preise anzeigen» hides every price and the sum, and remembers it', async ({ page }) => {
    await signIn(page, 'admin@e2e.test')
    await page.goto(`/projects/${id}`)

    await expect(page.locator('.milestone-price').first()).toBeVisible()
    await expect(page.locator('.timeline-sum')).toBeVisible()

    await page.locator('#timeline-prices').click()
    await expect(page.locator('.milestone-price')).toHaveCount(0)
    await expect(page.locator('.timeline-sum')).toHaveCount(0)
    await shot(page, 'de-preise-aus')

    await page.reload()
    await expect(page.locator('.milestone-title').first()).toBeVisible()
    await expect(page.locator('.milestone-price')).toHaveCount(0)
    await page.locator('#timeline-prices').click()
    await expect(page.locator('.milestone-price').first()).toBeVisible()
  })

  test('the current milestone is open at every visit, also when it was closed the last time', async ({ page }) => {
    await signIn(page, 'admin@e2e.test')
    await page.goto(`/projects/${id}`)

    const current = page.getByRole('button', { name: 'Milestone «Zweiter Wurf» auf- oder zuklappen' })

    await expect(current).toHaveAttribute('aria-expanded', 'true')
    await current.click()
    await expect(current).toHaveAttribute('aria-expanded', 'false')

    await page.reload()
    await expect(current).toHaveAttribute('aria-expanded', 'true')
  })

  test('narrow window: the toolbar stays inside the screen', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 })
    await signIn(page, 'admin@e2e.test')
    await page.goto(`/projects/${id}`)

    await expect(page.locator('.milestone-title').first()).toBeVisible()
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
    await shot(page, 'de-schmal-390')
  })
})
