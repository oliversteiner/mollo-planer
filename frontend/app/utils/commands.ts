import { routeRight } from './route-rights'
import { matchesSearch } from './text-search'

/**
 * One entry of the command palette (docs/zen-modus.md §4.4). `right` is a system right (viewerRights, for example
 * USERS_MANAGE); an entry without it is for everybody. `visible` is for what only the page knows (a right in the
 * project, an archived project): the page gives a function that is true when the entry may show.
 */
export interface Command {
  id: string
  label: string
  icon?: string
  right?: string | null
  visible?: () => boolean
  /** Words besides the label that find the entry */
  keywords?: readonly string[]
  group: 'action' | 'page' | 'project'
  run: () => void | Promise<void>
}

/** Without the right the entry is not there, also not greyed out. */
export function visibleCommands(commands: readonly Command[], can: (right: string) => boolean): Command[] {
  return commands.filter(command => (command.right == null || can(command.right)) && (command.visible?.() ?? true))
}

export function filterCommands(commands: readonly Command[], query: string): Command[] {
  return commands.filter(command => matchesSearch(query, [command.label, ...(command.keywords ?? [])]))
}

interface PageEntry {
  id: string
  to: string
  label: string
  icon: string
}

/**
 * The pages of the app (the menu, docs/refactoring-m1-m3.md RF5): the right of each comes from route-rights.ts, so
 * the palette and the menu cannot disagree.
 */
export function pageCommands(t: (key: string) => string, go: (to: string) => void | Promise<void>): Command[] {
  const pages: PageEntry[] = [
    { id: 'page-dashboard', to: '/', label: t('nav.dashboard'), icon: 'gauge-high' },
    { id: 'page-my-tasks', to: '/my-tasks', label: t('nav.myTasks'), icon: 'square-check' },
    { id: 'page-assistant', to: '/assistant', label: t('nav.assistant'), icon: 'wand-magic-sparkles' },
    { id: 'page-ideas', to: '/ideas', label: t('nav.ideas'), icon: 'lightbulb' },
    { id: 'page-projects', to: '/projects', label: t('nav.projects'), icon: 'folder' },
    { id: 'page-trash', to: '/trash', label: t('nav.trash'), icon: 'trash-can' },
    { id: 'page-profile', to: '/profile', label: t('profile.title'), icon: 'user-gear' },
    { id: 'page-templates', to: '/templates', label: t('nav.templates'), icon: 'copy' },
    { id: 'page-database', to: '/system/db', label: t('nav.database'), icon: 'database' },
    { id: 'page-status', to: '/system/status', label: t('nav.status'), icon: 'heart-pulse' },
    { id: 'page-version', to: '/system/version', label: t('nav.version'), icon: 'code-branch' },
    { id: 'page-users', to: '/admin/users', label: t('nav.users'), icon: 'users' },
    { id: 'page-roles', to: '/admin/roles', label: t('nav.roles'), icon: 'user-shield' },
    { id: 'page-settings', to: '/admin/settings', label: t('nav.appSettings'), icon: 'sliders' }
  ]

  return pages.map(page => ({
    id: page.id,
    label: page.label,
    icon: page.icon,
    right: routeRight(page.to),
    group: 'page' as const,
    run: () => go(page.to)
  }))
}
