import { mountSuspended } from '@nuxt/test-utils/runtime'
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
      todos: []
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
          assignee: { id: '5', name: 'Lindita Aliu', avatarUrl: null }
        }]
      }]
    }
  ]
}

vi.mock('@vue/apollo-composable', () => ({
  useQuery: () => ({ result: ref({ project }), loading: ref(false) })
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
})
