import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import EmptyState from '~/components/EmptyState.vue'
import UserAvatar from '~/components/UserAvatar.vue'
import UserLine from '~/components/UserLine.vue'

type Avatar = HTMLElement & { image: string, initials: string, label: string }

describe('UserAvatar', () => {
  it('shows the picture with the name as accessible name', async () => {
    const avatar = await mountSuspended(UserAvatar, { props: { name: 'Lindita Aliu', src: 'https://example.org/a.png' } })
    const element = avatar.element as Avatar

    expect(element.tagName).toBe('WA-AVATAR')
    expect(element.image).toBe('https://example.org/a.png')
    expect(element.initials).toBe('LA')
    expect(element.label).toBe('Lindita Aliu')
  })

  it('has the initials and a person icon without a picture, and sets the size', async () => {
    const avatar = await mountSuspended(UserAvatar, { props: { name: 'Lindita Aliu', src: null, size: 'md' } })

    expect((avatar.element as Avatar).image).toBeFalsy()
    expect((avatar.element as Avatar).initials).toBe('LA')
    expect(avatar.find('wa-icon[slot="icon"]').exists()).toBe(true)
    expect(avatar.attributes('style')).toContain('--size: 2.5rem')
  })
})

describe('UserLine', () => {
  it('shows name and description', async () => {
    const line = await mountSuspended(UserLine, { props: { name: 'Lindita Aliu', description: 'Projektleitung' } })

    expect(line.text()).toContain('Lindita Aliu')
    expect(line.text()).toContain('Projektleitung')
    expect(line.find('wa-avatar').exists()).toBe(true)
  })
})

describe('EmptyState', () => {
  it('shows icon, title, text and actions', async () => {
    const empty = await mountSuspended(EmptyState, {
      props: { icon: 'flag-checkered', title: 'Noch keine Milestones', description: 'Lege den ersten an.' },
      slots: { default: '<button class="act">Anlegen</button>' }
    })

    expect(empty.find('wa-icon').exists()).toBe(true)
    expect(empty.text()).toContain('Noch keine Milestones')
    expect(empty.text()).toContain('Lege den ersten an.')
    expect(empty.find('.act').exists()).toBe(true)
  })

  it('can be small and without frame', async () => {
    const empty = await mountSuspended(EmptyState, { props: { description: 'Nichts da.', size: 'sm', bare: true } })

    expect(empty.attributes('data-size')).toBe('sm')
    expect(empty.attributes('data-bare')).toBe('')
    expect(empty.find('wa-icon').exists()).toBe(false)
  })
})
