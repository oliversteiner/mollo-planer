import { useApolloClient } from '@vue/apollo-composable'
import { projectHeaderQuery, projectRevisionQuery } from '~/graphql/projects'
import { isForeignChange } from '~/utils/planer-revision'

/** How often timeline and kanban ask for the revision. */
const POLL_INTERVAL_MS = 30_000

/**
 * The revision of the planer file this tab has loaded, per project (S3f).
 * Own changes move it on; a newer revision from the server means someone
 * else changed the project: a hint, and the project is loaded again.
 */
export function useProjectRevision() {
  const route = useRoute()
  const projectId = computed(() => (typeof route.params.id === 'string' ? route.params.id : null))
  const known = useState<Record<string, number>>('planer-revisions', () => ({}))
  /** Own changes on their way: the polling waits, so it does not take them for someone else's. */
  const ownChangesRunning = useState<number>('planer-own-changes', () => 0)
  const { resolveClient } = useApolloClient()
  const refreshProject = useProjectRefresh()
  const toast = useToast()
  const { t } = useI18n()

  function knownRevision(): number | null {
    return projectId.value === null ? null : known.value[projectId.value] ?? null
  }

  function remember(revision: number | null): void {
    if (projectId.value !== null && revision !== null) {
      known.value = { ...known.value, [projectId.value]: revision }
    }
  }

  /** The revision in the project header as the cache has it. */
  function cachedRevision(): number | null {
    if (projectId.value === null) {
      return null
    }

    return resolveClient().readQuery({ query: projectHeaderQuery, variables: { id: projectId.value } })?.project?.planerRevision ?? null
  }

  function showChangedElsewhere(): void {
    toast.add({ title: t('project.changedElsewhere'), color: 'info' })
  }

  function beforeOwnChange(): number | null {
    ownChangesRunning.value += 1

    return knownRevision() ?? cachedRevision()
  }

  /** After a change of this tab; the project content is loaded again already. */
  function afterOwnChange(before: number | null): void {
    const current = cachedRevision()

    if (isForeignChange(before, current, true)) {
      showChangedElsewhere()
    }
    remember(current)
  }

  /** Asks the server for the revision only; loads the project again if someone else changed it. */
  async function check(): Promise<void> {
    if (projectId.value === null || ownChangesRunning.value > 0) {
      return
    }

    const id = projectId.value
    const { data } = await resolveClient().query({ query: projectRevisionQuery, variables: { id }, fetchPolicy: 'network-only' })
    const current = data?.project?.planerRevision ?? null

    if (isForeignChange(knownRevision(), current, false)) {
      showChangedElsewhere()
      await refreshProject(id)
    }
    remember(current)
  }

  function ownChangeFinished(): void {
    ownChangesRunning.value = Math.max(0, ownChangesRunning.value - 1)
  }

  return { knownRevision, remember, cachedRevision, beforeOwnChange, afterOwnChange, ownChangeFinished, check }
}

/**
 * For timeline and kanban: asks for the revision every 30 seconds while the
 * tab is visible, and once more when it becomes visible again.
 */
export function useProjectRevisionPolling(): void {
  const { project } = useProject()
  const { knownRevision, remember, check } = useProjectRevision()

  // The first revision this tab sees is the loaded state.
  watch(() => project.value?.planerRevision, (revision) => {
    if (revision !== undefined && knownRevision() === null) {
      remember(revision)
    }
  }, { immediate: true })

  function tick(): void {
    if (document.visibilityState === 'visible') {
      check().catch(() => {
        // Offline or logged out: the next tick tries again; the api plugin reports real errors.
      })
    }
  }

  let timer: ReturnType<typeof setInterval> | undefined

  onMounted(() => {
    timer = setInterval(tick, POLL_INTERVAL_MS)
    document.addEventListener('visibilitychange', tick)
  })

  onBeforeUnmount(() => {
    clearInterval(timer)
    document.removeEventListener('visibilitychange', tick)
  })
}
