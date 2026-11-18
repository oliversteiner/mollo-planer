import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import AccountPendingPage from '~/pages/account-pending.vue'
import RegisterPage from '~/pages/register.vue'

const { backend, raw, load, logout, redirect, toast, memory } = await vi.hoisted(async () => {
  const { ref: createRef } = await import('vue')

  return {
    backend: vi.fn(),
    raw: vi.fn(),
    load: vi.fn(),
    logout: vi.fn(),
    redirect: vi.fn(),
    toast: vi.fn(),
    // A real ref, like the one of useState: the template unwraps it.
    memory: { pendingEmail: createRef<string | null>(null) }
  }
})

mockNuxtImport('useNuxtApp', original => () => {
  const nuxtApp = original()
  const $backend = Object.assign(backend, { raw })

  return new Proxy(nuxtApp, { get: (target, key) => (key === '$backend' ? $backend : Reflect.get(target, key)) })
})
mockNuxtImport('useViewer', () => () => ({ viewer: ref(null), load, logout, pendingEmail: memory.pendingEmail, can: () => false }))
mockNuxtImport('navigateTo', () => redirect)
mockNuxtImport('useToast', () => () => ({ add: toast }))

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

beforeEach(() => {
  for (const mock of [backend, raw, load, logout, redirect, toast]) {
    mock.mockReset()
  }
  memory.pendingEmail.value = null
})

type Page = Awaited<ReturnType<typeof mountSuspended>>
const text = (page: Page) => page.text().replace(/\s+/g, ' ')
const button = (page: Page, label: string) => page.findAll('wa-button').find((candidate: { text: () => string }) => candidate.text() === label)
const field = (page: Page, name: string) => page.findAll('wa-input').find((candidate: { element: Element }) => (candidate.element as unknown as { name: string }).name === name)

describe('page: confirm your address', () => {
  it('names the address, the deadline, the spam folder and the wait, and offers to send again', async () => {
    load.mockResolvedValue('unverified')
    memory.pendingEmail.value = 'eva@example.org'
    const page = await mountSuspended(AccountPendingPage, { route: '/account-pending?reason=email' })
    await vi.waitFor(() => expect(load).toHaveBeenCalled())

    expect(text(page)).toContain('Wir haben dir eine Mail an eva@example.org geschickt.')
    expect(text(page)).toContain('Ein Link in der Mail gilt 60 Minuten.')
    expect(text(page)).toContain('Sieh auch im Spam-Ordner nach.')
    expect(text(page)).toContain('Die Mail kann eine Minute brauchen.')
    expect(button(page, 'Bestätigungsmail erneut senden')).toBeDefined()
    expect(text(page)).toContain('Adresse falsch? Nochmals registrieren')
  })

  it('says the same without an address (after a reload without an answer of the API)', async () => {
    load.mockResolvedValue('unverified')
    const page = await mountSuspended(AccountPendingPage, { route: '/account-pending?reason=email' })
    await vi.waitFor(() => expect(load).toHaveBeenCalled())

    expect(text(page)).toContain('Wir haben dir eine Mail geschickt.')
    expect(text(page)).not.toContain('{email}')
  })

  it('stays without a session (a taken address does not log anybody in) and offers the form for the address', async () => {
    load.mockResolvedValue('unauthenticated')
    memory.pendingEmail.value = 'eva@example.org'
    const page = await mountSuspended(AccountPendingPage, { route: '/account-pending?reason=email' })
    await vi.waitFor(() => expect(load).toHaveBeenCalled())

    expect(redirect).not.toHaveBeenCalledWith('/login')
    expect(text(page)).toContain('Wir haben dir eine Mail an eva@example.org geschickt.')
    expect(field(page, 'email')).toBeDefined()
    expect((field(page, 'email')!.element as unknown as { value: string }).value).toBe('eva@example.org')
    expect(button(page, 'Erneut prüfen')).toBeUndefined()
    expect(button(page, 'Abmelden')).toBeUndefined()
  })

  it('says nothing that tells a new address from a taken one', async () => {
    load.mockResolvedValue('unauthenticated')
    memory.pendingEmail.value = 'eva@example.org'
    const page = await mountSuspended(AccountPendingPage, { route: '/account-pending?reason=email' })
    await vi.waitFor(() => expect(load).toHaveBeenCalled())

    expect(text(page)).not.toMatch(/vergeben|bereits|schon ein Konto|already/i)
  })

  it('"wrong address" ends the session and goes to the registration, and forgets the address', async () => {
    load.mockResolvedValue('unverified')
    memory.pendingEmail.value = 'eva@example.org'
    const page = await mountSuspended(AccountPendingPage, { route: '/account-pending?reason=email' })
    await vi.waitFor(() => expect(load).toHaveBeenCalled())

    await page.find('a[href="/register"]').trigger('click')

    expect(logout).toHaveBeenCalledWith('/register')
    expect(memory.pendingEmail.value).toBeNull()
  })

  it('"wrong address" without a session goes straight to the registration', async () => {
    load.mockResolvedValue('unauthenticated')
    const page = await mountSuspended(AccountPendingPage, { route: '/account-pending?reason=email' })
    await vi.waitFor(() => expect(load).toHaveBeenCalled())

    await page.find('a[href="/register"]').trigger('click')

    expect(logout).not.toHaveBeenCalled()
    expect(redirect).toHaveBeenCalledWith('/register')
  })
})

describe('page: release pending', () => {
  it('has its own title and says what happens next and that the person need not do anything', async () => {
    load.mockResolvedValue('awaiting-approval')
    const page = await mountSuspended(AccountPendingPage, { route: '/account-pending?reason=approval' })
    await vi.waitFor(() => expect(load).toHaveBeenCalled())

    expect(page.find('h1').text()).toBe('Freigabe ausstehend')
    expect(text(page)).toContain('Deine E-Mail-Adresse ist bestätigt.')
    expect(text(page)).toContain('Ein Admin schaltet dein Konto frei.')
    expect(text(page)).toContain('Du bekommst eine Mail')
    expect(text(page)).toContain('Du musst nichts tun.')
    expect(text(page)).not.toContain('Bestätigungsmail erneut senden')
  })

  it('goes to the login without a session', async () => {
    load.mockResolvedValue('unauthenticated')
    await mountSuspended(AccountPendingPage, { route: '/account-pending?reason=approval' })

    await vi.waitFor(() => expect(redirect).toHaveBeenCalledWith('/login'))
  })
})

describe('registration page', () => {
  it('asks for at least 12 characters', async () => {
    const page = await mountSuspended(RegisterPage)

    expect((field(page, 'password')!.element as unknown as { hint: string }).hint).toBe('Mindestens 12 Zeichen')
  })

  it('goes on to the page about the confirmation with the address in memory, not in the address bar', async () => {
    backend.mockResolvedValue('')
    const page = await mountSuspended(RegisterPage)
    for (const [name, value] of [['name', 'Eva Neu'], ['email', ' eva@example.org '], ['password', 'correct-horse-battery'], ['password_confirmation', 'correct-horse-battery']] as const) {
      const element = field(page, name)!.element as HTMLElement & { value: string }
      Object.defineProperty(element, 'value', { value, writable: true, configurable: true })
      element.dispatchEvent(new Event('input'))
    }

    await page.find('form').trigger('submit')
    await vi.waitFor(() => expect(redirect).toHaveBeenCalledWith('/account-pending?reason=email'))

    expect(memory.pendingEmail.value).toBe('eva@example.org')
  })
})
