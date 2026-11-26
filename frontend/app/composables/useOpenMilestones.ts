/**
 * The open milestones of the project page (docs/ux-projektseite.md §4.3): a set of ids, remembered per project in this
 * browser (`mollo.timeline.<projectId>.open`, a JSON list). The current milestone is open at every visit, also when it
 * was closed the last time (Entscheid E1, Auftrag 47 E); the others keep their remembered state, and as long as nothing
 * is remembered only the current one is open. Without storage (private window, blocked) the state lasts for the visit.
 * Todos are not remembered (E2).
 */
export function openMilestonesKey(projectId: string): string {
  return `mollo.timeline.${projectId}.open`
}

/** The remembered ids, or null when nothing is remembered (or what is there cannot be read). */
export function readOpenMilestones(projectId: string): string[] | null {
  try {
    const stored = localStorage.getItem(openMilestonesKey(projectId))

    if (stored === null) {
      return null
    }

    const list: unknown = JSON.parse(stored)

    return Array.isArray(list) ? list.filter((id): id is string => typeof id === 'string') : null
  } catch {
    return null
  }
}

export function useOpenMilestones(projectId: () => string) {
  const open = ref(new Set<string>())
  const started = ref(false)

  function save(): void {
    try {
      localStorage.setItem(openMilestonesKey(projectId()), JSON.stringify([...open.value]))
    } catch {
      // Storage blocked: the state stays for this visit.
    }
  }

  /** Once, when the milestones are known: the remembered state without unknown ids, and the current milestone. */
  function start(ids: string[], currentId: string | undefined): void {
    if (started.value || ids.length === 0) {
      return
    }

    started.value = true
    const remembered = readOpenMilestones(projectId())

    // The current milestone is not saved here: opening it is not a choice of the person.
    open.value = new Set([...(remembered ?? []).filter(id => ids.includes(id)), ...(currentId ? [currentId] : [])])
  }

  function change(next: Set<string>): void {
    open.value = next
    save()
  }

  function set(id: string, value: boolean): void {
    const next = new Set(open.value)

    if (value) {
      next.add(id)
    } else {
      next.delete(id)
    }

    change(next)
  }

  /** Opens these milestones in addition to the ones that are open (a link, a new milestone, a drop). */
  function reveal(ids: string[]): void {
    if (ids.some(id => !open.value.has(id))) {
      change(new Set([...open.value, ...ids]))
    }
  }

  return {
    open,
    start,
    isOpen: (id: string) => open.value.has(id),
    set,
    reveal,
    openAll: (ids: string[]) => change(new Set(ids)),
    closeAll: () => change(new Set()),
    /** The calm view opens the current milestone besides the others; this is not a choice of the person, so nothing is saved. */
    showInCalm: (id: string) => {
      if (!open.value.has(id)) {
        open.value = new Set([...open.value, id])
      }
    }
  }
}
