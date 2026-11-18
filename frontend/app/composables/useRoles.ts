import { useQuery } from '@vue/apollo-composable'
import type { RoleScope } from '~/graphql/generated/graphql'
import { rolesQuery } from '~/graphql/roles'

/** What every role from the API carries to be shown by name. */
export interface NamedRole {
  key: string
  names: { de: string, en: string }
}

/**
 * The name of a role in the language of the interface. Roles are data
 * (shipped ones and those made in the settings), so their names come from
 * the backend and not from the translation files.
 */
export function useRoleName() {
  const { locale } = useI18n()

  return (role: NamedRole): string => (locale.value === 'en' ? role.names.en : role.names.de)
}

/** The roles of one scope as items of a selection, and the default role. */
export function useRoles(scope: RoleScope) {
  const roleName = useRoleName()
  const { result } = useQuery(rolesQuery, { scope })

  const roles = computed(() => result.value?.roles ?? [])
  const items = computed(() => roles.value.map(role => ({ label: roleName(role), value: role.key })))
  const defaultKey = computed(() => roles.value.find(role => role.isDefault)?.key ?? '')

  return { roles, items, defaultKey }
}
