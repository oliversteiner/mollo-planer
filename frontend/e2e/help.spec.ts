import { expect, test } from '@playwright/test'
import { enterCode, freshTotp, login, PASSWORD, TOTP_SECRET, unexpected, watch } from './support/helpers'

/**
 * Help (Auftrag 54 D): the user menu opens the overview, the overview opens a chapter, a prompt can be copied, the switch
 * «Bilder anzeigen» hides and shows the pictures and the browser keeps it. The output for agents (/manual/) is read
 * without signing in.
 */
test('help: overview, chapter, copy a prompt, switch the pictures', async ({ page, context }) => {
  test.setTimeout(120_000)
  const watched = watch(page)
  await context.grantPermissions(['clipboard-read', 'clipboard-write'])

  await login(page, 'admin@e2e.test', PASSWORD)
  await expect(page.getByRole('heading', { name: 'Zweiter Faktor' })).toBeVisible()
  await enterCode(page, await freshTotp(TOTP_SECRET))
  await expect(page).toHaveURL(/\/$/)

  // The user menu leads to the help.
  await page.locator('.user-menu-button').click()
  await page.getByRole('menuitem', { name: 'Hilfe' }).click()
  await expect(page).toHaveURL(/\/help$/)
  await expect(page.getByRole('heading', { name: 'Hilfe', level: 1 })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Meilensteine, ToDos und Tasks' })).toBeVisible()

  // A chapter: steps with pictures in German.
  await page.getByRole('link', { name: 'Task anlegen und bearbeiten' }).click()
  await expect(page).toHaveURL(/\/help\/task-anlegen$/)
  await expect(page.getByRole('heading', { name: 'So geht\'s' })).toBeVisible()
  const pictures = page.locator('img.help-image')
  await expect(pictures).toHaveCount(2)
  await expect(pictures.first()).toHaveAttribute('src', /\/help-images\/task-anlegen\/board\.de\.[0-9a-f]{10}\.webp$/)
  expect(await pictures.first().evaluate((image: HTMLImageElement) => image.complete && image.naturalWidth > 0)).toBe(true)

  // Copy the first prompt.
  await page.locator('[data-copy-prompt]').first().click()
  await expect(page.getByText('Prompt kopiert.')).toBeVisible()
  const copied = await page.evaluate(() => navigator.clipboard.readText())
  await expect(page.locator('.help-prompt-text').first()).toHaveText(copied)

  // The switch hides the pictures, leaves no gap, and is kept after a reload and in other chapters.
  await page.locator('wa-switch').click()
  await expect(pictures).toHaveCount(0)
  await page.reload()
  await expect(page.getByRole('heading', { name: 'So geht\'s' })).toBeVisible()
  await expect(pictures).toHaveCount(0)
  await page.goto('/help/agent-anlegen')
  await expect(page.getByRole('heading', { name: 'Agent anlegen und Zugang einrichten' })).toBeVisible()
  await expect(pictures).toHaveCount(0)
  await page.locator('wa-switch').click()
  await expect(pictures).toHaveCount(2)

  expect(unexpected(watched)).toEqual([])
})

test('the output for agents is readable without signing in, without script', async ({ request }) => {
  for (const path of ['/manual/', '/manual/de/', '/manual/llms.txt', '/manual/de/llms.txt', '/manual/de/task-anlegen.html', '/manual/task-anlegen.md']) {
    const response = await request.get(path)
    expect(response.status(), path).toBe(200)
    expect(await response.text(), path).not.toMatch(/<script\b/i)
  }

  const llms = await (await request.get('/manual/de/llms.txt')).text()
  expect(llms).toContain('(task-anlegen.md)')
})
