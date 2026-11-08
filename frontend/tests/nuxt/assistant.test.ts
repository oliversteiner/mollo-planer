import { mountSuspended } from '@nuxt/test-utils/runtime'
import { beforeAll, describe, expect, it } from 'vitest'
import PlanPreview from '~/components/assistant/PlanPreview.vue'
import type { ProjectPlan } from '~/utils/plan'

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

const plan: ProjectPlan = {
  name: 'Podcast «Zwischen Welten»',
  description: 'Ein Podcast über Menschen mit Migrationsgeschichte.',
  locale: 'DE',
  members: [{ email: 'lindita@example.ch', role: 'MEMBER' }],
  milestones: [{
    title: 'Konzept steht',
    description: 'Format und Zielgruppe sind klar.',
    dueDate: '2027-01-31',
    todos: [{
      title: 'Zielgruppe festlegen',
      description: null,
      dueDate: null,
      tasks: [{ title: 'Hörerprofile schreiben', description: '- Alter\n- Interessen', dueDate: null, assigneeEmail: 'lindita@example.ch' }]
    }]
  }]
} as ProjectPlan

const people = { 'lindita@example.ch': { name: 'Lindita Aliu', avatarUrl: null } }

describe('AssistantPlanPreview', () => {
  it('shows name, description and the milestones; todos open on demand', async () => {
    const preview = await mountSuspended(PlanPreview, { props: { plan, people } })

    expect(preview.text()).toContain('Podcast «Zwischen Welten»')
    expect(preview.text()).toContain('Ein Podcast über Menschen mit Migrationsgeschichte.')
    expect(preview.text()).toContain('Konzept steht')
    expect(preview.text()).toContain('31.01.2027')
    expect(preview.text()).not.toContain('Hörerprofile schreiben')

    await preview.find('button[aria-label="ToDo «Zielgruppe festlegen» auf- oder zuklappen"]').trigger('click')

    expect(preview.text()).toContain('Hörerprofile schreiben')
    expect(preview.text()).toContain('Lindita Aliu')
    expect(preview.findAll('li').map(item => item.text())).toContain('Interessen')
  })

  it('"Jetzt eintragen" asks for the import, without mails by default', async () => {
    const preview = await mountSuspended(PlanPreview, { props: { plan, people } })

    await preview.findAll('button').find(button => button.text() === 'Jetzt eintragen')!.trigger('click')

    expect(preview.emitted('import')).toEqual([[false]])
  })
})
