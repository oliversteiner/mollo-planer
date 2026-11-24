import { mountSuspended } from '@nuxt/test-utils/runtime'
import { beforeAll, describe, expect, it } from 'vitest'
import Chat from '~/components/assistant/Chat.vue'
import PlanPreview from '~/components/assistant/PlanPreview.vue'

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

const document = {
  format: 'planer-v2',
  revision: 3,
  project: { name: 'Podcast', locale: 'de', members: [], labels: [] },
  milestones: [{
    id: 'm-1',
    title: 'Konzept steht',
    todos: [
      { id: 't-new-1', title: 'Zielgruppe festlegen', tasks: [{ id: 'k-new-1', title: 'Hörerprofile schreiben', status: 'planned' }] },
      { id: 't-new-2', title: 'Name finden', tasks: [] }
    ]
  }]
}

const preview = {
  ok: true,
  dryRun: true,
  projectId: '1',
  changes: [{ kind: 'CREATE' as const, path: 'milestones[0].todos[0]', id: 't-new-1', fields: [], title: null }],
  errors: [],
  warnings: []
}

const props = { plan: JSON.stringify(document), preview, hints: [], struck: new Set<string>(), existingProject: true }

describe('AssistantPlanPreview', () => {
  it('shows the tree with a checkbox for every new element', async () => {
    const view = await mountSuspended(PlanPreview, { props })

    expect(view.text()).toContain('Konzept steht')
    expect(view.text()).toContain('Zielgruppe festlegen')
    // two new todos and one new task, the existing milestone has none
    expect(view.findAll('wa-checkbox[aria-label^="«"]').length).toBe(3)
  })

  it('a click on a checkbox asks to strike the element out, and a struck one is crossed out', async () => {
    const view = await mountSuspended(PlanPreview, { props: { ...props, struck: new Set(['t-new-2']) } })

    expect(view.find('[data-struck]').text()).toBe('Name finden')

    await view.findAll('wa-checkbox')[0]!.trigger('change')

    expect(view.emitted('toggle')?.[0]).toEqual(['t-new-1'])
  })

  it('"Jetzt eintragen" asks for the import, without mails by default, and only when the preview is fine', async () => {
    const view = await mountSuspended(PlanPreview, { props })
    const button = () => view.findAll('wa-button').find(item => item.text() === 'Jetzt eintragen')!

    await button().trigger('click')
    expect(view.emitted('import')).toEqual([[false]])

    await view.setProps({ preview: { ...preview, ok: false, errors: [{ path: 'milestones[0]', kind: 'rule', message: 'Fehler im Plan' }] } })
    expect(view.text()).toContain('Fehler im Plan')
    expect((button().element as unknown as HTMLButtonElement).disabled).toBe(true)
  })

  it('shows a warning about an assignment that is left out, and the import stays possible', async () => {
    const warning = { path: 'milestones[0].todos[0].tasks[0]', email: 'nobody@example.com', message: 'nobody@example.com ist nicht Mitglied des Projekts oder existiert nicht. Die Zuweisung wird nicht übernommen.' }
    const view = await mountSuspended(PlanPreview, { props: { ...props, preview: { ...preview, warnings: [warning] } } })

    expect(view.text()).toContain('1 Warnung')
    expect(view.text()).toContain('milestones[0].todos[0].tasks[0]')
    expect(view.text()).toContain('nobody@example.com ist nicht Mitglied')
    expect(view.findAll('wa-callout').some(callout => (callout.element as unknown as { variant: string }).variant === 'warning')).toBe(true)
    expect((view.findAll('wa-button').find(item => item.text() === 'Jetzt eintragen')!.element as unknown as HTMLButtonElement).disabled).toBe(false)
  })

  it('shows no warning box without warnings', async () => {
    const view = await mountSuspended(PlanPreview, { props })

    expect(view.text()).not.toContain('Warnung')
  })

  it('offers "In Ideen ablegen" and "Verwerfen"', async () => {
    const view = await mountSuspended(PlanPreview, { props })

    await view.findAll('wa-button').find(item => item.text() === 'In Ideen ablegen')!.trigger('click')
    await view.findAll('wa-button').find(item => item.text() === 'Verwerfen')!.trigger('click')

    expect(view.emitted('ideas')).toHaveLength(1)
    expect(view.emitted('discard')).toHaveLength(1)
  })
})

describe('AssistantChat choices', () => {
  const choices = [
    { kind: 'MILESTONE', label: 'Konzept steht', detail: 'bis Ende Januar', value: 'm-1', recommended: true },
    { kind: 'NEW_MILESTONE', label: 'Neuer Milestone', detail: null, value: null, recommended: false }
  ]

  it('shows the buttons of the last answer, the recommended one marked, and reports the click', async () => {
    const view = await mountSuspended(Chat, { props: { messages: [{ role: 'ASSISTANT', content: 'Wohin damit?' }], choices } })
    const buttons = view.findAll('.chat-choices wa-button')

    expect(buttons).toHaveLength(2)
    expect(buttons[0]!.text()).toContain('empfohlen')
    expect(buttons[0]!.text()).toContain('bis Ende Januar')

    await buttons[1]!.trigger('click')

    expect(view.emitted('choose')?.[0]).toEqual([choices[1]])
  })

  it('shows no buttons while Claude is thinking', async () => {
    const view = await mountSuspended(Chat, { props: { messages: [], choices, pending: true } })

    expect(view.find('.chat-choices').exists()).toBe(false)
  })

  it('starts in automatic mode and emits the chosen target kind', async () => {
    const view = await mountSuspended(Chat, { props: { messages: [] } })
    const select = view.find('wa-select')

    expect((select.element as unknown as { value: string }).value).toBe('')
    expect(select.findAll('wa-option').map(option => option.text())).toEqual(['Automatisch', 'Milestones', 'ToDo', 'Tasks', 'Idee'])

    Object.defineProperty(select.element, 'value', { value: 'TASK', writable: true, configurable: true })
    select.element.dispatchEvent(new Event('change'))

    expect(view.emitted('update:targetKind')).toEqual([['TASK']])
  })

  it('locks the target choice while a conversation is active', async () => {
    const view = await mountSuspended(Chat, {
      props: {
        messages: [{ role: 'USER', content: 'Plane Tasks.' }],
        targetKind: 'TASK',
        targetKindLocked: true
      }
    })
    const select = view.find('wa-select').element as unknown as { value: string, disabled: boolean }

    expect(select.value).toBe('TASK')
    expect(select.disabled).toBe(true)
  })

  it('translates target choices into English', async () => {
    const i18n = useNuxtApp().$i18n
    await i18n.setLocale('en')
    const view = await mountSuspended(Chat, { props: { messages: [] } })

    expect(view.find('wa-select').text()).toContain('Automatic')
    expect(view.find('wa-select').text()).toContain('Milestones')
    expect(view.find('wa-select').text()).toContain('Todo')
    expect(view.find('wa-select').text()).toContain('Tasks')
    expect(view.find('wa-select').text()).toContain('Idea')

    await i18n.setLocale('de')
  })
})
