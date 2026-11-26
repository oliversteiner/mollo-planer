import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises, type VueWrapper } from '@vue/test-utils'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { computed, ref } from 'vue'
import Timeline from '~/components/project/Timeline.vue'

const project = {
  id: '1',
  milestones: [
    {
      id: '1',
      title: 'Konzept steht',
      description: null,
      icon: null,
      dueDate: '2027-01-31',
      status: 'DONE',
      progress: { done: 1, total: 1, percent: 100 },
      todos: [{
        id: '10',
        title: 'Zielgruppe festlegen',
        description: null,
        icon: null,
        dueDate: null,
        status: 'DONE',
        progress: { done: 1, total: 1, percent: 100 },
        tasks: [{
          id: '100',
          title: 'Hörerprofile schreiben',
          description: null,
          icon: null,
          status: 'DONE',
          dueDate: null,
          commentCount: 3,
          labels: [{ id: '4', name: 'Fehler', color: 'RED', position: 3 }],
          assignee: { id: '5', name: 'Lindita Aliu', avatarUrl: null },
          viewerCanUpdate: false
        }]
      }]
    },
    {
      id: '2',
      title: 'Erste Folge online',
      description: null,
      icon: null,
      dueDate: '2027-03-31',
      status: 'OPEN',
      progress: { done: 0, total: 1, percent: 0 },
      todos: [{
        id: '11',
        title: 'Folge schneiden',
        description: null,
        icon: null,
        dueDate: null,
        status: 'OPEN',
        progress: { done: 0, total: 0, percent: 0 },
        tasks: []
      }]
    }
  ]
}

const { moveTaskToTodo, queryError, refetch, routeState, toastAdd } = vi.hoisted(() => ({
  toastAdd: vi.fn(),
  moveTaskToTodo: vi.fn(),
  queryError: { value: null as Error | null },
  refetch: vi.fn(),
  // The route the page is on (the test is not signed in, so the router would send it to the login).
  routeState: { params: { id: '1' }, query: {} as Record<string, string>, hash: '' }
}))

mockNuxtImport('useRoute', () => () => routeState)
// The real toast builds a wa-button, which happy-dom cannot upgrade (no ElementInternals).
mockNuxtImport('useToast', () => () => ({ add: toastAdd }))

/** The backend's answer; milestones in their order (position). */
const answer = ref<{ project: unknown } | undefined>({ project })

/** The answer of «Nach Version ordnen» (milestoneVersionOrder); null: not asked. */
const orderAnswer = ref<{ milestoneVersionOrder: unknown } | undefined>(undefined)
const sortMilestones = vi.fn()
const reorderMilestones = vi.fn()

vi.mock('@vue/apollo-composable', () => ({
  useQuery: (document: { definitions: { name?: { value: string } }[] }) => document.definitions[0]?.name?.value === 'MilestoneVersionOrder'
    ? { result: orderAnswer, loading: ref(false), error: ref(null), refetch }
    : { result: answer, loading: ref(false), error: computed(() => queryError.value), refetch },
  useMutation: (document: { definitions: { name?: { value: string } }[] }) => ({
    mutate: { SortMilestones: sortMilestones, ReorderMilestones: reorderMilestones }[document.definitions[0]?.name?.value ?? ''] ?? moveTaskToTodo
  }),
  // The revision of the planer file (S3f): nothing in the cache, nothing changed.
  useApolloClient: () => ({ resolveClient: () => ({ readQuery: () => null, query: async () => ({ data: null }), refetchQueries: async () => [] }) })
}))

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

describe('ProjectTimeline', () => {
  // The open milestones are remembered per project in the browser: every test starts without that memory.
  beforeEach(async () => {
    localStorage.clear()
    // Most tests read the milestones in their stored order; the turned display has its own tests below.
    localStorage.setItem('mollo.timeline.newestFirst', '0')
    orderAnswer.value = undefined
    toastAdd.mockClear()
    sortMilestones.mockReset()
    reorderMilestones.mockReset()
    answer.value = { project }
    queryError.value = null
    refetch.mockClear()
    routeState.query = {}
    routeState.hash = ''
  })

  it('shows milestones in their order, the reached ones as completed', async () => {
    const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })

    const stepper = timeline.find('wa-stepper').element as unknown as { active: string }
    const steps = timeline.findAll('.plan-timeline > wa-step')
    const reached = steps.map(step => (step.element as unknown as { completed: boolean }).completed)
    const titles = steps.map(step => step.find('.timeline-title').text())

    expect(titles).toEqual([
      expect.stringMatching(/^M1\s*Konzept steht/),
      expect.stringMatching(/^M2\s*Erste Folge online/)
    ])
    expect(reached).toEqual([true, false])
    expect(stepper.active).toBe('2')
    expect(timeline.text()).toContain('31.01.2027')
  })

  it('keeps the order of the backend (position), not the due dates (F5.4)', async () => {
    answer.value = { project: { ...project, milestones: [...project.milestones].reverse() } }
    const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })
    answer.value = { project }

    const titles = timeline.findAll('.plan-timeline > wa-step').map(step => step.find('.timeline-title').text())

    expect(titles).toEqual([
      expect.stringMatching(/^M1\s*Erste Folge online/),
      expect.stringMatching(/^M2\s*Konzept steht/)
    ])
  })

  const milestoneToggle = (timeline: VueWrapper, title: string) =>
    timeline.find(`button[aria-label="Milestone «${title}» auf- oder zuklappen"]`)
  const toolbarButton = (timeline: VueWrapper, text: string) =>
    timeline.findAll('wa-button').find(button => button.text() === text)!
  const isDisabled = (button: { element: Element }) => button.element.hasAttribute('disabled') || (button.element as HTMLElement & { disabled?: boolean }).disabled === true
  const todoToggle = (timeline: VueWrapper, title: string) =>
    timeline.find(`button[aria-label="ToDo «${title}» auf- oder zuklappen"]`)

  it('opens a todo to show its tasks with the assignee', async () => {
    const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })

    await milestoneToggle(timeline, 'Konzept steht').trigger('click')

    expect(timeline.text()).not.toContain('Hörerprofile schreiben')

    await todoToggle(timeline, 'Zielgruppe festlegen').trigger('click')

    expect(timeline.text()).toContain('Hörerprofile schreiben')
    expect(timeline.text()).toContain('Lindita Aliu')
    expect(timeline.text()).toContain('3 Kommentare')
    expect(timeline.find('.task-labels').text()).toBe('Fehler')
  })

  it('starts with every milestone closed except the current one (E1), each with its progress', async () => {
    const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })

    expect(milestoneToggle(timeline, 'Konzept steht').attributes('aria-expanded')).toBe('false')
    expect(milestoneToggle(timeline, 'Erste Folge online').attributes('aria-expanded')).toBe('true')
    expect(timeline.text()).not.toContain('Zielgruppe festlegen')
    expect(timeline.text()).toContain('Folge schneiden')
    expect(timeline.text()).toContain('1 von 1')
    expect(timeline.text()).toContain('0 von 1 Tasks erledigt · 1 offen')
    // The old buttons are gone.
    expect(timeline.text()).not.toContain('Nur Milestones anzeigen')
    expect(timeline.text()).not.toContain('Alle ToDos zeigen')
  })

  it('opens and closes a single milestone with its arrow, the content is what the arrow controls', async () => {
    const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })
    const toggle = milestoneToggle(timeline, 'Konzept steht')

    expect(toggle.attributes('aria-controls')).toBe('milestone-1-content')

    await toggle.trigger('click')

    expect(toggle.attributes('aria-expanded')).toBe('true')
    expect(timeline.find('#milestone-1-content').exists()).toBe(true)
    expect(timeline.text()).toContain('Zielgruppe festlegen')

    await toggle.trigger('click')

    expect(timeline.find('#milestone-1-content').exists()).toBe(false)
    expect(timeline.text()).not.toContain('Zielgruppe festlegen')
  })

  it('opens with the right arrow and closes with the left one, and does nothing when that changes nothing', async () => {
    const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })
    const toggle = milestoneToggle(timeline, 'Konzept steht')

    await toggle.trigger('keydown', { key: 'ArrowLeft' })
    expect(toggle.attributes('aria-expanded')).toBe('false')

    await toggle.trigger('keydown', { key: 'ArrowRight' })
    expect(toggle.attributes('aria-expanded')).toBe('true')

    await toggle.trigger('keydown', { key: 'ArrowLeft' })
    expect(toggle.attributes('aria-expanded')).toBe('false')
  })

  it('"Alle aufklappen" opens all milestones and "Alle zuklappen" closes them and the todos; each is off when it would change nothing', async () => {
    const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })

    expect(isDisabled(toolbarButton(timeline, 'Alle aufklappen'))).toBe(false)

    await toolbarButton(timeline, 'Alle aufklappen').trigger('click')

    expect(timeline.text()).toContain('Zielgruppe festlegen')
    expect(isDisabled(toolbarButton(timeline, 'Alle aufklappen'))).toBe(true)

    await todoToggle(timeline, 'Zielgruppe festlegen').trigger('click')

    expect(timeline.text()).toContain('Hörerprofile schreiben')

    await toolbarButton(timeline, 'Alle zuklappen').trigger('click')

    expect(timeline.text()).not.toContain('Zielgruppe festlegen')
    expect(isDisabled(toolbarButton(timeline, 'Alle zuklappen'))).toBe(true)

    // The todos were closed too: opening the milestone again shows the todo closed.
    await milestoneToggle(timeline, 'Konzept steht').trigger('click')

    expect(timeline.text()).toContain('Zielgruppe festlegen')
    expect(timeline.text()).not.toContain('Hörerprofile schreiben')
  })

  it('the Option key opens the todos of that milestone too, and closes them again', async () => {
    const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })

    await milestoneToggle(timeline, 'Konzept steht').trigger('click', { altKey: true })

    expect(timeline.text()).toContain('Hörerprofile schreiben')

    await milestoneToggle(timeline, 'Konzept steht').trigger('click', { altKey: true })
    await milestoneToggle(timeline, 'Konzept steht').trigger('click')

    expect(timeline.text()).toContain('Zielgruppe festlegen')
    expect(timeline.text()).not.toContain('Hörerprofile schreiben')
  })

  it('remembers the open milestones per project in the browser, and drops ids that are gone', async () => {
    const first = await mountSuspended(Timeline, { props: { projectId: '1' } })

    await milestoneToggle(first, 'Konzept steht').trigger('click')
    await milestoneToggle(first, 'Erste Folge online').trigger('click')

    expect(JSON.parse(localStorage.getItem('mollo.timeline.1.open')!)).toEqual(['1'])
    first.unmount()

    localStorage.setItem('mollo.timeline.1.open', JSON.stringify(['2', '99']))
    const second = await mountSuspended(Timeline, { props: { projectId: '1' } })

    // What is remembered wins, also when another milestone is the current one now; the unknown id is dropped.
    expect(milestoneToggle(second, 'Konzept steht').attributes('aria-expanded')).toBe('false')
    expect(milestoneToggle(second, 'Erste Folge online').attributes('aria-expanded')).toBe('true')
    second.unmount()

    // Another project has its own memory.
    expect(localStorage.getItem('mollo.timeline.2.open')).toBeNull()
  })

  it('does not remember anything until the person opens or closes something', async () => {
    await mountSuspended(Timeline, { props: { projectId: '1' } })

    expect(localStorage.getItem('mollo.timeline.1.open')).toBeNull()
  })

  it('an old link with ?milestones=only closes everything and removes the parameter', async () => {
    const replace = vi.spyOn(useRouter(), 'replace').mockResolvedValue(undefined)

    routeState.query = { milestones: 'only', other: 'keep' }

    const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })

    expect(milestoneToggle(timeline, 'Erste Folge online').attributes('aria-expanded')).toBe('false')
    expect(replace).toHaveBeenCalledWith({ query: { other: 'keep' } })
    replace.mockRestore()
  })

  it('opens the milestone a link points at: #milestone-<id>, and ?task=<id> with its todo', async () => {
    routeState.hash = '#milestone-1'

    const byAnchor = await mountSuspended(Timeline, { props: { projectId: '1' } })

    expect(milestoneToggle(byAnchor, 'Konzept steht').attributes('aria-expanded')).toBe('true')
    byAnchor.unmount()
    localStorage.clear()

    routeState.hash = ''
    routeState.query = { task: '100' }

    const byTask = await mountSuspended(Timeline, { props: { projectId: '1' } })

    expect(milestoneToggle(byTask, 'Konzept steht').attributes('aria-expanded')).toBe('true')
    expect(byTask.text()).toContain('Hörerprofile schreiben')
  })

  it('opens a closed milestone when a task is held over it for 600 ms while dragging', async () => {
    const timeline = await mountSuspended(Timeline, { props: { projectId: '1', canMoveTask: true } })

    // The list of the open milestone is there once its todo is open.
    await todoToggle(timeline, 'Folge schneiden').trigger('click')

    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })

    try {
      const drop = timeline.findAll('.milestone-drop')[0]!

      // Not dragging: nothing happens.
      await drop.trigger('dragover')
      vi.advanceTimersByTime(700)
      await nextTick()
      expect(milestoneToggle(timeline, 'Konzept steht').attributes('aria-expanded')).toBe('false')

      // Dragging a task (the list of the open milestone reports the start).
      timeline.findComponent({ name: 'VueDraggable' }).vm.$emit('start')
      await drop.trigger('dragover')
      vi.advanceTimersByTime(500)
      await nextTick()
      expect(milestoneToggle(timeline, 'Konzept steht').attributes('aria-expanded')).toBe('false')

      vi.advanceTimersByTime(200)
      await nextTick()
      expect(milestoneToggle(timeline, 'Konzept steht').attributes('aria-expanded')).toBe('true')
    } finally {
      vi.useRealTimers()
    }
  })

  it('shows how many ideas a milestone holds instead of a progress when there are only ideas', async () => {
    answer.value = {
      project: {
        ...project,
        milestones: [{
          ...project.milestones[1]!,
          progress: { done: 0, total: 0, percent: 0 },
          todos: [{ ...project.milestones[1]!.todos[0]!, tasks: [
            { ...project.milestones[0]!.todos[0]!.tasks[0]!, id: '300', status: 'IDEA' },
            { ...project.milestones[0]!.todos[0]!.tasks[0]!, id: '301', status: 'IDEA' }
          ] }]
        }]
      }
    }

    const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })

    expect(timeline.text()).toContain('2 Ideen')
    expect(timeline.text()).not.toContain('0 von 0')
  })

  it('shows a version and a date in the head, a release date instead of the due date once released (never both)', async () => {
    answer.value = {
      project: {
        ...project,
        milestones: [
          { ...project.milestones[0]!, release: { version: '1.0', status: 'RELEASED', date: '2026-09-15' } },
          { ...project.milestones[1]!, release: { version: '1.1', status: 'PLANNED', date: null } }
        ]
      }
    }

    const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })
    const heads = timeline.findAll('.milestone-head').map(head => head.text())

    expect(heads[0]).toContain('1.0 · Veröffentlicht')
    expect(heads[0]).toContain('2026')
    expect(heads[0]).not.toContain('Januar')
    expect(heads[1]).toContain('1.1 · Geplant')
  })

  describe('prices (only the API gives them, with billing.manage)', () => {
    const priced = (patch: Record<string, unknown>) => ({
      project: { ...project, milestones: [{ ...project.milestones[0]!, ...patch }, project.milestones[1]!] }
    })

    it('shows nothing about prices when the API gives none', async () => {
      const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })

      expect(timeline.find('.milestone-price').exists()).toBe(false)
      expect(timeline.find('.timeline-sum').exists()).toBe(false)
    })

    it('shows the price, with an invoice in draft or issued as a link to it, and the sums', async () => {
      answer.value = {
        project: {
          ...project,
          milestones: [
            { ...project.milestones[0]!, priceCents: 480000, billing: { invoice: { id: '7', number: '2026-0007', status: 'ISSUED' } } },
            { ...project.milestones[1]!, priceCents: 620000, billing: { invoice: { id: '8', number: null, status: 'DRAFT' } } }
          ]
        }
      }

      const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })
      const prices = timeline.findAll('.milestone-price').map(entry => entry.text().replace(/\s+/g, ' '))

      expect(prices).toEqual(['CHF 4’800.00 · Verrechnet 2026-0007', 'CHF 6’200.00 · Rechnung im Entwurf'])
      expect(timeline.findAll('.milestone-invoice').map(link => link.attributes('href'))).toEqual(['/billing/invoices/7', '/billing/invoices/8'])
      expect(timeline.find('.timeline-sum').text()).toBe('Preise: CHF 11’000.00 · verrechnet 4’800.00 · im Entwurf 6’200.00')
    })

    it('shows a price without an invoice as a plain badge, no link', async () => {
      answer.value = priced({ priceCents: 780000 })

      const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })

      expect(timeline.find('.milestone-price').text()).toBe('CHF 7’800.00')
      expect(timeline.find('.milestone-invoice').exists()).toBe(false)
    })

    it('hints «Ohne Preis» on a done milestone that is not billed, only with billing.manage, and says nothing on one that is not done', async () => {
      answer.value = priced({})
      const rights = useState<string[]>('systemRights')
      const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })

      // Without the right the API gives no price for anybody, so a missing price says nothing.
      expect(timeline.find('.milestone-price').exists()).toBe(false)

      rights.value = ['BILLING_MANAGE']
      await nextTick()

      expect(timeline.findAll('.milestone-price').map(entry => entry.text())).toEqual(['Ohne Preis'])
      expect(timeline.find('.timeline-sum').exists()).toBe(false)

      rights.value = []
    })
  })

  it('shows the error with «Erneut versuchen», not an empty project, when the load failed', async () => {
    answer.value = undefined
    queryError.value = new Error('network')

    const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })

    expect(timeline.text()).toContain('Die Milestones konnten nicht geladen werden.')
    expect(timeline.text()).not.toContain('Noch keine Milestones')
    expect(timeline.find('.timeline-toolbar').exists()).toBe(false)

    await timeline.findAll('wa-button').find(button => button.text() === 'Erneut versuchen')!.trigger('click')

    expect(refetch).toHaveBeenCalledTimes(1)
  })

  it('offers a new todo per open milestone only with the right to create one', async () => {
    const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })
    const createButtons = () => timeline.findAll('wa-button[aria-label^="Neues ToDo im Milestone"]')

    await toolbarButton(timeline, 'Alle aufklappen').trigger('click')

    expect(createButtons()).toHaveLength(0)

    await timeline.setProps({ canCreateTodo: true })

    expect(createButtons()).toHaveLength(2)

    await timeline.find('wa-button[aria-label="Neues ToDo im Milestone «Konzept steht» anlegen"]').trigger('click')

    expect(timeline.emitted('createTodo')).toEqual([['1']])
  })

  it('offers a new task per todo only with the right to create one', async () => {
    const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })
    const create = 'wa-button[aria-label="Neuen Task im ToDo «Zielgruppe festlegen» anlegen"]'

    await milestoneToggle(timeline, 'Konzept steht').trigger('click')
    await todoToggle(timeline, 'Zielgruppe festlegen').trigger('click')

    expect(timeline.find(create).exists()).toBe(false)

    await timeline.setProps({ canCreateTask: true })
    await timeline.find(create).trigger('click')

    expect(timeline.emitted('createTask')).toEqual([['10']])
  })

  it('shows the actions menu of milestones and todos only with entries', async () => {
    const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })
    const menus = () => timeline.findAll('wa-button[aria-label^="Aktionen für"]').map(button => button.attributes('aria-label'))

    await toolbarButton(timeline, 'Alle aufklappen').trigger('click')

    expect(menus()).toEqual([])

    await timeline.setProps({ actions: (kind: 'milestone' | 'todo') => (kind === 'todo' ? [{ label: 'Ändern' }] : []) })

    expect(menus()).toEqual(['Aktionen für ToDo «Zielgruppe festlegen»', 'Aktionen für ToDo «Folge schneiden»'])

    await timeline.setProps({ actions: () => [{ label: 'Ändern' }] })

    expect(menus()).toEqual([
      'Aktionen für Milestone «Konzept steht»',
      'Aktionen für ToDo «Zielgruppe festlegen»',
      'Aktionen für Milestone «Erste Folge online»',
      'Aktionen für ToDo «Folge schneiden»'
    ])
  })

  it('tells the menu of a milestone its place, for "Nach oben" and "Nach unten"', async () => {
    const actions = vi.fn(() => [{ label: 'Ändern' }])
    await mountSuspended(Timeline, { props: { projectId: '1', actions } })

    expect(actions).toHaveBeenCalledWith('milestone', expect.objectContaining({ id: '1' }), { index: 0, count: 2, reversed: false })
    expect(actions).toHaveBeenCalledWith('milestone', expect.objectContaining({ id: '2' }), { index: 1, count: 2, reversed: false })
    expect(actions).toHaveBeenCalledWith('todo', expect.objectContaining({ id: '11' }), undefined)
  })

  it('opens a task by click and edits it by double-click when this task may be changed', async () => {
    const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })

    await milestoneToggle(timeline, 'Konzept steht').trigger('click')
    await todoToggle(timeline, 'Zielgruppe festlegen').trigger('click')

    const task = () => timeline.find('button[aria-label="Task «Hörerprofile schreiben» öffnen"]')

    await task().trigger('click')
    await task().trigger('dblclick')

    expect(timeline.emitted('openTask')).toEqual([['100']])
    expect(timeline.emitted('editTask')).toBeUndefined()

    // Task.viewerCanUpdate: per task, task.updateOwn holds for the own ones (F4.5).
    const fixture = project.milestones[0]!.todos[0]!.tasks[0]!
    fixture.viewerCanUpdate = true
    await task().trigger('dblclick')
    fixture.viewerCanUpdate = false

    expect(timeline.emitted('editTask')).toEqual([[expect.objectContaining({ id: '100', title: 'Hörerprofile schreiben' })]])
  })

  it('moves a task dropped into another todo, only with the right to', async () => {
    const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })
    const lists = () => timeline.findAllComponents({ name: 'VueDraggable' })

    await toolbarButton(timeline, 'Alle aufklappen').trigger('click')
    await todoToggle(timeline, 'Zielgruppe festlegen').trigger('click')
    await todoToggle(timeline, 'Folge schneiden').trigger('click')

    expect(lists()).toHaveLength(2)
    expect(lists().every(list => list.props('disabled'))).toBe(true)

    await timeline.setProps({ canMoveTask: true })

    const [from, to] = lists().map(list => list.element as HTMLElement)

    expect(to!.dataset.todoId).toBe('11')

    // SortableJS reports the drop; within the same todo nothing is saved.
    lists()[0]!.vm.$emit('end', { item: { dataset: { id: '100' } }, from, to: from })
    lists()[0]!.vm.$emit('end', { item: { dataset: { id: '100' } }, from, to })
    await flushPromises()

    expect(moveTaskToTodo).toHaveBeenCalledTimes(1)
    expect(moveTaskToTodo).toHaveBeenCalledWith({ id: '100', todoId: '11' })
  })

  describe('the current milestone is open at every visit (Auftrag 47 E)', () => {
    it('opens the current milestone even when it was closed the last time; the others keep their state', async () => {
      localStorage.setItem('mollo.timeline.1.open', JSON.stringify(['1']))

      const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })

      expect(milestoneToggle(timeline, 'Konzept steht').attributes('aria-expanded')).toBe('true')
      expect(milestoneToggle(timeline, 'Erste Folge online').attributes('aria-expanded')).toBe('true')
    })

    it('does not write the current milestone into the memory by itself', async () => {
      localStorage.setItem('mollo.timeline.1.open', JSON.stringify([]))

      await mountSuspended(Timeline, { props: { projectId: '1' } })

      expect(localStorage.getItem('mollo.timeline.1.open')).toBe('[]')
    })
  })

  describe('the order of the display (Auftrag 47 F)', () => {
    const titles = (timeline: VueWrapper) => timeline.findAll('.plan-timeline > wa-step').map(step => step.find('.timeline-title').text())

    it('shows the newest first by default, with the numbers of the stored order', async () => {
      localStorage.removeItem('mollo.timeline.newestFirst')

      const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })

      expect(titles(timeline)).toEqual([expect.stringMatching(/^M2\s*Erste Folge online/), expect.stringMatching(/^M1\s*Konzept steht/)])
    })

    it('turns the display with the button and remembers it in the browser', async () => {
      localStorage.removeItem('mollo.timeline.newestFirst')
      const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })

      await timeline.find('#timeline-direction').trigger('click')

      expect(titles(timeline)).toEqual([expect.stringMatching(/^M1\s*Konzept steht/), expect.stringMatching(/^M2\s*Erste Folge online/)])
      expect(localStorage.getItem('mollo.timeline.newestFirst')).toBe('0')
      expect(timeline.find('#timeline-direction').attributes('aria-pressed')).toBe('false')
    })

    it('tells the menu that the display is turned, so «Nach oben» goes the right way', async () => {
      localStorage.removeItem('mollo.timeline.newestFirst')
      const actions = vi.fn(() => [{ label: 'Ändern' }])

      await mountSuspended(Timeline, { props: { projectId: '1', actions } })

      expect(actions).toHaveBeenCalledWith('milestone', expect.objectContaining({ id: '1' }), { index: 0, count: 2, reversed: true })
    })
  })

  describe('«Preise anzeigen» (Auftrag 47 G)', () => {
    const withPrice = () => {
      answer.value = { project: { ...project, milestones: [{ ...project.milestones[0]!, priceCents: 480000 }, project.milestones[1]!] } }
    }

    it('is only there with billing.manage', async () => {
      withPrice()

      const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })

      expect(timeline.find('#timeline-prices').exists()).toBe(false)
    })

    it('hides every price and the sum when it is off, and remembers that', async () => {
      withPrice()
      const rights = useState<string[]>('systemRights')
      const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })

      rights.value = ['BILLING_MANAGE']
      await nextTick()

      expect(timeline.find('.milestone-price').exists()).toBe(true)
      expect(timeline.find('.timeline-sum').exists()).toBe(true)

      const toggle = timeline.find('#timeline-prices')
      ;(toggle.element as HTMLInputElement).checked = false
      await toggle.trigger('change')

      expect(timeline.find('.milestone-price').exists()).toBe(false)
      expect(timeline.find('.timeline-sum').exists()).toBe(false)
      expect(localStorage.getItem('mollo.timeline.prices')).toBe('0')

      rights.value = []
    })
  })

  describe('«Nach Version ordnen» (Auftrag 47 C)', () => {
    const plan = (patch: Record<string, unknown> = {}) => ({
      milestoneVersionOrder: {
        revision: 12,
        canApply: true,
        condition: null,
        moveCount: 2,
        conditionMilestones: [],
        order: [
          { id: '2', title: 'Erste Folge online', version: '1.0', from: 1, to: 0, moved: true },
          { id: '1', title: 'Konzept steht', version: '1.1', from: 0, to: 1, moved: true }
        ],
        ...patch
      }
    })

    it('has no button without the right to edit milestones', async () => {
      orderAnswer.value = plan({ canApply: false, condition: 'no_right' })

      const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })

      expect(timeline.find('#timeline-order').exists()).toBe(false)
    })

    it('is off with the first condition that is not met as its tooltip', async () => {
      orderAnswer.value = plan({ canApply: false, condition: 'too_few_versions', moveCount: 0 })

      const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })

      expect(isDisabled(timeline.find('#timeline-order'))).toBe(true)
      const tooltip = timeline.findAll('wa-tooltip').find(entry => (entry.element as HTMLElement & { for?: string }).for === 'timeline-order-wrap' || entry.attributes('for') === 'timeline-order-wrap')

      expect(tooltip?.text()).toBe('Mindestens zwei Milestones brauchen eine Version.')
    })

    it('lists the milestones with invalid or doubled versions, each with a link', async () => {
      orderAnswer.value = plan({ canApply: false, condition: 'invalid_version', conditionMilestones: [{ id: '2', title: 'Erste Folge online', version: 'v2' }] })

      const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })
      const links = timeline.findAll('.timeline-order-hint a')

      expect(links.map(link => link.attributes('href'))).toEqual(['#milestone-2'])
      expect(links[0]!.text()).toBe('M2 Erste Folge online (v2)')
    })

    // Opening the real wa-dialog makes happy-dom update the buttons behind it, which it cannot (no ElementInternals): the
    // preview is tested on its own below, here only what the Timeline hands to it and what it does with «Neu ordnen».
    it('hands the plan to the preview and applies the order with the revision it saw, then offers «Rückgängig»', async () => {
      orderAnswer.value = plan()
      sortMilestones.mockResolvedValue({ data: { sortMilestones: { revision: 13, moveCount: 2, order: ['2', '1'], before: ['1', '2'] } } })
      const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })
      const dialog = timeline.findComponent({ name: 'ProjectVersionOrderDialog' })

      expect(dialog.props('plan')).toMatchObject({ revision: 12, moveCount: 2, canApply: true })

      dialog.vm.$emit('apply')
      await flushPromises()

      expect(sortMilestones).toHaveBeenCalledWith({ projectId: '1', expectedRevision: 12 })
      expect(refetch).toHaveBeenCalled()
      expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({
        title: 'Milestones nach Version geordnet',
        action: expect.objectContaining({ label: 'Rückgängig' })
      }))
    })

    it('puts the earlier order back with «Rückgängig»', async () => {
      orderAnswer.value = plan()
      sortMilestones.mockResolvedValue({ data: { sortMilestones: { revision: 13, moveCount: 2, order: ['2', '1'], before: ['1', '2'] } } })
      reorderMilestones.mockResolvedValue({ data: { reorderSiblings: { revision: 14, moveCount: 2 } } })
      const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })

      timeline.findComponent({ name: 'ProjectVersionOrderDialog' }).vm.$emit('apply')
      await flushPromises()
      toastAdd.mock.calls[0]![0].action.run()
      await flushPromises()

      expect(reorderMilestones).toHaveBeenCalledWith({ projectId: '1', ids: ['1', '2'] })
    })

    it('reloads the preview and tells so when the plan changed in the meantime, and writes nothing', async () => {
      orderAnswer.value = plan()
      sortMilestones.mockRejectedValue({ graphQLErrors: [{ extensions: { code: 'ORDER_REVISION' } }] })
      const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })

      timeline.findComponent({ name: 'ProjectVersionOrderDialog' }).vm.$emit('apply')
      await flushPromises()

      expect(refetch).toHaveBeenCalled()
      expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ title: expect.stringContaining('Der Plan wurde inzwischen geändert.'), color: 'warning' }))
    })

    it('does not apply when a condition is not met', async () => {
      orderAnswer.value = plan({ canApply: false, condition: 'already_ordered', moveCount: 0 })
      const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })

      await (timeline.vm as unknown as { applyOrder: () => Promise<void> }).applyOrder?.()

      expect(sortMilestones).not.toHaveBeenCalled()
    })
  })
})
