import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import ConfirmModal from '~/components/ConfirmModal.vue'
import AdminUsersPage from '~/pages/admin/users.vue'
import { installElementInternals } from './support/element-internals'

const { reset, users } = vi.hoisted(() => ({
  reset: vi.fn(),
  users: [{ id: '2', name: 'Ben Müller', email: 'ben@example.ch', approved: true, createdAt: '2026-11-01T10:00:00Z', avatarUrl: null, twoFactorConfirmed: true, hasPasskey: false, role: { id: '3', key: 'mitglied', names: { de: 'Mitglied', en: 'Member' } } }]
}))

vi.mock('@vue/apollo-composable', () => ({
  useQuery: () => ({ result: ref({ users }), loading: ref(false), refetch: vi.fn() }),
  useMutation: (document: { definitions: { name?: { value: string } }[] }) => ({
    mutate: document.definitions[0]?.name?.value === 'ResetUserTwoFactor' ? reset : vi.fn()
  })
}))
mockNuxtImport('useViewer', () => () => ({ viewer: ref({ id: '1' }), load: vi.fn(), logout: vi.fn(), can: () => true, pendingEmail: ref(null) }))

// happy-dom has no form internals; the Web Awesome controls of the page need them.
installElementInternals()

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

beforeEach(() => {
  reset.mockReset().mockResolvedValue({ data: {} })
})

describe('admin: reset the second factor', () => {
  it('names the consequences and asks whether the request was checked by other means', async () => {
    const page = await mountSuspended(AdminUsersPage)

    ;(page.vm as unknown as { ask: (kind: string, user: unknown) => void }).ask('reset', users[0])
    await vi.waitFor(() => expect(page.findComponent(ConfirmModal).props('open')).toBe(true))

    const dialog = page.findComponent(ConfirmModal)
    expect(dialog.props('title')).toBe('2FA von Ben Müller zurücksetzen?')
    expect(dialog.props('description')).toContain('Authenticator-App und Passkeys von Ben Müller werden entfernt, alle Sitzungen beendet.')
    expect(dialog.props('description')).toContain('Ben Müller bekommt eine Mail und richtet bei der nächsten Anmeldung einen neuen Faktor ein.')
    expect(dialog.props('description')).toContain('Hast du auf anderem Weg geprüft, dass die Anfrage wirklich von Ben Müller kommt (Anruf, persönlich)?')
    expect(dialog.props('confirmLabel')).toBe('Ja, zurücksetzen')
  })

  it('resets only after the confirmation', async () => {
    const page = await mountSuspended(AdminUsersPage)
    ;(page.vm as unknown as { ask: (kind: string, user: unknown) => void }).ask('reset', users[0])
    await vi.waitFor(() => expect(page.findComponent(ConfirmModal).props('open')).toBe(true))

    expect(reset).not.toHaveBeenCalled()
    await page.findComponent(ConfirmModal).props('action')()

    expect(reset).toHaveBeenCalledWith({ id: '2' })
  })

  it('has the same texts in English', async () => {
    await useNuxtApp().$i18n.setLocale('en')
    const page = await mountSuspended(AdminUsersPage)
    ;(page.vm as unknown as { ask: (kind: string, user: unknown) => void }).ask('reset', users[0])
    await vi.waitFor(() => expect(page.findComponent(ConfirmModal).props('open')).toBe(true))

    expect(page.findComponent(ConfirmModal).props('description')).toContain('Have you checked by other means that the request really comes from Ben Müller')
    expect(page.findComponent(ConfirmModal).props('confirmLabel')).toBe('Yes, reset')
    await useNuxtApp().$i18n.setLocale('de')
  })
})
