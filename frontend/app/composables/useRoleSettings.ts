import type { ApolloCache } from '@apollo/client/core'
import { roleSettingsQuery } from '~/graphql/roles'
import { classifyApolloError } from '~/utils/api'

/**
 * What every change of roles and functions does afterwards: read the
 * settings anew and forget the cached role lists and the rights table, so
 * selections elsewhere show the new names and rights.
 */
export const roleSettingsRefresh = {
  refetchQueries: [{ query: roleSettingsQuery }],
  awaitRefetchQueries: true,
  update(cache: ApolloCache<unknown>): void {
    cache.evict({ id: 'ROOT_QUERY', fieldName: 'roles' })
    cache.evict({ id: 'ROOT_QUERY', fieldName: 'permissionMatrix' })
    cache.gc()
  }
}

/**
 * The reason the server refused a change (a ground rule, a role in use),
 * for actions without a form. Other failures are reported by the api plugin.
 */
export function refusalMessage(error: unknown): string | null {
  const apiError = classifyApolloError(error)

  return apiError.kind === 'validation' ? (Object.values(apiError.fields)[0]?.[0] ?? null) : null
}
