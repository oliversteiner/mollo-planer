import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import LoginPage from '~/pages/login.vue'

const { backend, load, redirect, toast, passkeys } = vi.hoisted(() => ({
  backend: vi.fn(),
  load: vi.fn(),
  redirect: vi.fn(),
  toast: vi.fn(),
  passkeys: { supported: true }
}))

mockNuxtImport('useNuxtApp', original => () => {
  const nuxtApp = original()

  return new Proxy(nuxtApp, { get: (target, key) => (key === '$backend' ? backend : Reflect.get(target, key)) })
})
mockNuxtImport('useViewer', () => () => ({ viewer: ref(null), load, logout: vi.fn(), can: () => false }))
mockNuxtImport('navigateTo', () => redirect)
mockNuxtImport('useToast', () => () => ({ add: toast }))
vi.mock('@simplewebauthn/browser', () => ({ browserSupportsWebAuthn: () => passkeys.supported }))

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

beforeEach(() => {
  for (const mock of [backend, load, redirect, toast]) {
    mock.mockReset()
  }
  passkeys.supported = true
})

type Field = HTMLElement & { value: string, name: string }

async function submitPassword(page: Awaited<ReturnType<typeof mountSuspended>>) {
  for (const [name, value] of [['email', 'ada@example.org'], ['password', 'geheim-geheim']] as const) {
    const element = page.findAll('wa-input').find((candidate: { element: Element }) => (candidate.element as Field).name === name)!.element as Field
    Object.defineProperty(element, 'value', { value, writable: true, configurable: true })
    element.dispatchEvent(new Event('input'))
  }
  await page.find('form').trigger('submit')
}

describe('login page: what it does with the answer of the viewer check', () => {
  it('stays quiet when it is only opened with a half-finished session', async () => {
    for (const problem of ['reauth-required', 'unauthenticated', 'network']) {
      load.mockResolvedValue(problem)
      const page = await mountSuspended(LoginPage)
      await vi.waitFor(() => expect(load).toHaveBeenCalled())

      expect(page.text()).not.toContain('Passkey geschützt')
      expect(toast).not.toHaveBeenCalled()
      expect(redirect).not.toHaveBeenCalled()
      load.mockClear()
    }
  })

  it('goes on into the app when it is opened with a good session', async () => {
    load.mockResolvedValue(null)
    await mountSuspended(LoginPage, { route: '/login?redirect=/projects/2' })

    await vi.waitFor(() => expect(redirect).toHaveBeenCalledWith('/projects/2'))
  })

  it('after the password, an account with a passkey is told to use it, at the form and not as a toast', async () => {
    load.mockResolvedValueOnce('unauthenticated').mockResolvedValue('reauth-required')
    backend.mockResolvedValue({})
    const page = await mountSuspended(LoginPage)

    await submitPassword(page)
    await vi.waitFor(() => expect(page.text()).toContain('Dein Konto ist mit einem Passkey geschützt. Melde dich mit dem Passkey an.'))

    expect(toast).not.toHaveBeenCalled()
    const buttons = page.findAll('wa-button')
    const submit = buttons.find((button: { text: () => string }) => button.text() === 'Anmelden')!.element as unknown as { variant: string, appearance: string }
    const passkey = buttons.find((button: { text: () => string }) => button.text() === 'Mit Passkey anmelden')!.element as unknown as { variant: string, appearance: string }
    expect(passkey.variant).toBe('brand')
    expect(submit.variant).toBe('neutral')
    expect(submit.appearance).toBe('outlined')
  })

  it('without passkey support the browser is told to use another device or an admin', async () => {
    passkeys.supported = false
    load.mockResolvedValueOnce('unauthenticated').mockResolvedValue('reauth-required')
    backend.mockResolvedValue({})
    const page = await mountSuspended(LoginPage)

    await submitPassword(page)
    await vi.waitFor(() => expect(page.text()).toContain('Öffne die Seite auf einem Gerät mit deinem Passkey oder melde dich bei einem Admin.'))

    expect(page.findAll('wa-button').some((button: { text: () => string }) => button.text() === 'Mit Passkey anmelden')).toBe(false)
  })

  it('after the password, a failure of the check is said, and the page stays', async () => {
    load.mockResolvedValueOnce('unauthenticated').mockResolvedValue('network')
    backend.mockResolvedValue({})
    const page = await mountSuspended(LoginPage)

    await submitPassword(page)
    await vi.waitFor(() => expect(toast).toHaveBeenCalledWith({ title: 'Der Server ist nicht erreichbar.', color: 'error' }))

    expect(redirect).not.toHaveBeenCalled()
  })

  it('leads to the setup and to the page of a waiting account, like before', async () => {
    load.mockResolvedValue('setup-required')
    await mountSuspended(LoginPage)
    await vi.waitFor(() => expect(redirect).toHaveBeenCalledWith('/setup-security'))

    redirect.mockReset()
    load.mockResolvedValue('unverified')
    await mountSuspended(LoginPage)
    await vi.waitFor(() => expect(redirect).toHaveBeenCalledWith('/account-pending?reason=email'))
  })
})
