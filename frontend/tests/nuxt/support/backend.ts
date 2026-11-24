import type { Mock } from 'vitest'

/**
 * Puts a stand-in in place of `$backend` (the REST client of the app) for the tests of the password confirmation
 * (usePasswordStep): `useNuxtApp().$backend` is read when the composable runs, so it must be set before the mount.
 */
export function stubBackend(backend: Mock): void {
  const nuxtApp = useNuxtApp() as unknown as Record<string, unknown>

  nuxtApp.$backend = backend
}
