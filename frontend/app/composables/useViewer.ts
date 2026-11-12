import type { ViewerQuery } from '~/graphql/generated/graphql'
import { viewerQuery } from '~/graphql/viewer'
import type { ApiErrorKind } from '~/utils/api'
import { classifyApolloError, readinessKind } from '~/utils/api'

export type Viewer = ViewerQuery['me']

/**
 * The logged-in user (null when not logged in or not yet loaded) and
 * login state shared by the whole app.
 */
export function useViewer() {
  const { $apollo, $backend } = useNuxtApp()
  const viewer = useState<Viewer | null>('viewer', () => null)
  const twoFactorEnabled = useState<boolean>('twoFactorEnabled', () => true)
  const systemAbilities = useState<string[]>('systemAbilities', () => [])

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
      systemAbilities.value = waiting ? [] : [...data.viewerSystemAbilities]

      return waiting
    } catch (error) {
      viewer.value = null
      systemAbilities.value = []

      return classifyApolloError(error).kind
    }
  }

  /** Ends the session and forgets all cached data. */
  async function logout(): Promise<void> {
    try {
      await $backend('/logout', { method: 'POST' })
    } finally {
      viewer.value = null
      systemAbilities.value = []
      await $apollo.clearStore()
      await navigateTo('/login')
    }
  }

  /** Whether the logged-in user has a system-wide ability, for example USERS_MANAGE. */
  function can(ability: string): boolean {
    return systemAbilities.value.includes(ability)
  }

  return { viewer, twoFactorEnabled, systemAbilities, can, load, logout }
}
