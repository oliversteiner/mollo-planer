import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import { computed, ref } from 'vue'
import AgentBadge from '~/components/AgentBadge.vue'
import EmptyState from '~/components/EmptyState.vue'
import ProjectGrid from '~/components/project/Grid.vue'
import StatisticsPage from '~/pages/projects/[id]/statistics.vue'
import { installElementInternals } from './support/element-internals'

const { answer } = vi.hoisted(() => ({ answer: { value: undefined as unknown } }))

vi.mock('@vue/apollo-composable', () => ({
  useQuery: () => ({ result: ref(answer.value), loading: ref(false), refetch: vi.fn() })
}))
mockNuxtImport('useProject', () => () => ({ projectId: computed(() => '7'), project: computed(() => ({ id: '7' })), loaded: computed(() => true), can: () => true }))

installElementInternals()

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

const progress = (done: number, total: number, percent: number) => ({ done, total, percent })

const filled = {
  projectStatistics: {
    milestones: { total: 3, released: 1, planned: 1, unversioned: 1 },
    todos: 12,
    tasks: 40,
    progress: progress(18, 36, 50),
    tasksByStatus: [
      { status: 'IDEA', count: 4, percent: 10 }, { status: 'PLANNED', count: 6, percent: 15 }, { status: 'OPEN', count: 8, percent: 20 },
      { status: 'IN_PROGRESS', count: 2, percent: 5 }, { status: 'REVIEW', count: 2, percent: 5 }, { status: 'DONE', count: 18, percent: 45 }
    ],
    tasksByLabel: [{ label: { id: '1', name: 'Fehler', color: 'RED', position: 0 }, count: 5 }, { label: null, count: 35 }],
    tasksByPerson: [
      { user: { id: '2', name: 'Ben Müller', avatarUrl: null, isAgent: false }, open: 9, done: 3 },
      { user: { id: '3', name: 'Kevin', avatarUrl: null, isAgent: true }, open: 1, done: 15 },
      { user: null, open: 2, done: 0 }
    ],
    due: { overdue: 2, soon: 3, undated: 7 },
    perMilestone: [
      { milestone: { id: '11', title: 'Erster', status: 'DONE', release: { version: '1.0', status: 'RELEASED', date: '2026-11-01' } }, todos: 4, tasks: 10, progress: progress(10, 10, 100) },
      { milestone: { id: '12', title: 'Ohne Version', status: 'OPEN', release: null }, todos: 0, tasks: 0, progress: progress(0, 0, 0) }
    ],
    withCommit: 9,
    comments: 21
  }
}

describe('project statistics page', () => {
  it('shows the key numbers, every bar with its number, and the agent marked', async () => {
    answer.value = filled
    const page = await mountSuspended(StatisticsPage)
    const text = page.text()

    expect(page.findAll('.statistics-kpi-value').map(value => value.text())).toEqual(['3', '12', '40', '50 %'])
    expect(text).toContain('1 veröffentlicht · 1 geplant · 1 ohne Version')
    expect(text).toContain('18 von 36 Tasks (Ideen zählen nicht)')
    expect(text).toContain('18 · 45 %')
    expect(text).toContain('Ohne Label')
    expect(text).toContain('9 offen · 3 erledigt')
    expect(text).toContain('Nicht zugewiesen')
    expect(page.findAllComponents(AgentBadge)).toHaveLength(1)
    expect(page.findAll('wa-progress-bar').every(bar => bar.attributes('label') || (bar.element as unknown as { label?: string }).label)).toBe(true)
  })

  it('shows the due dates and the other counts', async () => {
    answer.value = filled
    const text = (await mountSuspended(StatisticsPage)).text()

    expect(text).toMatch(/Überfällig\s*2/)
    expect(text).toMatch(/Fällig in den nächsten 7 Tagen\s*3/)
    expect(text).toMatch(/Ohne Datum\s*7/)
    expect(text).toMatch(/Tasks mit Commit\s*9/)
    expect(text).toMatch(/Kommentare\s*21/)
  })

  it('has one row per milestone in the plan order, linked to the milestone', async () => {
    answer.value = filled
    const rows = (await mountSuspended(StatisticsPage)).findAll('tbody tr')

    expect(rows).toHaveLength(2)
    expect(rows[0]!.find('a').attributes('href')).toBe('/projects/7#milestone-11')
    expect(rows[0]!.text()).toContain('1.0 · Veröffentlicht')
    expect(rows[0]!.text()).toContain('1. November 2026')
    expect(rows[0]!.text()).toContain('100 %')
    expect(rows[1]!.find('a').text()).toBe('Ohne Version')
    expect(rows[1]!.text()).toContain('–')
  })

  it('shows an empty state instead of zeros for an empty project', async () => {
    answer.value = { projectStatistics: { ...filled.projectStatistics, milestones: { total: 0, released: 0, planned: 0, unversioned: 0 }, todos: 0, tasks: 0, perMilestone: [] } }
    const page = await mountSuspended(StatisticsPage)

    expect(page.findComponent(EmptyState).text()).toContain('Noch nichts zu zählen')
    expect(page.find('.statistics-kpis').exists()).toBe(false)
  })
})

describe('project card', () => {
  it('shows the counts of milestones, todos and tasks', async () => {
    const projects = [{ id: '7', name: 'Podcast', description: null, viewerRole: null, progress: progress(1, 2, 50), counts: { milestones: 3, todos: 12, tasks: 40 }, members: [] }]
    const grid = await mountSuspended(ProjectGrid, { props: { projects } })

    expect(grid.text()).toContain('3 Milestones · 12 ToDo · 40 Tasks')
  })
})
