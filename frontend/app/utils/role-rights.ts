/**
 * Which roles a selection offers, decided by the rights of the roles
 * (Role.rights), never by their names (docs/fix-m1-abnahme.md F3.2, F3.9).
 */

interface RoleWithRights {
  rights: readonly string[]
}

/** Rights that make a project role an ownership role (§7.4). */
const OWNERSHIP_RIGHTS: readonly string[] = ['PROJECT_DELETE', 'OWNER_MANAGE']

/** Does the role carry project.delete or owner.manage? */
export function isOwnershipRole(role: RoleWithRights): boolean {
  return role.rights.some(right => OWNERSHIP_RIGHTS.includes(right))
}

/** The roles that may delete the project: the only ones a creator may get (§7.3). */
export function creatorRoles<TRole extends RoleWithRights>(roles: readonly TRole[]): TRole[] {
  return roles.filter(role => role.rights.includes('PROJECT_DELETE'))
}

/**
 * The project roles somebody may hand out: ownership roles only with
 * owner.manage (§7.4). A member's current role is always kept, so the
 * selection can show it.
 */
export function assignableRoles<TRole extends RoleWithRights & { key: string }>(
  roles: readonly TRole[],
  viewerRights: readonly string[],
  currentKey?: string | null
): TRole[] {
  const mayHandOwnership = viewerRights.includes('OWNER_MANAGE')

  return roles.filter(role => mayHandOwnership || !isOwnershipRole(role) || role.key === currentKey)
}
