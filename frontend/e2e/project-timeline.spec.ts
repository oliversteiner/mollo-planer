import { mkdirSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import type { Page } from '@playwright/test'
import { expect, test } from '@playwright/test'
import { artisan, enterCode, freshTotp, login, PASSWORD, resetThrottles, TOTP_SECRET } from './support/helpers'

/**
 * The project page as an outline (Auftrag 62, docs/ux-projektseite.md): milestones open and close like todos, the
 * current one is open at the first visit, the choice is remembered, prices show with billing.manage only. Test data
 * only, in the throwaway database of the browser tests. Screenshots (R1) go to SCREENSHOTS_DIR, never into the repository.
 */
const SHOTS = process.env.SCREENSHOTS_DIR ?? join(tmpdir(), 'mollo-planer-project-timeline')

/** A project with six milestones: two billed (one issued, one draft), a current one with a price, a done one without, one with ideas only. */
function seed(name: string): string {
  const out = artisan('tinker', `--execute=${[
    `$admin = App\\Models\\User::where("email", "admin@e2e.test")->first();`,
    `$member = App\\Models\\User::where("email", "member@e2e.test")->first();`,
    `$p = App\\Models\\Project::factory()->withMember($admin, App\\Enums\\ProjectRole::OWNER)->withMember($member, App\\Enums\\ProjectRole::MEMBER)->create(["name" => "${name}"]);`,
    `$c = App\\Models\\Customer::factory()->create();`,
    `$make = function ($title, $status, $price, $position) use ($p) { $m = App\\Models\\Milestone::factory()->for($p)->create(["title" => $title, "position" => $position]); $m->forceFill(["status" => $status, "price_cents" => $price])->saveQuietly(); return $m; };`,
    `$m1 = $make("Konzept und Design", App\\Enums\\ItemStatus::DONE, 480000, 0);`,
    `$m2 = $make("Technische Umsetzung", App\\Enums\\ItemStatus::DONE, 620000, 1);`,
    `$m3 = $make("Inhalte übertragen", App\\Enums\\ItemStatus::OPEN, 780000, 2);`,
    `$m4 = $make("Schulung", App\\Enums\\ItemStatus::DONE, null, 3);`,
    `$m5 = $make("Ideensammlung", App\\Enums\\ItemStatus::OPEN, null, 4);`,
    `foreach ([$m1, $m2, $m3] as $i => $m) { $t = App\\Models\\Todo::factory()->for($m)->create(["title" => "Arbeitspaket " . ($i + 1)]); App\\Models\\Task::factory()->for($t)->create(["title" => "Aufgabe " . ($i + 1)]); }`,
    `$t5 = App\\Models\\Todo::factory()->for($m5)->create(["title" => "Vorschläge"]); foreach (["Idee A", "Idee B"] as $idea) { $x = App\\Models\\Task::factory()->for($t5)->create(["title" => $idea]); $x->forceFill(["status" => App\\Enums\\TaskStatus::IDEA])->saveQuietly(); }`,
    // The status of a milestone follows its tasks (observer): set the done ones last.
    `foreach ([$m1, $m2, $m4] as $m) { $m->refresh()->forceFill(["status" => App\\Enums\\ItemStatus::DONE])->saveQuietly(); }`,
    `$inv = fn ($status, $number, $cents) => DB::table("invoices")->insertGetId(["customer_id" => $c->id, "number" => $number, "status" => $status, "total_cents" => $cents, "currency" => "CHF", "locale" => "de", "created_at" => now(), "updated_at" => now()]);`,
    `$item = fn ($invoice, $m, $cents) => DB::table("invoice_items")->insert(["invoice_id" => $invoice, "project_id" => $m->project_id, "project_name" => "${name}", "milestone_id" => $m->id, "billing_milestone_id" => $m->id, "position" => 0, "title" => $m->title, "amount_cents" => $cents, "created_at" => now(), "updated_at" => now()]);`,
    `$item($inv("issued", "2026-" . random_int(1000, 9999), 480000), $m1, 480000);`,
    `$item($inv("draft", null, 620000), $m2, 620000);`,
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
  // The bar of the Nuxt devtools is not part of the page.
  await page.addStyleTag({ content: '#nuxt-devtools-anchor { display: none !important; }' })
  await page.waitForTimeout(400)
  await page.screenshot({ path: join(SHOTS, `${name}.png`) })
}

const arrow = (page: Page, title: string) => page.getByRole('button', { name: `Milestone «${title}» auf- oder zuklappen` })

test.describe('project page as an outline', () => {
  test.describe.configure({ mode: 'serial' })
  test.setTimeout(240_000)

  const run = Date.now().toString(36)
  let id = ''

  test.beforeAll(() => {
    id = seed(`Kundenportal ${run}`)
  })

  test('first visit: all closed except the current one; prices and sums for the admin', async ({ page }) => {
    await signIn(page, 'admin@e2e.test')
    await page.goto(`/projects/${id}`)

    await expect(arrow(page, 'Inhalte übertragen')).toHaveAttribute('aria-expanded', 'true')
    for (const title of ['Konzept und Design', 'Technische Umsetzung', 'Schulung', 'Ideensammlung']) {
      await expect(arrow(page, title)).toHaveAttribute('aria-expanded', 'false')
    }

    const heads = page.locator('.milestone-head')

    await expect(heads.filter({ hasText: 'Konzept und Design' })).toContainText(/CHF 4’800\.00\s*·\s*Verrechnet 2026-/)
    await expect(heads.filter({ hasText: 'Technische Umsetzung' })).toContainText('CHF 6’200.00 · Rechnung im Entwurf')
    await expect(heads.filter({ hasText: 'Inhalte übertragen' })).toContainText('CHF 7’800.00')
    await expect(heads.filter({ hasText: 'Schulung' })).toContainText('Ohne Preis')
    await expect(page.locator('.timeline-sum')).toHaveText('Preise: CHF 18’800.00 · verrechnet 4’800.00 · im Entwurf 6’200.00')
    await expect(page.getByText('2 Ideen')).toBeVisible()

    await shot(page, '1-admin-alle-zu-aktueller-offen')
  })

  test('opens one milestone, remembers it after a reload, opens all and closes all', async ({ page }) => {
    await signIn(page, 'admin@e2e.test')
    await page.goto(`/projects/${id}`)

    await arrow(page, 'Konzept und Design').click()
    await expect(arrow(page, 'Konzept und Design')).toHaveAttribute('aria-expanded', 'true')
    await shot(page, '2-ein-milestone-auf')

    await page.reload()
    await expect(arrow(page, 'Konzept und Design')).toHaveAttribute('aria-expanded', 'true')

    await page.getByRole('button', { name: 'Alle aufklappen' }).click()
    await expect(page.getByRole('button', { name: 'Alle aufklappen' })).toBeDisabled()
    for (const title of ['Technische Umsetzung', 'Schulung', 'Ideensammlung']) {
      await expect(arrow(page, title)).toHaveAttribute('aria-expanded', 'true')
    }

    await page.getByRole('button', { name: 'Alle zuklappen' }).click()
    await expect(page.getByRole('button', { name: 'Alle zuklappen' })).toBeDisabled()
    await expect(arrow(page, 'Inhalte übertragen')).toHaveAttribute('aria-expanded', 'false')
  })

  test('keyboard: Tab to the arrow, Enter and Space toggle, the arrow keys open and close', async ({ page }) => {
    await signIn(page, 'admin@e2e.test')
    await page.goto(`/projects/${id}`)

    const toggle = arrow(page, 'Schulung')

    await toggle.focus()
    await expect(toggle).toBeFocused()
    await page.keyboard.press('Enter')
    await expect(toggle).toHaveAttribute('aria-expanded', 'true')
    await page.keyboard.press('Space')
    await expect(toggle).toHaveAttribute('aria-expanded', 'false')
    await page.keyboard.press('ArrowRight')
    await expect(toggle).toHaveAttribute('aria-expanded', 'true')
    await page.keyboard.press('ArrowLeft')
    await expect(toggle).toHaveAttribute('aria-expanded', 'false')
    // The focus stays on the arrow of that milestone, nothing jumps.
    await expect(toggle).toBeFocused()
  })

  test('the old link ?milestones=only closes everything and loses the parameter', async ({ page }) => {
    await signIn(page, 'admin@e2e.test')
    await page.goto(`/projects/${id}?milestones=only`)

    await expect(arrow(page, 'Inhalte übertragen')).toHaveAttribute('aria-expanded', 'false')
    await expect(page).not.toHaveURL(/milestones=only/)
  })

  test('a member without billing.manage sees no price, no sum and no hint', async ({ page }) => {
    await signIn(page, 'member@e2e.test')
    await page.goto(`/projects/${id}`)

    await expect(arrow(page, 'Inhalte übertragen')).toBeVisible()
    await expect(page.locator('.milestone-price')).toHaveCount(0)
    await expect(page.locator('.timeline-sum')).toHaveCount(0)
    await expect(page.getByText('Ohne Preis')).toHaveCount(0)

    await shot(page, '3-mitglied-ohne-abrechnung')
  })

  test('a failed load shows the error with a retry, not an empty project', async ({ page }) => {
    await signIn(page, 'admin@e2e.test')
    await page.route(url => url.pathname === '/api', async (route) => {
      const body = route.request().postData() ?? ''

      if (body.includes('ProjectTimeline')) {
        await route.abort()
        return
      }

      await route.continue()
    })
    await page.goto(`/projects/${id}`)

    await expect(page.getByText('Die Milestones konnten nicht geladen werden.')).toBeVisible()
    await expect(page.getByText('Noch keine Milestones')).toHaveCount(0)
    await expect(page.getByRole('button', { name: 'Erneut versuchen' })).toBeVisible()

    await shot(page, '4-fehlerzustand')
  })

  test('narrow window: two rows in the head, the toolbar buttons are icons', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 })
    await signIn(page, 'admin@e2e.test')
    await page.goto(`/projects/${id}`)

    await expect(arrow(page, 'Inhalte übertragen')).toBeVisible()
    await expect(page.locator('.timeline-toolbar-label').first()).toBeHidden()
    const noOverflow = await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)

    expect(noOverflow).toBe(true)

    await shot(page, '5-schmales-fenster')
  })
})
