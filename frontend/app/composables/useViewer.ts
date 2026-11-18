import type { ViewerQuery } from '~/graphql/generated/graphql'
import { viewerQuery } from '~/graphql/viewer'
import type { ApiErrorKind } from '~/utils/api'
import { classifyApolloError, readinessKind } from '~/utils/api'
import { clearAssistantStorage } from '~/utils/assistant-storage'

export type Viewer = ViewerQuery['me']

/**
 * The logged-in user (null when not logged in or not yet loaded) and
 * login state shared by the whole app.
 */
export function useViewer() {
  const { $apollo, $backend } = useNuxtApp()
  const viewer = useState<Viewer | null>('viewer', () => null)
  const twoFactorEnabled = useState<boolean>('twoFactorEnabled', () => true)
  const systemRights = useState<string[]>('systemRights', () => [])
  // The address a confirmation mail went to: from the answer of the API for an unconfirmed account (it knows
  // it after a reload), or from the registration form. Only in memory, never in the address bar.
  const pendingEmail = useState<string | null>('pendingEmail', () => null)

  /**
   * Asks the API who is logged in. Returns null on success, otherwise why
   * not (for example "unauthenticated", "setup-required" or "awaiting-approval").
   */
  async function load(): Promise<ApiErrorKind | null> {
    try {
      const { data } = await $apollo.query({
        query: viewerQuery,
        fetchPolicy: 'network-only',
        context: { silentErrors: true }
      })
      twoFactorEnabled.value = data.appSettings.twoFactorEnabled

      // A waiting account gets the viewer query too, but may not use the app.
      const waiting = readinessKind(data.me)

      viewer.value = waiting ? null : data.me
      systemRights.value = waiting ? [] : [...data.viewerRights]

      return waiting
    } catch (error) {
      viewer.value = null
      systemRights.value = []
      // The session is over: the chat of the assistant must not reach the next person.
      clearAssistantStorage()

      const apiError = classifyApolloError(error)

      if (apiError.kind === 'unverified' && apiError.email) {
        pendingEmail.value = apiError.email
      }

      return apiError.kind
    }
  }

  /** Ends the session and forgets all cached data, then goes to the given page (the login). */
  async function logout(to = '/login'): Promise<void> {
    try {
      await $backend('/logout', { method: 'POST' })
    } finally {
      clearAssistantStorage()
      viewer.value = null
      systemRights.value = []
      await $apollo.clearStore()
      await navigateTo(to)
    }
  }

  /** Whether the logged-in user has a system right, for example USERS_MANAGE. */
  function can(right: string): boolean {
    return systemRights.value.includes(right)
  }

  return { viewer, twoFactorEnabled, systemRights, pendingEmail, can, load, logout }
}
