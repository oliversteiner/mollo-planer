import type { ViewerQuery } from '~/graphql/generated/graphql'
import { viewerQuery } from '~/graphql/viewer'
import type { ApiErrorKind } from '~/utils/api'
import { classifyApolloError } from '~/utils/api'

export type Viewer = ViewerQuery['me']

/**
 * The logged-in user (null when not logged in or not yet loaded) and
 * login state shared by the whole app.
 */
export function useViewer() {
  const { $apollo, $backend } = useNuxtApp()
  const viewer = useState<Viewer | null>('viewer', () => null)
  const twoFactorEnabled = useState<boolean>('twoFactorEnabled', () => true)

  /**
   * Asks the API who is logged in. Returns null on success, otherwise why
   * not (for example "unauthenticated" or "setup-required").
   */
  async function load(): Promise<ApiErrorKind | null> {
    try {
      const { data } = await $apollo.query({
        query: viewerQuery,
        fetchPolicy: 'network-only',
        context: { silentErrors: true }
      })
      viewer.value = data.me
      twoFactorEnabled.value = data.appSettings.twoFactorEnabled

      return null
    } catch (error) {
      viewer.value = null

      return classifyApolloError(error as Parameters<typeof classifyApolloError>[0]).kind
    }
  }

  /** Ends the session and forgets all cached data. */
  async function logout(): Promise<void> {
    try {
      await $backend('/logout', { method: 'POST' })
    } finally {
      viewer.value = null
      await $apollo.clearStore()
      await navigateTo('/login')
    }
  }

  return { viewer, twoFactorEnabled, load, logout }
}
