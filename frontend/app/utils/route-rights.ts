/**
 * The right that opens a page or a menu entry (RF5, docs/refactoring-m1-m3.md).
 * The global middleware and the menu both read this list, so they cannot
 * disagree. A path belongs to the longest prefix listed here. Paths not listed
 * need no right. The backend checks every action again.
 */
export const ROUTE_RIGHTS: Readonly<Record<string, string>> = {
  '/assistant': 'USERS_MANAGE',
  '/system/db': 'USERS_MANAGE',
  '/admin/users': 'USERS_MANAGE',
  '/admin/roles': 'ROLES_MANAGE',
  '/admin/settings': 'SETTINGS_MANAGE',
  '/label-sets': 'LABEL_SETS_MANAGE'
}

/** The system right that opens this path, or null when the path needs none. */
export function routeRight(path: string): string | null {
  const prefix = Object.keys(ROUTE_RIGHTS)
    .filter(candidate => path === candidate || path.startsWith(`${candidate}/`))
    .sort((a, b) => b.length - a.length)[0]

  return prefix === undefined ? null : ROUTE_RIGHTS[prefix] ?? null
}
