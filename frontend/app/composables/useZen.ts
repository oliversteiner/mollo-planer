import { useQuery } from '@vue/apollo-composable'
import { sidebarProjectsQuery } from '~/graphql/projects'
import { chooseZenArea, cleanPath, isContentArea, projectIdOf, projectPageOf } from '~/utils/zen-area'
import { routeRight } from '~/utils/route-rights'

/**
 * Starting Zen (docs/zen-modus.md §4.4): the same for the hit "Zen · <area>" in the search and the button in the header.
 * The area is the page if it is a content area, else the one last visited on this device (cookie `zen_area`), else
 * "Meine Tasks". A remembered path the person may not see any more (right, project) is dropped.
 */
export function useZen() {
  const { t } = useI18n()
  const route = useRoute()
  const { can, viewer } = useViewer()
  const { setMode } = useViewMode()
  const remembered = useCookie<string | null>('zen_area', { maxAge: 60 * 60 * 24 * 365, sameSite: 'lax', path: '/', default: () => null })
  const { result } = useQuery(sidebarProjectsQuery, null, { fetchPolicy: 'cache-first', enabled: computed(() => viewer.value !== null) })
  const projects = computed(() => new Map((result.value?.projects ?? []).map(project => [project.id, project.name])))

  function allowed(path: string): boolean {
    const right = routeRight(path)
    const id = projectIdOf(path)

    return (right === null || can(right)) && (id === null || projects.value.has(id))
  }

  const area = computed(() => chooseZenArea(route.path, remembered.value, allowed))

  /** "Website-Relaunch, Kanban" or "Meine Tasks". */
  const areaLabel = computed(() => {
    const path = area.value
    const id = projectIdOf(path)

    if (id !== null) {
      const page = projectPageOf(path)

      return `${projects.value.get(id) ?? t('nav.projects')}, ${t(`project.tabs.${page === 'kanban' ? 'tasks' : page === 'todos' ? 'todos' : 'milestones'}`)}`
    }

    const keys: Record<string, string> = { '/': 'nav.dashboard', '/my-tasks': 'nav.myTasks', '/ideas': 'nav.ideas', '/assistant': 'nav.assistant' }

    return t(keys[path] ?? 'nav.myTasks')
  })

  /** Remembers the content area the person is in (called when the route changes). */
  function remember(): void {
    if (isContentArea(route.path) && remembered.value !== cleanPath(route.path)) {
      remembered.value = cleanPath(route.path)
    }
  }

  async function start(): Promise<void> {
    const target = area.value

    setMode('calm')

    if (cleanPath(route.path) !== target) {
      await navigateTo(target)
    }
  }

  return { area, areaLabel, remember, start }
}
