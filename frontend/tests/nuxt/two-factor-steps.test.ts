import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import TwoFactorSetup from '~/components/security/TwoFactorSetup.vue'
import SetupSecurityPage from '~/pages/setup-security.vue'
import LoginPage from '~/pages/login.vue'

const { security, backend } = vi.hoisted(() => ({
  security: { startTwoFactor: vi.fn(), confirmTwoFactor: vi.fn(), recoveryCodes: vi.fn(), addPasskey: vi.fn() },
  backend: vi.fn()
}))

mockNuxtImport('useSecurity', () => () => security)
mockNuxtImport('useViewer', () => () => ({ viewer: ref({ id: '1' }), load: vi.fn().mockResolvedValue('unauthenticated'), logout: vi.fn(), can: () => true, pendingEmail: ref(null) }))
mockNuxtImport('useNuxtApp', original => () => {
  const nuxtApp = original()

  return new Proxy(nuxtApp, { get: (target, key) => (key === '$backend' ? backend : Reflect.get(target, key)) })
})
vi.mock('@simplewebauthn/browser', () => ({ browserSupportsWebAuthn: () => true }))

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

beforeEach(() => {
  security.startTwoFactor.mockReset().mockResolvedValue({ qrCode: 'data:image/png;base64,', secretKey: 'ABCDEF' })
  security.confirmTwoFactor.mockReset().mockResolvedValue(undefined)
  security.recoveryCodes.mockReset().mockResolvedValue(['aaaa-1111', 'bbbb-2222'])
})

type Page = Awaited<ReturnType<typeof mountSuspended>>
const button = (page: Page, label: string) => page.findAll('wa-button').find((candidate: { text: () => string }) => candidate.text() === label)!

describe('recovery codes step', () => {
  async function reachCodes() {
    const page = await mountSuspended(TwoFactorSetup)
    await page.find('wa-button').trigger('click')
    await vi.waitFor(() => expect(page.find('form').exists()).toBe(true))
    await page.find('form').trigger('submit')
    await vi.waitFor(() => expect(page.text()).toContain('aaaa-1111'))

    return page
  }

  it('keeps "Fertig" closed until the person says the codes are saved', async () => {
    const page = await reachCodes()
    const done = () => button(page, 'Fertig').element as unknown as { disabled: boolean }

    expect(page.text()).toContain('Ich habe die Codes gespeichert.')
    expect(done().disabled).toBe(true)

    await page.find('wa-checkbox').trigger('change')
    Object.defineProperty(page.find('wa-checkbox').element, 'checked', { value: true, configurable: true })
    await page.find('wa-checkbox').trigger('change')

    expect(done().disabled).toBe(false)
    await button(page, 'Fertig').trigger('click')
    expect(page.emitted('done')).toHaveLength(1)
  })

  it('says in which step it is', async () => {
    const page = await mountSuspended(TwoFactorSetup)
    await page.find('wa-button').trigger('click')
    await vi.waitFor(() => expect(page.emitted('step')?.at(-1)).toEqual(['scan']))
    await page.find('form').trigger('submit')
    await vi.waitFor(() => expect(page.emitted('step')?.at(-1)).toEqual(['codes']))
  })
})

describe('setup page: progress as text', () => {
  it('names the step of the passkey way and of the app way', async () => {
    const page = await mountSuspended(SetupSecurityPage, { route: '/setup-security' })
    expect(page.text()).not.toContain('Schritt')

    await page.find('.setup-choice').trigger('click')
    expect(page.text()).toContain('Schritt 1 von 2: Faktor einrichten')

    await page.findComponent({ name: 'SecurityPasskeyAdd' }).vm.$emit('added')
    expect(page.text()).toContain('Schritt 2 von 2: Abschluss')

    await button(page, 'Authenticator-App einrichten').trigger('click')
    expect(page.text()).toContain('Schritt 1 von 2: Faktor einrichten')
    await page.findComponent(TwoFactorSetup).vm.$emit('step', 'codes')
    expect(page.text()).toContain('Schritt 2 von 2: Codes sichern')
  })
})

describe('second factor page', () => {
  it('points to an admin for those who lost the app and the codes', async () => {
    backend.mockResolvedValue({ two_factor: true })
    const page = await mountSuspended(LoginPage)
    for (const [name, value] of [['email', 'ada@example.org'], ['password', 'geheim-geheim']] as const) {
      const element = page.findAll('wa-input').find((candidate: { element: Element }) => (candidate.element as unknown as { name: string }).name === name)!.element as HTMLElement & { value: string }
      Object.defineProperty(element, 'value', { value, writable: true, configurable: true })
      element.dispatchEvent(new Event('input'))
    }

    await page.find('form').trigger('submit')
    await vi.waitFor(() => expect(page.text()).toContain('Zweiter Faktor'))

    expect(page.text()).toContain('Kein Zugriff mehr auf App und Codes? Melde dich bei einem Admin.')
  })
})
