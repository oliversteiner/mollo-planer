import { mkdirSync, readFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { expect, test } from '@playwright/test'
import type { Page } from '@playwright/test'
import { artisan, enterCode, expectAtMostOneDialog, fillField, freshTotp, login, PASSWORD, resetThrottles, TOTP_SECRET, unexpected, watch } from './support/helpers'

/**
 * Billing (Auftrag 39), from «Billable» to the download, as Oliver does it: the sender in the settings, a customer,
 * the customer in the project, the price in the form of the milestone; then «Billable» → draft → text changed →
 * preview → issue (a question, final) → download of the kept PDF → paid. After every step at most one dialog.
 * The project and its done milestone come from tinker (the throwaway database of these tests); everything else is
 * done in the browser. Files land in the storage folder of the tests (environment.ts, STORAGE), never in backend/.
 */

const SCREENSHOT_DIR = process.env.SCREENSHOTS_DIR ?? join(tmpdir(), 'mollo-planer-billing')

/** The toast with this text (the text is also in the live region for screen readers). */
function toast(page: Page, text: string) {
  return page.getByRole('strong').filter({ hasText: text })
}

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

/** A project of the admin with one done milestone (no price yet); returns the id of the project. */
function projectWithDoneMilestone(name: string, milestone: string): string {
  const out = artisan('tinker', `--execute=${[
    `$admin = App\\Models\\User::where("email", "admin@e2e.test")->first();`,
    `$project = App\\Models\\Project::factory()->withMember($admin, App\\Enums\\ProjectRole::OWNER)->create(["name" => "${name}"]);`,
    `$m = App\\Models\\Milestone::factory()->for($project)->create(["title" => "${milestone}", "description" => ["type" => "doc", "content" => [["type" => "paragraph", "content" => [["type" => "text", "text" => "Konzept und Umsetzung"]]]]]]);`,
    `$m->forceFill(["status" => App\\Enums\\ItemStatus::DONE])->saveQuietly();`,
    `echo "PROJECT:" . $project->id;`
  ].join(' ')}`)

  return out.split('PROJECT:')[1]!.trim()
}

test('from billable milestones to the downloaded invoice', async ({ page }) => {
  test.setTimeout(180_000)
  const watched = watch(page)
  const run = Date.now().toString(36)
  const projectName = `E2E Abrechnung ${run}`
  const company = `Beispiel AG ${run}`
  const projectId = projectWithDoneMilestone(projectName, 'Version 1.0')
  // The tests share one database: the sender starts empty in every run.
  artisan('tinker', '--execute=App\\Models\\BillingSetting::query()->delete();')

  await signIn(page)

  // The menu has the section «Abrechnung» (billing.manage) with its four entries.
  const section = page.locator('wa-details').filter({ hasText: 'Abrechnung' })
  await page.getByRole('button', { name: 'Abrechnung', exact: true }).click()
  await expect(section.getByRole('link', { name: 'Rechnungen' })).toBeVisible()
  await expect(section.getByRole('link', { name: 'Verrechenbar' })).toBeVisible()
  await expect(section.getByRole('link', { name: 'Kunden' })).toBeVisible()
  await expect(section.getByRole('link', { name: 'Einstellungen' })).toBeVisible()
  await page.goto('/billing/settings')
  await expect(page.getByRole('heading', { name: 'Einstellungen der Abrechnung' })).toBeVisible()

  // 1. Sender: what an invoice needs is said, then filled in.
  await expect(page.getByText(/Für eine Rechnung fehlt noch/)).toBeVisible()
  await fillField(page, 'name', 'Mollo Test')
  await fillField(page, 'street', 'Weg')
  await fillField(page, 'houseNumber', '2')
  await fillField(page, 'postalCode', '3000')
  await fillField(page, 'city', 'Bern')
  await fillField(page, 'iban', 'CH93 0076 2011 6238 5295 7')
  await page.locator('form').filter({ has: page.locator('wa-input[name="iban"]') }).getByRole('button', { name: 'Speichern' }).click()
  const password = page.locator('wa-input[type="password"]').first()
  if (await password.isVisible().catch(() => false)) {
    await password.locator('input').fill(PASSWORD)
    await page.getByRole('button', { name: 'Bestätigen' }).first().click()
  }
  await expect(toast(page, 'Absender gespeichert.')).toBeVisible()
  await expect(page.getByText(/Für eine Rechnung fehlt noch/)).toBeHidden()
  await screenshot(page, '1-settings')

  // 2. A customer.
  await page.goto('/billing/customers')
  await page.getByRole('button', { name: 'Kunde anlegen' }).click()
  const dialog = page.locator('wa-dialog[label="Kunde anlegen"]')
  await dialog.locator('wa-input[name="company"] input').fill(company)
  await dialog.locator('wa-input[name="name"] input').fill('Erika Beispiel')
  await dialog.locator('wa-input[name="street"] input').fill('Beispielstrasse')
  await dialog.locator('wa-input[name="houseNumber"] input').fill('12a')
  await dialog.locator('wa-input[name="postalCode"] input').fill('8004')
  await dialog.locator('wa-input[name="city"] input').fill('Zürich')
  await expectAtMostOneDialog(page)
  await screenshot(page, '2-customer-form')
  await dialog.getByRole('button', { name: 'Speichern' }).click()
  await expect(toast(page, `${company} gespeichert.`)).toBeVisible()
  await expect(page.getByRole('link', { name: company })).toBeVisible()

  // 3. The customer in the project settings.
  await page.goto(`/projects/${projectId}/settings`)
  const billingCard = page.locator('wa-card').filter({ has: page.getByRole('heading', { name: 'Abrechnung' }) })
  await billingCard.getByLabel('Kunde').first().click()
  await page.getByRole('option', { name: company }).click()
  await billingCard.getByRole('button', { name: 'Speichern' }).click()
  await expect(toast(page, 'Kunde gespeichert.')).toBeVisible()

  // 4. The price in the form of the milestone.
  await page.goto(`/projects/${projectId}`)
  await page.locator('wa-button[aria-label="Aktionen für Milestone «Version 1.0»"]').click()
  await page.locator('wa-dropdown-item', { hasText: 'Ändern' }).first().click()
  const form = page.locator('wa-dialog').filter({ has: page.locator('wa-input[name="price"]') })
  await form.locator('wa-input[name="price"] input').fill('1’500.50')
  await screenshot(page, '3-milestone-price')
  await form.getByRole('button', { name: 'Speichern' }).click()
  await expect(toast(page, 'Milestone gespeichert.')).toBeVisible()

  // 5. Billable → draft.
  await page.goto('/billing/billable')
  const group = page.locator('wa-card').filter({ hasText: company })
  await expect(group.getByText(projectName)).toBeVisible()
  await expect(group.getByText('1’500.50').first()).toBeVisible()
  await screenshot(page, '4-billable')
  await group.getByRole('button', { name: 'Rechnung erstellen' }).click()
  await expect(page).toHaveURL(/\/billing\/invoices\/\d+$/)
  await expect(page.getByRole('heading', { name: 'Entwurf einer Rechnung' })).toBeVisible()

  // 6. Text, preview, issue.
  const text = page.locator('wa-textarea[name^="description-"] textarea')
  await expect(text).toHaveValue('Konzept und Umsetzung')
  await text.fill('Konzept, Umsetzung und Übergabe')
  await page.getByRole('button', { name: 'Speichern' }).click()
  await expect(toast(page, 'Entwurf gespeichert.')).toBeVisible()
  const preview = page.waitForEvent('download')
  await page.getByRole('button', { name: 'Vorschau (PDF)' }).click()
  expect((await preview).suggestedFilename()).toMatch(/^rechnung-entwurf-\d+\.pdf$/)
  await screenshot(page, '5-draft')

  await page.getByRole('button', { name: 'Ausstellen' }).click()
  await expect(page.getByRole('dialog', { name: 'Rechnung ausstellen?' })).toBeVisible()
  await expectAtMostOneDialog(page)
  await screenshot(page, '6-issue-question')
  await page.locator('wa-dialog[label="Rechnung ausstellen?"]').getByRole('button', { name: 'Endgültig ausstellen' }).click()
  await expect(page.getByRole('heading', { name: /^Rechnung \d{4}-\d{4}$/ })).toBeVisible()
  await expect(page.getByText(/^RF\d{2} \d{4}/)).toBeVisible()

  // 7. Download of the kept PDF.
  const download = page.waitForEvent('download')
  await page.getByRole('button', { name: 'PDF herunterladen' }).click()
  const file = await download
  expect(file.suggestedFilename()).toMatch(/^rechnung-\d{4}-\d{4}\.pdf$/)
  expect(readFileSync(await file.path()).subarray(0, 5).toString()).toBe('%PDF-')
  await screenshot(page, '7-issued')

  // 8. Paid, with a date in this same dialog.
  await page.getByRole('button', { name: 'Als bezahlt markieren' }).click()
  await expectAtMostOneDialog(page)
  await page.locator('wa-dialog[label="Als bezahlt markieren"]').getByRole('button', { name: 'Als bezahlt markieren' }).click()
  await expect(toast(page, 'Als bezahlt markiert.')).toBeVisible()

  await page.goto('/billing/invoices')
  await expect(page.locator('table tr').filter({ hasText: company }).getByText('Bezahlt')).toBeVisible()
  await screenshot(page, '8-invoices')

  expect(unexpected(watched)).toEqual([])

  // This test sends in seconds what people send in minutes; the API counts 120 requests a minute per person. The next
  // test of the same account must not meet that limit (it did: documents.spec got HTTP 429 on saving).
  resetThrottles()
})
