import { mountSuspended } from '@nuxt/test-utils/runtime'
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest'
import DueDate from '~/components/DueDate.vue'
import ItemIcon from '~/components/ItemIcon.vue'
import StatusBadge from '~/components/StatusBadge.vue'
import TaskCard from '~/components/task/Card.vue'

// The test browser reports English; the texts below are checked in German.
beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

/** Vue sets the properties of a custom element, not attributes. */
function iconProps(wrapper: { element: Element }) {
  return wrapper.element as unknown as { name: string, label?: string }
}

describe('ItemIcon', () => {
  it('shows the chosen icon and hides it from screen readers without a label', async () => {
    const icon = await mountSuspended(ItemIcon, { props: { name: 'rocket', fallback: 'flag-checkered' } })

    expect(icon.element.tagName).toBe('WA-ICON')
    expect(iconProps(icon).name).toBe('rocket')
    expect(iconProps(icon).label || undefined).toBeUndefined()
  })

  it('uses the fallback for no name and for names outside the server format', async () => {
    for (const name of [null, '', '../../secret', 'Rocket', 'rocket.svg']) {
      const icon = await mountSuspended(ItemIcon, { props: { name, fallback: 'flag-checkered' } })

      expect(iconProps(icon).name).toBe('flag-checkered')
    }
  })

  it('uses the fallback when the server has no such icon', async () => {
    const icon = await mountSuspended(ItemIcon, { props: { name: 'no-such-icon', fallback: 'flag-checkered' } })

    icon.element.dispatchEvent(new Event('wa-error'))
    await nextTick()

    expect(iconProps(icon).name).toBe('flag-checkered')
  })

  it('has a name for screen readers when it has a label', async () => {
    const icon = await mountSuspended(ItemIcon, { props: { name: 'rocket', fallback: 'flag-checkered', label: 'Rakete' } })

    expect(iconProps(icon).label).toBe('Rakete')
  })
})

describe('DueDate', () => {
  it('marks a past date as overdue, in red and for screen readers', async () => {
    const date = await mountSuspended(DueDate, { props: { date: '2020-01-31' } })

    expect(date.attributes('data-overdue')).toBe('')
    expect(date.text()).toContain('31.01.2020')
    expect(date.text()).toContain('überfällig')
  })

  it('does not mark done items or future dates, and shows nothing without a date', async () => {
    expect((await mountSuspended(DueDate, { props: { date: '2020-01-31', done: true } })).attributes('data-overdue')).toBeUndefined()
    expect((await mountSuspended(DueDate, { props: { date: '2999-12-31' } })).attributes('data-overdue')).toBeUndefined()
    expect((await mountSuspended(DueDate, { props: { date: null } })).text()).toBe('')
  })
})

describe('StatusBadge', () => {
  it('shows the translated status', async () => {
    expect((await mountSuspended(StatusBadge, { props: { status: 'IN_PROGRESS' } })).text()).toBe('In Arbeit')
    expect((await mountSuspended(StatusBadge, { props: { status: 'DONE' } })).text()).toBe('Erledigt')
  })
})

describe('TaskCard', () => {
  const task = {
    id: '7',
    title: 'Hosting bestellen',
    icon: null,
    status: 'OPEN' as const,
    dueDate: null,
    commentCount: 0,
    assignee: null,
    todo: { title: 'Server und Domain vorbereiten' }
  }

  afterEach(() => {
    vi.useRealTimers()
  })

  async function mountCard() {
    vi.useFakeTimers()

    return mountSuspended(TaskCard, { props: { task, menuItems: [], editable: true } })
  }

  it('opens the details after a single click, once no second click follows', async () => {
    const card = await mountCard()
    await card.find('button').trigger('click', { detail: 1 })

    expect(card.emitted('open')).toBeUndefined()
    vi.advanceTimersByTime(300)
    expect(card.emitted('open')).toHaveLength(1)
  })

  it('opens only the edit dialog on a double-click, never the details', async () => {
    const card = await mountCard()
    const title = card.find('button')
    await title.trigger('click', { detail: 1 })
    await title.trigger('click', { detail: 2 })
    await card.find('article').trigger('dblclick')
    vi.advanceTimersByTime(300)

    expect(card.emitted('edit')).toHaveLength(1)
    expect(card.emitted('open')).toBeUndefined()
  })

  it('opens the details at once from the keyboard', async () => {
    const card = await mountCard()
    await card.find('button').trigger('click', { detail: 0 })

    expect(card.emitted('open')).toHaveLength(1)
  })
})
