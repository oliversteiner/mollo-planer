import { mkdirSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { expect, test } from '@playwright/test'
import type { Page } from '@playwright/test'
import { artisan, enterCode, expectAtMostOneDialog, expirePasswordConfirmation, freshTotp, login, PASSWORD, resetThrottles, TOTP_SECRET } from './support/helpers'

/**
 * Rule R11 (Auftrag 55): no dialog on top of another one, anywhere. The places besides the agents (agent-access.spec.ts)
 * and the icon picker (icon-picker.spec.ts): the login service (password in the dialog, the question of revoking),
 * the labels of a project (the question is a step of the dialog), the import of a planer file (the same) and a new
 * project from a file (the file takes the place of the form). After every step at most one dialog is open.
 */

const SCREENSHOT_DIR = process.env.SCREENSHOTS_DIR ?? join(tmpdir(), 'mollo-planer-no-dialog-over-dialog')

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

/** The content of a dialog by its title (slotted into the element: reached through the element, not through the role). */
function dialogOf(page: Page, label: string) {
  return page.locator(`wa-dialog[label="${label}"]`)
}

/** The dialog is open and drawn: asked by role, because the element itself has no box of its own. */
async function expectDialogShown(page: Page, label: string): Promise<void> {
  await expect(page.getByRole('dialog', { name: label })).toBeVisible()
}

/** A project of its own for a test (the admin owns it), made in the database of the tests; returns its id. */
function makeProject(name: string): string {
  const out = artisan('tinker', `--execute=$p = App\\Models\\Project::factory()->withMember(App\\Models\\User::where("email", "admin@e2e.test")->first(), App\\Enums\\ProjectRole::OWNER)->create(["name" => "${name}"]); echo "ID=" . $p->id;`)

  return /ID=(\d+)/.exec(out)![1]!
}

test.describe('Anmeldedienst: Anwendung anlegen und widerrufen', () => {
  test('das Passwort steht als Feld im Dialog, danach das Geheimnis; widerrufen fragt im selben Dialog nach dem Passwort', async ({ page }) => {
    test.setTimeout(180_000)
    const name = `Club ${Date.now().toString(36)}`

    await signIn(page)
    await page.goto('/admin/oauth-clients')
    await page.getByRole('button', { name: 'Anwendung anlegen' }).first().click()
    await expectAtMostOneDialog(page)

    const form = dialogOf(page, 'Anwendung anlegen')
    await form.locator('#oauth-client-form wa-input').first().locator('input').fill(name)
    await form.locator('#oauth-client-form wa-textarea textarea').fill('https://app.example/callback')
    expirePasswordConfirmation()
    await form.getByRole('button', { name: 'Anlegen', exact: true }).click()

    const field = form.locator('.password-confirm')
    await expect(field).toBeVisible()
    await expectAtMostOneDialog(page)
    await screenshot(page, 'oauth-1-password-field')
    await field.locator('wa-input input').fill(PASSWORD)
    await field.getByRole('button', { name: 'Bestätigen' }).click()

    // The secret is shown once, in its own dialog; the form is gone by then: one dialog.
    const secret = dialogOf(page, `Geheimnis für ${name}`)
    await expect(secret.getByText('Wird nicht mehr angezeigt.')).toBeVisible()
    await expectAtMostOneDialog(page)
    await secret.getByRole('button', { name: 'Fertig' }).click()
    await expect(page.locator('wa-dialog[open]')).toHaveCount(0)

    // Revoke: the question is one dialog, the password is a field of it.
    const row = page.locator('.clients-row', { hasText: name })
    await row.locator(`wa-button[aria-label="Aktionen für ${name}"]`).click()
    await row.locator('wa-dropdown-item', { hasText: 'Widerrufen' }).click()
    const question = dialogOf(page, `${name} widerrufen?`)
    await expectDialogShown(page, `${name} widerrufen?`)
    await expectAtMostOneDialog(page)

    // Cancel first: nothing is revoked.
    await question.getByRole('button', { name: 'Abbrechen' }).click()
    await expect(page.locator('wa-dialog[open]')).toHaveCount(0)
    await expect(row).not.toContainText('widerrufen')

    expirePasswordConfirmation()
    await row.locator(`wa-button[aria-label="Aktionen für ${name}"]`).click()
    await row.locator('wa-dropdown-item', { hasText: 'Widerrufen' }).click()
    await question.getByRole('button', { name: 'Ja, widerrufen' }).click()
    const revokeField = question.locator('.password-confirm')

    await expect(revokeField).toBeVisible()
    await expectAtMostOneDialog(page)
    await screenshot(page, 'oauth-2-revoke-password-field')
    await revokeField.locator('wa-input input').fill(PASSWORD)
    await revokeField.getByRole('button', { name: 'Bestätigen' }).click()

    await expect(row.locator('wa-dropdown-item, wa-button[aria-label^="Aktionen"]')).toHaveCount(0)
    await expect(page.locator('wa-dialog[open]')).toHaveCount(0)
  })
})

test.describe('Labels eines Projekts: die Rückfrage ist ein Schritt des Dialogs', () => {
  test('Label entfernen: Speichern zeigt die Rückfrage im selben Dialog, «Zurück» geht zurück, «Speichern und löschen» speichert', async ({ page }) => {
    test.setTimeout(180_000)
    const id = makeProject(`E2E Labels ${Date.now().toString(36)}`)
    artisan('tinker', '--execute=foreach (["eins", "zwei"] as $key) { App\\Models\\Label::factory()->create(["project_id" => ' + id + ', "key" => $key, "names" => ["de" => "Label " . $key, "en" => "Label " . $key]]); }')

    await signIn(page)
    await page.goto(`/projects/${id}/settings`)
    await page.getByRole('button', { name: 'Labels bearbeiten' }).first().click()

    const dialog = dialogOf(page, 'Labels des Projekts')
    await expectDialogShown(page, 'Labels des Projekts')
    await dialog.locator('wa-button[aria-label="«Label zwei» entfernen"]').click()
    await dialog.getByRole('button', { name: 'Speichern', exact: true }).click()

    // The question is a step of this dialog.
    await expect(dialog.getByText('Labels löschen?')).toBeVisible()
    await expectAtMostOneDialog(page)
    await screenshot(page, 'labels-1-question-step')
    await dialog.getByRole('button', { name: 'Zurück' }).click()
    await expect(dialog.getByText('Labels löschen?')).toBeHidden()
    await expect(dialog.locator('wa-button[aria-label="«Label eins» entfernen"]')).toBeVisible()

    await dialog.getByRole('button', { name: 'Speichern', exact: true }).click()
    await expect(dialog.getByText('Labels löschen?')).toBeVisible()
    await dialog.getByRole('button', { name: 'Speichern und löschen' }).click()

    await expect(page.locator('wa-dialog[open]')).toHaveCount(0)
    await expect(page.getByText('Labels gespeichert.').first()).toBeVisible()
  })
})

test.describe('Planer-Datei eintragen: die Rückfrage ist ein Schritt des Dialogs', () => {
  test('eine Datei, die ein Element weglässt: Rückfrage im selben Dialog, Zurück, bestätigen', async ({ page }) => {
    test.setTimeout(180_000)
    const id = makeProject(`E2E Import ${Date.now().toString(36)}`)
    artisan('tinker', '--execute=foreach (["Eins", "Zwei"] as $i => $title) { App\\Models\\Milestone::factory()->create(["project_id" => ' + id + ', "title" => "Etappe " . $title, "position" => $i]); }')
    const out = artisan('tinker', `--execute=echo "DOC=" . json_encode(app(App\\Planer\\Export::class)->document(App\\Models\\Project::find(${id})));`)
    const document = JSON.parse(out.split('DOC=')[1]!.trim()) as { milestones: unknown[] }

    // The file leaves out the second milestone: in mode «Ganzer Plan» that is a deletion, which is asked first.
    document.milestones = document.milestones.slice(0, 1)
    const file = join(tmpdir(), `e2e-planer-${id}.json`)
    writeFileSync(file, JSON.stringify(document))

    await signIn(page)
    await page.goto(`/projects/${id}/settings`)
    await page.getByRole('button', { name: 'Datei eintragen …' }).click()

    const dialog = dialogOf(page, 'Planer-Datei eintragen')
    await expectDialogShown(page, 'Planer-Datei eintragen')
    await dialog.locator('input[type="file"]').setInputFiles(file)
    await expect(dialog.getByText(/Änderung/).first()).toBeVisible()

    await dialog.getByRole('button', { name: 'Eintragen', exact: true }).click()
    await expect(dialog.getByText('Eintragen und löschen?')).toBeVisible()
    await expectAtMostOneDialog(page)
    await screenshot(page, 'import-1-question-step')

    await dialog.getByRole('button', { name: 'Zurück' }).click()
    await expect(dialog.getByText('Eintragen und löschen?')).toBeHidden()
    await expect(dialog.locator('input[type="file"]')).toHaveCount(1)

    await dialog.getByRole('button', { name: 'Eintragen', exact: true }).click()
    await expect(dialog.getByText('Eintragen und löschen?')).toBeVisible()
    await dialog.getByRole('button', { name: 'Eintragen', exact: true }).click()

    await expect(page.locator('wa-dialog[open]')).toHaveCount(0)
    await expect(page.getByText('Die Datei ist eingetragen.').first()).toBeVisible()
  })
})

test.describe('Neues Projekt aus einer Datei', () => {
  test('«Aus Datei» ersetzt das Formular durch den Import: nie beide Dialoge zugleich', async ({ page }) => {
    test.setTimeout(120_000)

    await signIn(page)
    await page.goto('/projects')
    await page.getByRole('button', { name: 'Projekt anlegen' }).first().click()
    await expectDialogShown(page, 'Neues Projekt')
    await expectAtMostOneDialog(page)

    await page.getByRole('button', { name: 'Aus Datei' }).click()

    await expectDialogShown(page, 'Projekt aus Datei')
    await expectAtMostOneDialog(page)
    await expect(page.locator('wa-dialog[open]')).toHaveCount(1)
    await screenshot(page, 'create-from-file')
  })
})
