import { mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import Timeline from '~/components/project/Timeline.vue'

const project = {
  id: '1',
  milestones: [
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
    },
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
          assignee: { id: '5', name: 'Lindita Aliu', avatarUrl: null }
        }]
      }]
    }
  ]
}

const { moveTaskToTodo } = vi.hoisted(() => ({ moveTaskToTodo: vi.fn() }))

vi.mock('@vue/apollo-composable', () => ({
  useQuery: () => ({ result: ref({ project }), loading: ref(false), refetch: vi.fn() }),
  useMutation: () => ({ mutate: moveTaskToTodo })
}))

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

describe('ProjectTimeline', () => {
  it('shows milestones by due date, the reached ones as completed', async () => {
    const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })

    const items = timeline.findAll('.plan-timeline > [data-slot="item"]')

    expect(items.map(item => item.find('[data-slot="title"]').text())).toEqual([
      expect.stringContaining('Konzept steht'),
      expect.stringContaining('Erste Folge online')
    ])
    expect(items.map(item => item.attributes('data-state'))).toEqual(['completed', 'active'])
    expect(items.map(item => item.find('[data-slot="title"]').text())).toEqual([
      expect.stringMatching(/^M1\s*Konzept steht/),
      expect.stringMatching(/^M2\s*Erste Folge online/)
    ])
    expect(timeline.text()).toContain('31. Januar 2027')
  })

  it('opens a todo to show its tasks with the assignee', async () => {
    const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })

    expect(timeline.text()).not.toContain('Hörerprofile schreiben')

    await timeline.find('button[aria-label="ToDo «Zielgruppe festlegen» auf- oder zuklappen"]').trigger('click')

    expect(timeline.text()).toContain('Hörerprofile schreiben')
    expect(timeline.text()).toContain('Lindita Aliu')
    expect(timeline.text()).toContain('3 Kommentare')
  })

  it('"Nur Milestones anzeigen" hides the todos and shows them again', async () => {
    const timeline = await mountSuspended(Timeline, {
      props: {
        'projectId': '1',
        'milestonesOnly': false,
        'onUpdate:milestonesOnly': (value: boolean) => timeline.setProps({ milestonesOnly: value })
      }
    })
    const toggle = () => timeline.findAll('button').find(button => ['Nur Milestones anzeigen', 'Alles anzeigen'].includes(button.text()))!

    expect(timeline.text()).toContain('Zielgruppe festlegen')

    await toggle().trigger('click')

    expect(timeline.emitted('update:milestonesOnly')).toEqual([[true]])
    expect(timeline.text()).toContain('Konzept steht')
    expect(timeline.text()).not.toContain('Zielgruppe festlegen')
    expect(timeline.text()).not.toContain('Alle aufklappen')
    expect(toggle().text()).toBe('Alles anzeigen')
    expect(toggle().attributes('aria-pressed')).toBe('true')

    await toggle().trigger('click')

    expect(timeline.text()).toContain('Zielgruppe festlegen')
  })

  it('starts with only the milestones when the page says so', async () => {
    const timeline = await mountSuspended(Timeline, { props: { projectId: '1', milestonesOnly: true } })

    expect(timeline.text()).toContain('Konzept steht')
    expect(timeline.text()).not.toContain('Zielgruppe festlegen')
  })

  it('offers a new todo per milestone only with the right to create one', async () => {
    const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })
    const createButtons = () => timeline.findAll('button[aria-label^="Neues ToDo im Milestone"]')

    expect(createButtons()).toHaveLength(0)

    await timeline.setProps({ canCreateTodo: true })

    expect(createButtons()).toHaveLength(2)

    await timeline.find('button[aria-label="Neues ToDo im Milestone «Konzept steht» anlegen"]').trigger('click')

    expect(timeline.emitted('createTodo')).toEqual([['1']])
  })

  it('offers a new task per todo only with the right to create one', async () => {
    const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })
    const todo = 'button[aria-label="ToDo «Zielgruppe festlegen» auf- oder zuklappen"]'
    const create = 'button[aria-label="Neuen Task im ToDo «Zielgruppe festlegen» anlegen"]'

    await timeline.find(todo).trigger('click')

    expect(timeline.find(create).exists()).toBe(false)

    await timeline.setProps({ canCreateTask: true })
    await timeline.find(create).trigger('click')

    expect(timeline.emitted('createTask')).toEqual([['10']])
  })

  it('shows the actions menu of milestones and todos only with entries', async () => {
    const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })
    const menus = () => timeline.findAll('button[aria-label^="Aktionen für"]').map(button => button.attributes('aria-label'))

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

  it('opens a task by click and edits it by double-click with the right to', async () => {
    const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })

    await timeline.find('button[aria-label="ToDo «Zielgruppe festlegen» auf- oder zuklappen"]').trigger('click')

    const task = () => timeline.find('button[aria-label="Task «Hörerprofile schreiben» öffnen"]')

    await task().trigger('click')
    await task().trigger('dblclick')

    expect(timeline.emitted('openTask')).toEqual([['100']])
    expect(timeline.emitted('editTask')).toBeUndefined()

    await timeline.setProps({ canUpdateTask: true })
    await task().trigger('dblclick')

    expect(timeline.emitted('editTask')).toEqual([[expect.objectContaining({ id: '100', title: 'Hörerprofile schreiben' })]])
  })

  it('moves a task dropped into another todo, only with the right to', async () => {
    const timeline = await mountSuspended(Timeline, { props: { projectId: '1' } })
    const lists = () => timeline.findAllComponents({ name: 'VueDraggable' })

    await timeline.findAll('button').find(button => button.text() === 'Alle aufklappen')!.trigger('click')

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
})
