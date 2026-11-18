import { mountSuspended } from '@nuxt/test-utils/runtime'
import { beforeAll, describe, expect, it } from 'vitest'
import TimelineTask from '~/components/project/TimelineTask.vue'

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

const task = (status: string) => ({
  id: '1',
  title: 'Styleguide erstellen',
  description: null,
  icon: null,
  status,
  dueDate: '2026-12-01',
  commentCount: 2,
  labels: [],
  assignee: null
}) as never

/** What the calm view hides is marked in the component; zen.css does the hiding (docs/zen-modus.md §4.2). */
describe('zen marks of a task in the timeline', () => {
  it('hides a done task, keeps an open one', async () => {
    expect((await mountSuspended(TimelineTask, { props: { task: task('DONE'), menu: [] } })).attributes('data-zen')).toBe('hide')
    expect((await mountSuspended(TimelineTask, { props: { task: task('OPEN'), menu: [] } })).attributes('data-zen')).toBeUndefined()
  })

  it('marks comments and the menu as hidden and the due date as hover, the title and status stay unmarked', async () => {
    const view = await mountSuspended(TimelineTask, { props: { task: task('OPEN'), menu: [{ label: 'Ändern', onSelect: () => undefined }] } })

    expect(view.find('.task-comments').attributes('data-zen')).toBe('hide')
    expect(view.find('[data-zen="hover"]').exists()).toBe(true)
    expect(view.find('.task-title').attributes('data-zen')).toBeUndefined()
    expect(view.find('.task-row-menu, [data-zen="hide"] wa-dropdown, [data-zen="hide"]').exists()).toBe(true)
  })
})
