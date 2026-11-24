import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import ImportModal from '~/components/planer/ImportModal.vue'

const { sent, toasts } = vi.hoisted(() => ({ sent: [] as Array<Record<string, unknown>>, toasts: [] as Array<Record<string, unknown>> }))

const warning = {
  path: 'milestones[0].todos[0].tasks[0]',
  email: 'nobody@example.com',
  message: 'nobody@example.com ist nicht Mitglied des Projekts oder existiert nicht. Die Zuweisung wird nicht übernommen.'
}

mockNuxtImport('useProjectMutation', () => () => ({
  mutate: vi.fn(async (variables: Record<string, unknown>) => {
    sent.push(variables)

    return {
      data: {
        importPlan: {
          ok: true,
          dryRun: variables.dryRun,
          projectId: '1',
          changes: [{ kind: 'UPDATE', path: 'milestones[0].todos[0].tasks[0]', id: 'k-1', fields: ['assignee'], title: null }],
          errors: [],
          warnings: [warning]
        }
      }
    }
  })
}))
mockNuxtImport('useProjectRefresh', () => () => vi.fn(async () => undefined))
mockNuxtImport('useToast', () => () => ({ add: (toast: Record<string, unknown>) => toasts.push(toast) }))

const file = JSON.stringify({
  format: 'planer-v2',
  revision: 1,
  project: { name: 'Podcast', locale: 'de', members: [], labels: [] },
  milestones: [{ id: 'm-1', title: 'Konzept', todos: [{ id: 't-1', title: 'Start', tasks: [{ id: 'k-1', title: 'Aufgabe', status: 'planned', assigneeEmail: 'nobody@example.com' }] }] }]
})

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

beforeEach(() => {
  sent.length = 0
  toasts.length = 0
})

async function chooseFile(view: Awaited<ReturnType<typeof mountSuspended>>): Promise<void> {
  const input = view.find('input[type="file"]').element as HTMLInputElement

  Object.defineProperty(input, 'files', { value: [new File([file], 'plan.json', { type: 'application/json' })], configurable: true })
  input.dispatchEvent(new Event('change'))
  await flushPromises()
  await new Promise(resolve => setTimeout(resolve, 0))
  await flushPromises()
}

describe('ImportModal warnings (Auftrag 42 B)', () => {
  it('shows the warning of the dry run with the place and the address, and the import stays possible', async () => {
    const view = await mountSuspended(ImportModal, { props: { projectId: '1', open: true } })

    await chooseFile(view)

    const text = view.text()
    expect(sent[0]).toMatchObject({ dryRun: true })
    expect(text).toContain('1 Warnung')
    expect(text).toContain('milestones[0].todos[0].tasks[0]')
    expect(text).toContain('nobody@example.com ist nicht Mitglied')
    view.unmount()
  })

  it('names the left-out entries in the message after the real import', async () => {
    const view = await mountSuspended(ImportModal, { props: { projectId: '1', open: true } })

    await chooseFile(view)
    await view.findAll('wa-button').find(button => button.text() === 'Eintragen')!.trigger('click')
    await flushPromises()

    expect(sent.at(-1)).toMatchObject({ dryRun: false })
    expect(toasts.at(-1)).toMatchObject({ color: 'warning', description: expect.stringContaining('nicht übernommen') })
    view.unmount()
  })
})
