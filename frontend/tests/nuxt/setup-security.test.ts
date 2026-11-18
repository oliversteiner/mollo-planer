import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import SecurityPasskeyAdd from '~/components/security/PasskeyAdd.vue'
import SetupSecurityPage from '~/pages/setup-security.vue'

const { load, redirect, add } = vi.hoisted(() => ({ load: vi.fn(), redirect: vi.fn(), add: vi.fn() }))

// A viewer is there, so the route middleware lets the page through; `load` is what the page asks.
mockNuxtImport('useViewer', () => () => ({ viewer: ref({ id: '1' }), load, logout: vi.fn(), can: () => true }))
mockNuxtImport('navigateTo', () => redirect)
mockNuxtImport('useToast', () => () => ({ add }))

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

beforeEach(() => {
  load.mockReset()
  redirect.mockReset()
  add.mockReset()
})

/** Opens the passkey step and lets the child say the passkey was added. */
async function setUp(redirectTo?: string) {
  const page = await mountSuspended(SetupSecurityPage, { route: redirectTo ? { path: '/setup-security', query: { redirect: redirectTo } } : '/setup-security' })

  await page.find('.setup-choice').trigger('click')
  await page.findComponent(SecurityPasskeyAdd).vm.$emit('added')
  await vi.waitFor(() => expect(load).toHaveBeenCalled())
  await vi.waitFor(() => expect(redirect.mock.calls.length + add.mock.calls.length).toBeGreaterThan(0))

  return page
}

describe('setup security page, after the first factor', () => {
  it('goes on to the app (or the page the person came from) when the API opens', async () => {
    load.mockResolvedValue(null)

    await setUp('/projects/3')

    expect(redirect).toHaveBeenCalledWith('/projects/3')
    expect(add).not.toHaveBeenCalled()
  })

  it('goes on to the home page when there is no page to return to', async () => {
    load.mockResolvedValue(null)

    await setUp()

    expect(redirect).toHaveBeenCalledWith('/')
  })

  it('leads a waiting account to its page', async () => {
    load.mockResolvedValue('awaiting-approval')

    await setUp()

    expect(redirect).toHaveBeenCalledWith('/account-pending?reason=approval')
  })

  it('asks to sign in again when the session does not count as strong, and keeps the page to return to', async () => {
    load.mockResolvedValue('reauth-required')

    await setUp('/projects/3')

    expect(add).toHaveBeenCalledWith({ title: 'Dein Faktor ist eingerichtet. Melde dich jetzt damit an.', color: 'info' })
    expect(redirect).toHaveBeenCalledWith({ path: '/login', query: { redirect: '/projects/3' } })
  })

  it('does the same when the session is gone', async () => {
    load.mockResolvedValue('unauthenticated')

    await setUp()

    expect(redirect).toHaveBeenCalledWith({ path: '/login', query: { redirect: '/' } })
  })

  it('never sends the return address of another site on to the login page', async () => {
    load.mockResolvedValue('reauth-required')

    await setUp('https://evil.example/steal')

    expect(redirect).toHaveBeenCalledWith({ path: '/login', query: { redirect: '/' } })
  })

  it('stays and says what went wrong when the server cannot be reached', async () => {
    load.mockResolvedValue('network')

    await setUp()

    expect(add).toHaveBeenCalledWith({ title: 'Der Server ist nicht erreichbar.', color: 'error' })
    expect(redirect).not.toHaveBeenCalled()
  })

  it('stays and says so for the other failures', async () => {
    load.mockResolvedValue('unknown')
    await setUp()
    expect(add).toHaveBeenCalledWith({ title: 'Die Anfrage ist fehlgeschlagen.', color: 'error' })

    add.mockReset()
    load.mockResolvedValue('csrf')
    await setUp()
    expect(add).toHaveBeenCalledWith({ title: 'Die Sitzung war abgelaufen. Bitte versuche es nochmals.', color: 'warning' })
    expect(redirect).not.toHaveBeenCalled()
  })

  it('stays on the page without a message while the setup is still open', async () => {
    load.mockResolvedValue('setup-required')
    const page = await mountSuspended(SetupSecurityPage)

    await page.find('.setup-choice').trigger('click')
    await page.findComponent(SecurityPasskeyAdd).vm.$emit('added')
    await vi.waitFor(() => expect(load).toHaveBeenCalled())

    expect(redirect).not.toHaveBeenCalled()
    expect(add).not.toHaveBeenCalled()
  })
})
