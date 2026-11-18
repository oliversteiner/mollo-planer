import { expect, test } from '@playwright/test'
import { enterCode, freshTotp, login, PASSWORD, TOTP_SECRET, unexpected, watch } from './support/helpers'

test('the test backend and the seeded admin work: password, code, dashboard', async ({ page }) => {
  const watched = watch(page)

  await login(page, 'admin@e2e.test', PASSWORD)
  await expect(page.getByRole('heading', { name: 'Zweiter Faktor' })).toBeVisible()
  await enterCode(page, await freshTotp(TOTP_SECRET))
  await expect(page).toHaveURL(/\/$/)
  await expect(page.getByRole('heading', { name: /Guten (Morgen|Tag|Abend)/ })).toBeVisible()

  expect(unexpected(watched)).toEqual([])
})
