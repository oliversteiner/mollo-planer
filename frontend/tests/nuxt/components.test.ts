import { mountSuspended } from '@nuxt/test-utils/runtime'
import { beforeAll, describe, expect, it } from 'vitest'
import DueDate from '~/components/DueDate.vue'
import ItemIcon from '~/components/ItemIcon.vue'
import StatusBadge from '~/components/StatusBadge.vue'

// The test browser reports English; the texts below are checked in German.
beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

describe('ItemIcon', () => {
  it('loads the chosen icon and hides it from screen readers without a label', async () => {
    const icon = await mountSuspended(ItemIcon, { props: { name: 'rocket', fallback: 'flag-checkered' } })

    expect(icon.attributes('style')).toContain('icons/duotone-light/rocket.svg')
    expect(icon.attributes('aria-hidden')).toBe('true')
  })

  it('uses the fallback for no name and for names outside the server format', async () => {
    for (const name of [null, '', '../../secret', 'Rocket', 'rocket.svg']) {
      const icon = await mountSuspended(ItemIcon, { props: { name, fallback: 'flag-checkered' } })

      expect(icon.attributes('style')).toContain('icons/duotone-light/flag-checkered.svg')
    }
  })

  it('is an image with a name when it has a label', async () => {
    const icon = await mountSuspended(ItemIcon, { props: { name: 'rocket', fallback: 'flag-checkered', label: 'Rakete' } })

    expect(icon.attributes('role')).toBe('img')
    expect(icon.attributes('aria-label')).toBe('Rakete')
  })
})

describe('DueDate', () => {
  it('marks a past date as overdue, in red and for screen readers', async () => {
    const date = await mountSuspended(DueDate, { props: { date: '2020-01-31' } })

    expect(date.classes()).toContain('text-error')
    expect(date.text()).toContain('31.01.2020')
    expect(date.text()).toContain('überfällig')
  })

  it('does not mark done items or future dates, and shows nothing without a date', async () => {
    expect((await mountSuspended(DueDate, { props: { date: '2020-01-31', done: true } })).classes()).not.toContain('text-error')
    expect((await mountSuspended(DueDate, { props: { date: '2999-12-31' } })).classes()).not.toContain('text-error')
    expect((await mountSuspended(DueDate, { props: { date: null } })).text()).toBe('')
  })
})

describe('StatusBadge', () => {
  it('shows the translated status', async () => {
    expect((await mountSuspended(StatusBadge, { props: { status: 'IN_PROGRESS' } })).text()).toBe('In Arbeit')
    expect((await mountSuspended(StatusBadge, { props: { status: 'DONE' } })).text()).toBe('Erledigt')
  })
})
