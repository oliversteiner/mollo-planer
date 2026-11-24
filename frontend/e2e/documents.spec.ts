import { expect, test } from '@playwright/test'
import type { Page } from '@playwright/test'
import { enterCode, expectAtMostOneDialog, freshTotp, login, PASSWORD, TOTP_SECRET, unexpected, watch } from './support/helpers'

/**
 * Documents (task 17, docs/dokumentenverwaltung.md): create, edit with a table and a code block, save, reload (the
 * content is the same), a second tab causes a conflict, share with a member, export as PDF, compare versions and
 * bring one back. The seeded project "E2E Dokumente" has the admin as owner and the member in it.
 */
async function signIn(page: Page): Promise<void> {
  await login(page, 'admin@e2e.test', PASSWORD)
  await expect(page.getByRole('heading', { name: 'Zweiter Faktor' })).toBeVisible()
  await enterCode(page, await freshTotp(TOTP_SECRET))
  await expect(page).toHaveURL(/\/$/)
}

test('a document: create, edit with table and code block, save, reload, conflict, share, PDF, history', async ({ page, context, browser }) => {
  test.setTimeout(240_000)
  const watched = watch(page)

  await signIn(page)

  // The project of the seed has the id 1.
  await page.goto('/projects/1/documents')
  await expect(page.getByText('Noch keine Dokumente')).toBeVisible()

  // Create.
  await page.getByRole('button', { name: 'Dokument anlegen' }).first().click()
  await expectAtMostOneDialog(page)
  await page.getByLabel('Titel').fill('E2E Aufbau')
  await page.getByRole('button', { name: 'Anlegen', exact: true }).click()
  await expect(page).toHaveURL(/\/projects\/1\/documents\/\d+/, { timeout: 30_000 })
  const documentUrl = page.url().split('?')[0]!

  // Edit: a paragraph, a table, a code block.
  const editor = page.locator('.ProseMirror').first()
  await expect(editor).toBeVisible()
  await editor.click()
  await page.keyboard.type('Erster Absatz')
  await page.keyboard.press('Enter')
  await page.getByRole('button', { name: 'Codeblock' }).click()
  await page.keyboard.type('echo 1;')
  // Leave the code block (a table cell holds one paragraph only, so the table goes below it).
  await page.keyboard.press('Control+Enter')
  await page.getByRole('button', { name: 'Tabelle', exact: true }).click()
  await page.getByRole('menuitem', { name: 'Tabelle einfügen' }).click()
  await page.getByRole('button', { name: 'Sichern', exact: true }).click()
  await expect(page.getByRole('button', { name: 'Sichern', exact: true })).toBeDisabled()

  // Reload: the content is the same.
  await page.goto(documentUrl)
  await expect(page.locator('.ProseMirror table')).toBeVisible()
  await expect(page.locator('.ProseMirror th')).toHaveCount(3)
  await expect(page.locator('.ProseMirror pre')).toContainText('echo 1;')
  await expect(page.locator('.ProseMirror')).toContainText('Erster Absatz')

  // Conflict: a second tab saves a newer version first.
  await page.getByRole('button', { name: 'Bearbeiten', exact: true }).click()
  await page.locator('.ProseMirror').first().click()
  await page.keyboard.press('Control+End')
  await page.keyboard.type(' (Tab eins)')

  // The second tab is another browser window of the same person: same session, but its own storage (the draft of
  // the first tab is not in it).
  const otherWindow = await browser.newContext({ storageState: { cookies: (await context.storageState()).cookies, origins: [] } })
  const second = await otherWindow.newPage()
  await second.goto(`${documentUrl}?edit=1`)
  await second.locator('.ProseMirror').first().click()
  await second.keyboard.press('Control+End')
  await second.keyboard.type(' (Tab zwei)')
  await second.getByRole('button', { name: 'Sichern', exact: true }).click()
  await expect(second.getByRole('button', { name: 'Sichern', exact: true })).toBeDisabled()
  await otherWindow.close()

  await page.getByRole('button', { name: 'Sichern', exact: true }).click()
  await expect(page.getByText('Jemand hat eine neuere Fassung gesichert')).toBeVisible()
  await page.getByRole('button', { name: 'Neu laden' }).click()
  await expect(page.locator('.ProseMirror')).toContainText('(Tab zwei)')
  // The own text is still there as a draft: it is offered again, and discarded here.
  await expect(page.getByText('Du hast am')).toBeVisible()
  await page.getByRole('button', { name: 'Abbrechen' }).last().click()

  // Share with the member (only members of the project can be chosen).
  await page.getByRole('button', { name: 'Bearbeiten beenden' }).click()
  await page.locator('wa-button[aria-label="Aktionen"]').click()
  await page.locator('wa-dropdown-item', { hasText: 'Freigeben' }).click()
  await expectAtMostOneDialog(page)
  await page.getByLabel('Person hinzufügen').first().click()
  await page.getByRole('option', { name: /Mia Member/ }).click()
  await page.getByRole('button', { name: 'Person hinzufügen' }).click()
  await page.getByRole('button', { name: 'Speichern' }).click()
  await expect(page.locator('wa-dialog[open]')).toHaveCount(0)

  // PDF export downloads a file.
  const download = page.waitForEvent('download')
  await page.getByRole('button', { name: 'Exportieren' }).click()
  await page.locator('wa-dropdown-item', { hasText: 'Als PDF' }).click()
  expect((await download).suggestedFilename()).toMatch(/\.pdf$/)

  // History: compare two versions, bring the first one back.
  await page.locator('wa-button[aria-label="Aktionen"]').click()
  const historyEntry = page.locator('wa-dropdown-item', { hasText: 'Verlauf' })
  await expect(historyEntry).toBeVisible()
  await historyEntry.click()
  await expect(page).toHaveURL(/\/documents\/\d+\/history/)
  await expect(page.getByRole('heading', { name: 'Verlauf' })).toBeVisible()
  await page.locator('wa-checkbox', { hasText: 'Fassung 1' }).click()
  await page.locator('wa-checkbox', { hasText: 'Fassung 2' }).click()
  await expect(page.locator('.document-diff')).toContainText('+')
  await page.getByRole('button', { name: 'Zurückholen' }).last().click()
  await page.getByRole('button', { name: 'Zurückholen' }).last().click()
  await expect(page).toHaveURL(documentUrl)
  // The first version was empty, so the document is empty again (and has a new version).
  await expect(page.getByText('Das Dokument hat noch keinen Inhalt.')).toBeVisible()

  expect(unexpected(watched)).toEqual([])
})
