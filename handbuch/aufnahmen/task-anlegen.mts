import { relabel } from '../../frontend/e2e-manual/recording'
import type { Recording } from '../../frontend/e2e-manual/recording'

/**
 * Pictures of «Task anlegen und bearbeiten»: the board of a project (the columns), the form for a new task (the
 * dialog). The names of the throwaway database are replaced by neutral ones before the picture (relabel).
 */
const recording: Recording = {
  chapter: 'task-anlegen',
  shots: [
    {
      name: 'board',
      take: async (page, { t, lang }) => {
        await page.goto('/projects')
        await page.getByRole('link', { name: 'E2E Dokumente' }).first().click()
        await page.getByRole('link', { name: t('project.tabs.tasks'), exact: true }).click()
        await page.getByRole('button', { name: t('task.create') }).waitFor()
        await relabel(page, { 'E2E Dokumente': lang === 'de' ? 'Website-Relaunch' : 'Website relaunch', 'Symbol wählen': lang === 'de' ? 'Startseite entwerfen' : 'Design the home page', 'Auswahl testen': lang === 'de' ? 'Startseite' : 'Home page' })

        return page.locator('.kanban-board')
      },
    },
    {
      name: 'formular',
      take: async (page, { t, lang }) => {
        // A taller window, so the dialog is not scrolled halfway.
        await page.setViewportSize({ width: 1280, height: 1300 })
        await page.getByRole('button', { name: t('task.create') }).click()
        const dialog = page.locator(`wa-dialog[label="${t('task.create')}"]`)
        await dialog.locator('wa-input[name="title"] input').fill(lang === 'de' ? 'Entwurf prüfen' : 'Review the draft')
        await relabel(page, { 'Auswahl testen': lang === 'de' ? 'Startseite' : 'Home page', 'Symbole prüfen': lang === 'de' ? 'Gestaltung' : 'Design' })

        return dialog.locator('[part~="dialog"]').first()
      },
    },
  ],
}

export default recording
