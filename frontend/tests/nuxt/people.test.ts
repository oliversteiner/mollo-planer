import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import EmptyState from '~/components/EmptyState.vue'
import PageFrame from '~/components/PageFrame.vue'
import UserAvatar from '~/components/UserAvatar.vue'
import UserAvatarGroup from '~/components/UserAvatarGroup.vue'
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

describe('UserAvatarGroup', () => {
  const people = Array.from({ length: 7 }, (_, index) => ({ id: String(index), name: `Person ${index}`, avatarUrl: null }))

  it('shows up to the maximum and counts the rest', async () => {
    const group = await mountSuspended(UserAvatarGroup, { props: { people, max: 5 } })

    expect(group.findAll('wa-avatar')).toHaveLength(5)
    expect(group.text()).toBe('+2')
  })

  it('has no counter when everybody fits', async () => {
    const group = await mountSuspended(UserAvatarGroup, { props: { people: people.slice(0, 3), max: 5 } })

    expect(group.findAll('wa-avatar')).toHaveLength(3)
    expect(group.text()).toBe('')
  })
})

describe('PageFrame', () => {
  it('shows the title as heading and the actions only when given', async () => {
    const plain = await mountSuspended(PageFrame, { props: { title: 'Dashboard' }, slots: { default: '<p class="body">Inhalt</p>' } })

    expect(plain.find('h1').text()).toBe('Dashboard')
    expect(plain.find('.body').exists()).toBe(true)
    expect(plain.find('.page-actions').exists()).toBe(false)

    const withActions = await mountSuspended(PageFrame, { props: { title: 'Projekte' }, slots: { actions: '<button class="act">Neu</button>' } })

    expect(withActions.find('.page-actions .act').exists()).toBe(true)
  })
})
