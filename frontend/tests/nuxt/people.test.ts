import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import EmptyState from '~/components/EmptyState.vue'
import PageFrame from '~/components/PageFrame.vue'
import UserAvatar from '~/components/UserAvatar.vue'
import UserAvatarGroup from '~/components/UserAvatarGroup.vue'
import UserLine from '~/components/UserLine.vue'
import AgentBadge from '~/components/AgentBadge.vue'

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

describe('UserAvatar of an agent account (variant C)', () => {
  const sizes = { '2xs': '1.25rem', 'xs': '1.5rem', 'sm': '2rem', 'md': '2.5rem', '3xl': '4rem' } as const

  it.each(['sm', 'md', '3xl'] as const)('from 2 rem (%s): one initial and a robot mark, name "<name>, agent" (de: Agent)', async (size) => {
    const avatar = await mountSuspended(UserAvatar, { props: { name: 'Bob Lang', isAgent: true, size } })
    const inner = avatar.find('wa-avatar').element as Avatar

    expect(inner.initials).toBe('B')
    expect(inner.label).toMatch(/^Bob Lang, agent$/i)
    expect(avatar.find('.user-avatar-agent-mark').exists()).toBe(true)
    expect(avatar.find('.user-avatar-agent-mark').attributes('aria-hidden')).toBe('true')
    expect(avatar.attributes('style')).toContain(`--size: ${sizes[size]}`)
  })

  it.each(['2xs', 'xs'] as const)('below 2 rem (%s): only the robot icon, no initials, no mark', async (size) => {
    const avatar = await mountSuspended(UserAvatar, { props: { name: 'Bob Lang', isAgent: true, size } })
    const inner = avatar.find('wa-avatar').element as Avatar

    expect(inner.initials).toBeFalsy()
    expect(inner.label).toMatch(/^Bob Lang, agent$/i)
    const icon = avatar.find('wa-icon[slot="icon"]').element as HTMLElement & { name?: string }

    expect(icon.name ?? icon.getAttribute('name')).toBe('robot')
    expect(avatar.find('.user-avatar-agent-mark').exists()).toBe(false)
  })

  it.each(['2xs', 'xs', 'sm', 'md', '3xl'] as const)('a person (%s) has no mark and keeps the plain name and two initials', async (size) => {
    const avatar = await mountSuspended(UserAvatar, { props: { name: 'Bob Lang', isAgent: false, size } })

    expect(avatar.element.tagName).toBe('WA-AVATAR')
    expect((avatar.element as Avatar).label).toBe('Bob Lang')
    expect((avatar.element as Avatar).initials).toBe('BL')
    expect(avatar.find('.user-avatar-agent-mark').exists()).toBe(false)
  })

  it('passes isAgent on from UserLine and UserAvatarGroup', async () => {
    const line = await mountSuspended(UserLine, { props: { name: 'Bob Lang', isAgent: true, size: 'sm' } })
    const group = await mountSuspended(UserAvatarGroup, { props: { people: [{ id: '1', name: 'Bob Lang', isAgent: true }, { id: '2', name: 'Ada Admin' }], size: 'sm' } })

    expect(line.find('.user-avatar-agent-mark').exists()).toBe(true)
    expect(group.findAll('.user-avatar-agent-mark')).toHaveLength(1)
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
    expect(plain.find('.page-actions').text()).toBe('')
    expect(plain.find('wa-breadcrumb').exists()).toBe(false)

    const withActions = await mountSuspended(PageFrame, { props: { title: 'Projekte' }, slots: { actions: '<button class="act">Neu</button>' } })

    expect(withActions.find('.page-actions .act').exists()).toBe(true)
  })

  it('shows the breadcrumb with links up to the current page and the description', async () => {
    const frame = await mountSuspended(PageFrame, {
      props: { title: 'Website', description: 'Neue Website', breadcrumbs: [{ label: 'Projekte', to: '/projects' }, { label: 'Website', to: '/projects/1' }, { label: 'Tasks' }] }
    })
    const items = frame.findAll('wa-breadcrumb-item')

    expect(items.map(item => item.text())).toEqual(['Projekte', 'Website', 'Tasks'])
    expect(items.map(item => (item.element as unknown as { href?: string }).href)).toEqual(['/projects', '/projects/1', undefined])
    expect(frame.find('.page-description').text()).toBe('Neue Website')
    // Child pages put their buttons here with <Teleport to="#page-actions">.
    expect(frame.find('#page-actions').exists()).toBe(true)
  })
})

describe('AppLogo', () => {
  it('is an image named after the app, in the colour of the text', async () => {
    const { default: AppLogo } = await import('~/components/AppLogo.vue')
    const logo = await mountSuspended(AppLogo)

    expect(logo.attributes('role')).toBe('img')
    expect(logo.attributes('aria-label')).toBe(useNuxtApp().$i18n.t('app.name'))
    expect(logo.find('path').attributes('fill')).toBe('currentColor')
  })
})

describe('AgentBadge', () => {
  it('marks an agent account with its name and a hint', async () => {
    const badge = await mountSuspended(AgentBadge)

    expect(badge.element.tagName).toBe('WA-BADGE')
    expect(badge.text()).toBe('Agent')
    expect(badge.attributes('title')).toContain('token')
  })
})
