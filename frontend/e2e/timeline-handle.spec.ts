import { expect, test } from '@playwright/test'
import { enterCode, freshTotp, login, PASSWORD, TOTP_SECRET } from './support/helpers'

/**
 * Timeline (docs/verwaltung-agenten-anfasser.md §3.5): a task moves to another todo only by its handle. Dragging the
 * row does nothing. The seeded project "E2E Zeitachse" (id 2) has the todos "E2E Quelle" (with the task) and "E2E Ziel".
 */
test('a task moves only by its handle, not by its row', async ({ page }) => {
  test.setTimeout(120_000)

  await login(page, 'admin@e2e.test', PASSWORD)
  await expect(page.getByRole('heading', { name: 'Zweiter Faktor' })).toBeVisible()
  await enterCode(page, await freshTotp(TOTP_SECRET))
  await expect(page).toHaveURL(/\/$/)

  await page.goto('/projects/2')
  for (const name of ['E2E Quelle', 'E2E Ziel']) {
    await page.getByRole('button', { name: new RegExp(name) }).first().click()
  }

  const source = page.locator('[data-todo-id]').filter({ hasText: 'E2E Beweglich' })
  const target = page.locator('[data-todo-id]').filter({ hasNotText: 'E2E Beweglich' }).first()
  const targetId = await target.getAttribute('data-todo-id')
  const row = source.locator('li', { hasText: 'E2E Beweglich' })

  await expect(row).toBeVisible()

  // The row itself: no drag.
  await row.getByText('E2E Beweglich').hover()
  await page.mouse.down()
  await target.hover()
  await page.mouse.up()
  await expect(source.getByText('E2E Beweglich')).toBeVisible()

  // The handle: the task lands in the other todo.
  const handle = await row.getByRole('img', { name: 'Task verschieben' }).boundingBox()
  const goal = await target.boundingBox()

  await page.mouse.move(handle!.x + handle!.width / 2, handle!.y + handle!.height / 2)
  await page.mouse.down()
  await page.mouse.move(handle!.x + 10, handle!.y + 10, { steps: 5 })
  await page.mouse.move(goal!.x + goal!.width / 2, goal!.y + goal!.height / 2, { steps: 20 })
  await page.mouse.up()
  await expect(page.locator('[data-todo-id]').filter({ hasText: 'E2E Beweglich' }).first()).toHaveAttribute('data-todo-id', targetId ?? '')
})
