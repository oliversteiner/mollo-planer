import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import AuthorizePage from '~/pages/oauth/authorize.vue'
import ConsentPage from '~/pages/oauth/consent.vue'

const { backend } = vi.hoisted(() => ({ backend: vi.fn() }))

mockNuxtImport('useNuxtApp', original => () => {
  const nuxtApp = original()

  return new Proxy(nuxtApp, { get: (target, key) => (key === '$backend' ? backend : Reflect.get(target, key)) })
})

// A person is logged in, so the global middleware lets the pages through.
mockNuxtImport('useViewer', () => () => ({ viewer: ref({ id: '1' }), load: vi.fn(), logout: vi.fn(), can: () => true, pendingEmail: ref(null) }))

const consent = {
  client: { name: 'Club DB', redirectHost: 'clubdb.example', selfRegistered: false },
  scopes: [{ id: 'openid', description: 'Deine Identität bestätigen' }, { id: 'email', description: 'Deine E-Mail-Adresse lesen' }],
  authToken: 'token-1',
  user: { name: 'Anna' }
}

const assign = vi.fn()
const replace = vi.fn()

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

beforeEach(() => {
  backend.mockReset()
  assign.mockReset()
  replace.mockReset()
  vi.stubGlobal('location', { href: 'http://localhost:3000/oauth/consent', origin: 'http://localhost:3000', pathname: '/oauth/consent', hash: '', assign, replace, search: '?client_id=abc&state=xyz' })
})

describe('consent page', () => {
  it('shows who asks, for what and where it goes on', async () => {
    backend.mockResolvedValue(consent)

    const page = await mountSuspended(ConsentPage)
    await vi.waitFor(() => expect(page.text()).toContain('Club DB möchte mit deinem Konto arbeiten (angemeldet als Anna).'))

    expect(page.text()).toContain('Deine Identität bestätigen')
    expect(page.text()).toContain('Deine E-Mail-Adresse lesen')
    expect(page.text()).toContain('Danach geht es weiter zu clubdb.example.')
    expect(backend).toHaveBeenCalledWith('/oauth/consent')
  })

  it('sends the token with the answer and goes on to the address the API names', async () => {
    backend.mockResolvedValueOnce(consent).mockResolvedValueOnce({ redirect: 'https://clubdb.example/callback?code=abc&state=xyz' })

    const page = await mountSuspended(ConsentPage)
    await vi.waitFor(() => expect(page.text()).toContain('Zulassen'))
    await page.findAll('wa-button')[0]!.trigger('click')

    await vi.waitFor(() => expect(assign).toHaveBeenCalledWith('https://clubdb.example/callback?code=abc&state=xyz'))
    expect(backend).toHaveBeenLastCalledWith('/oauth/consent/approve', { method: 'POST', body: { auth_token: 'token-1' } })
  })

  it('refuses through the deny route', async () => {
    backend.mockResolvedValueOnce(consent).mockResolvedValueOnce({ redirect: 'https://clubdb.example/callback?error=access_denied' })

    const page = await mountSuspended(ConsentPage)
    await vi.waitFor(() => expect(page.text()).toContain('Ablehnen'))
    await page.findAll('wa-button')[1]!.trigger('click')

    await vi.waitFor(() => expect(assign).toHaveBeenCalledWith('https://clubdb.example/callback?error=access_denied'))
    expect(backend).toHaveBeenLastCalledWith('/oauth/consent/deny', { method: 'POST', body: { auth_token: 'token-1' } })
  })

  it('says so when there is no request any more', async () => {
    backend.mockRejectedValue(new Error('404'))

    const page = await mountSuspended(ConsentPage)

    await vi.waitFor(() => expect(page.text()).toContain('Anfrage nicht mehr gültig'))
    expect(page.findAll('wa-button')).toHaveLength(0)
  })
})

describe('consent page: a client that registered itself', () => {
  it('says so, and where it goes on', async () => {
    backend.mockResolvedValue({ ...consent, client: { ...consent.client, name: 'Claude', selfRegistered: true } })

    const page = await mountSuspended(ConsentPage)

    await vi.waitFor(() => expect(page.text()).toContain('hat sich selbst registriert und wurde nicht von Mollo geprüft'))
    expect(page.text()).toContain('weiter zu clubdb.example')
  })

  it('does not say so for a client that an admin registered', async () => {
    backend.mockResolvedValue(consent)

    const page = await mountSuspended(ConsentPage)

    await vi.waitFor(() => expect(page.text()).toContain('Club DB möchte mit deinem Konto arbeiten'))
    expect(page.text()).not.toContain('selbst registriert')
  })
})

describe('consent page: an account that may not authorize', () => {
  /** The router needs the real address of the window: the stub of the other tests (for the navigation) is taken away. */
  const at = (): void => {
    vi.unstubAllGlobals()
  }
  const refusal = (reason: string) => Object.assign(new Error('403'), { status: 403, data: { error: 'account_not_allowed', reason } })

  it('says why when the API sent the person here with the reason', async () => {
    at()
    const page = await mountSuspended(ConsentPage, { route: '/oauth/consent?error=email_unverified' })

    await vi.waitFor(() => expect(page.text()).toContain('Bestätige zuerst deine E-Mail-Adresse.'))
    expect(page.text()).toContain('Zugriff nicht möglich')
    expect(page.findAll('wa-button')).toHaveLength(0)
    expect(backend).not.toHaveBeenCalled()
  })

  it('says why for each reason, and has a text for one it does not know', async () => {
    for (const [reason, text] of [['agent', 'Ein Agent-Konto kann keine Anwendung zulassen.'], ['not_approved', 'noch nicht von einem Admin freigegeben'], ['whatever', 'Mit diesem Konto kann keine Anwendung zugelassen werden.']]) {
      at()
      const page = await mountSuspended(ConsentPage, { route: `/oauth/consent?error=${reason}` })

      await vi.waitFor(() => expect(page.text()).toContain(text))
    }
  })

  it('shows the reason of a 403 when the page loads the request', async () => {
    backend.mockRejectedValue(refusal('not_approved'))

    const page = await mountSuspended(ConsentPage)

    await vi.waitFor(() => expect(page.text()).toContain('noch nicht von einem Admin freigegeben'))
    expect(page.findAll('wa-button')).toHaveLength(0)
  })

  it('shows the reason of a 403 on the answer, instead of the text for a request that is gone', async () => {
    backend.mockResolvedValueOnce(consent).mockRejectedValueOnce(refusal('email_unverified'))

    const page = await mountSuspended(ConsentPage)
    await vi.waitFor(() => expect(page.text()).toContain('Zulassen'))
    await page.findAll('wa-button')[0]!.trigger('click')

    await vi.waitFor(() => expect(page.text()).toContain('Bestätige zuerst deine E-Mail-Adresse.'))
    expect(assign).not.toHaveBeenCalled()
  })
})

describe('authorize page', () => {
  it('hands the request to the API again, with the same query', async () => {
    await mountSuspended(AuthorizePage)

    await vi.waitFor(() => expect(replace).toHaveBeenCalledOnce())
    expect(replace.mock.calls[0]![0]).toMatch(/\/oauth\/authorize\?client_id=abc&state=xyz$/)
  })
})
