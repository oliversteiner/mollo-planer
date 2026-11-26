import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import TabLabel from '~/components/TabLabel.vue'

describe('TabLabel', () => {
  it('shows the count as a pill, not in brackets', async () => {
    const wrapper = await mountSuspended(TabLabel, { props: { label: 'Kommentare', count: 4 } })
    const badge = wrapper.find('wa-badge')

    expect(badge.exists()).toBe(true)
    // Vue sets `pill` as a property of the element when it knows one, else as an attribute.
    expect((badge.element as HTMLElement & { pill?: boolean }).pill === true || badge.attributes('pill') !== undefined).toBe(true)
    expect(badge.text()).toBe('4')
    expect(wrapper.text()).not.toMatch(/\(\s*4\s*\)/)
  })

  it('reads "title, count" for screen readers', async () => {
    const wrapper = await mountSuspended(TabLabel, { props: { label: 'Archiv', count: 0 } })

    expect(wrapper.find('.wa-visually-hidden').text()).toBe(',')
    expect(wrapper.text().replace(/\s+/g, ' ').trim()).toBe('Archiv, 0')
  })
})
