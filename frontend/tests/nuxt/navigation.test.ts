import { mountSuspended } from '@nuxt/test-utils/runtime'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import ColorModeSwitch from '~/components/ColorModeSwitch.vue'
import FormSelect from '~/components/form/Select.vue'
import LocaleSwitch from '~/components/LocaleSwitch.vue'
import NavList from '~/components/NavList.vue'

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

/** The tests are not signed in: the global middleware leaves them on the public login page. */
const items = [
  { label: 'Dashboard', icon: 'gauge-high', to: '/', exact: true },
  { label: 'Anmeldung', icon: 'right-to-bracket', to: '/login' }
]

describe('NavList', () => {
  it('lists the links with icon and name and marks the current page', async () => {
    const nav = await mountSuspended(NavList, { route: '/login', props: { items, label: 'Hauptnavigation' } })

    expect(nav.attributes('aria-label')).toBe('Hauptnavigation')
    expect(nav.findAll('a').map(link => link.text())).toEqual(['Dashboard', 'Anmeldung'])
    expect(nav.findAll('a wa-icon')).toHaveLength(2)

    const [dashboard, login] = nav.findAll('a')

    expect(login!.attributes('aria-current')).toBe('page')
    expect(login!.classes()).toContain('nav-link-active')
    expect(dashboard!.attributes('aria-current')).toBeUndefined()
    expect(dashboard!.classes()).not.toContain('nav-link-active')
  })

  it('counts sub pages as the current page, except for an exact link', async () => {
    const nav = await mountSuspended(NavList, {
      route: '/login',
      props: { items: [{ label: 'Alles', icon: 'folder', to: '/', exact: true }, { label: 'Hoch', icon: 'folder', to: '/login' }, { label: 'Andere', icon: 'folder', to: '/log' }] }
    })

    // "/" is exact, so it is not the page "/login"; "/log" is a different path, not a parent of "/login".
    expect(nav.findAll('a').map(link => link.attributes('aria-current'))).toEqual([undefined, 'page', undefined])
  })
})

describe('FormSelect with search', () => {
  it('becomes a combobox for long lists', async () => {
    const list = [{ label: 'A', value: 'a' }, { label: 'B', value: 'b' }]
    const plain = await mountSuspended(FormSelect, { props: { modelValue: 'a', items: list } })
    const searchable = await mountSuspended(FormSelect, { props: { modelValue: 'a', items: list, searchable: true } })

    expect(plain.element.tagName).toBe('WA-SELECT')
    expect(searchable.element.tagName).toBe('WA-COMBOBOX')
    expect(searchable.findAll('wa-option')).toHaveLength(2)
  })
})

describe('LocaleSwitch', () => {
  it('offers both languages and switches the interface language', async () => {
    const switcher = await mountSuspended(LocaleSwitch)

    expect(switcher.findAll('wa-option').map(option => option.text())).toEqual(['Deutsch', 'English'])

    const select = switcher.element as unknown as { value: string }
    select.value = 'en'
    switcher.element.dispatchEvent(new Event('change'))
    await vi.waitFor(() => expect(useNuxtApp().$i18n.locale.value).toBe('en'))

    await useNuxtApp().$i18n.setLocale('de')
  })
})

describe('ColorModeSwitch', () => {
  it('toggles between light and dark', async () => {
    const colorMode = useColorMode()
    colorMode.preference = 'light'
    const button = await mountSuspended(ColorModeSwitch)

    await button.trigger('click')

    expect(colorMode.preference).toBe('dark')

    await button.trigger('click')

    expect(colorMode.preference).toBe('light')
  })
})

describe('NavList on a sub page', () => {
  it('marks the link of the page above, but not an exact one', async () => {
    const nav = await mountSuspended(NavList, {
      route: '/login/anything',
      props: { items: [{ label: 'Alles', icon: 'folder', to: '/', exact: true }, { label: 'Anmeldung', icon: 'folder', to: '/login' }] }
    })

    expect(nav.findAll('a').map(link => link.attributes('aria-current'))).toEqual([undefined, 'page'])
  })
})
