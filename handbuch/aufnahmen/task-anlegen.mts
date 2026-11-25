import type { Recording } from '../../frontend/e2e-manual/recording'

/** Pictures of «Task anlegen und bearbeiten»: the board of a project, the form for a new task. */
const recording: Recording = {
  chapter: 'task-anlegen',
  shots: [
    {
      name: 'board',
      take: async (page, { t }) => {
        await page.goto('/projects')
        await page.getByRole('link', { name: 'E2E Dokumente' }).first().click()
        await page.getByRole('link', { name: t('project.tabs.tasks'), exact: true }).click()
        await page.getByRole('button', { name: t('task.create') }).waitFor()

        return null
      },
    },
    {
      name: 'formular',
      take: async (page, { t, lang }) => {
        await page.getByRole('button', { name: t('task.create') }).click()
        const dialog = page.locator(`wa-dialog[label="${t('task.create')}"]`)
        await dialog.locator('wa-input[name="title"] input').fill(lang === 'de' ? 'Entwurf prüfen' : 'Review the draft')

        return null
      },
    },
  ],
}

export default recording
