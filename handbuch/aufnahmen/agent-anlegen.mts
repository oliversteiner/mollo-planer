import { PASSWORD } from '../../frontend/e2e/support/helpers'
import type { Recording } from '../../frontend/e2e-manual/recording'

/**
 * Pictures of «Agent anlegen und Zugang einrichten»: the new row with «Set up access», the dialog with the new access.
 * The access shown is from the throwaway database of the browser tests and is replaced by a placeholder before the
 * picture, so no token looks real in the manual.
 */
const recording: Recording = {
  chapter: 'agent-anlegen',
  shots: [
    {
      name: 'neue-zeile',
      take: async (page, { t, lang }) => {
        await page.goto('/admin/users')
        // The button is at the top and again in the empty list of agents: the first one.
        await page.getByRole('button', { name: t('admin.users.newAgent.title') }).first().click()
        const dialog = page.locator(`wa-dialog[label="${t('admin.users.newAgent.title')}"]`)
        await dialog.locator('wa-input[name="name"] input').fill('Claude')
        const email = `claude.${lang}.${Date.now().toString(36)}@example.com`
        await dialog.locator('wa-input[name="email"] input').fill(email)
        await dialog.getByRole('button', { name: t('admin.users.newAgent.submit') }).click()
        // The row of the new agent, by its e-mail (the highlight fades after a while).
        await page.locator('.users-row', { hasText: email }).locator('[data-agent-access]').waitFor()
        await page.evaluate(address => sessionStorage.setItem('manual-agent', address), email)

        return null
      },
    },
    {
      name: 'zugang',
      take: async (page, { t }) => {
        const email = await page.evaluate(() => sessionStorage.getItem('manual-agent') ?? '')
        await page.locator('.users-row', { hasText: email }).locator('[data-agent-access]').click()
        const create = page.getByRole('button', { name: t('admin.users.agent.create') })
        await create.click()
        const password = page.locator('wa-input[type="password"] input')

        if (await password.isVisible({ timeout: 1500 }).catch(() => false)) {
          await password.fill(PASSWORD)
          await password.press('Enter')
        }

        // The field with the new access (the copy button next to it is an icon only).
        await page.waitForFunction(() => [...document.querySelectorAll('wa-input[readonly]')].some(input => (input as HTMLElement & { value: string }).value.includes('|')))
        // Placeholder instead of the token of the throwaway database; the field in the middle of the dialog.
        await page.evaluate(() => {
          for (const input of document.querySelectorAll('wa-input[readonly]')) {
            const element = input as HTMLElement & { value: string }

            if (element.value.includes('|')) {
              element.value = '1|•••••••••••••••••••••••••'
              element.scrollIntoView({ block: 'center' })
            }
          }
        })

        return null
      },
    },
  ],
}

export default recording
