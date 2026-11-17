/** How long the undo message stays (docs/auftrag-m2.md §8: about 8 seconds). */
export const UNDO_DURATION = 8000

interface DeleteOptions<T> {
  /** Runs after a successful delete, for example to close a panel. */
  onDeleted?: (target: T) => void
}

/**
 * The deletionId in the result of a delete mutation ({ data: { deleteTask: { deletionId } } }).
 */
export function deletionIdOf(result: unknown): string | null {
  const data = (result as { data?: Record<string, { deletionId?: string | null } | null> } | null | undefined)?.data

  for (const value of Object.values(data ?? {})) {
    if (value?.deletionId) {
      return value.deletionId
    }
  }

  return null
}

/** How long to wait at most for a closing overlay. */
const HIDE_TIMEOUT = 1000

/** Resolves when the overlay has finished closing (or after HIDE_TIMEOUT). */
function afterHide(overlay: HTMLElement): Promise<void> {
  return new Promise((resolve) => {
    const done = (event?: Event) => {
      if (event && event.target !== overlay) {
        return
      }
      overlay.removeEventListener('wa-after-hide', done)
      clearTimeout(timer)
      resolve()
    }
    const timer = setTimeout(done, HIDE_TIMEOUT)
    overlay.addEventListener('wa-after-hide', done)
  })
}

/** Did the focus leave with the deleted element? */
function focusLeft(focused: Element | null, closed: HTMLElement[]): boolean {
  const active = document.activeElement

  if (active === null || active === document.body) {
    return true
  }
  if (focused === null || focused === document.body) {
    return false
  }

  return !focused.isConnected || closed.some(overlay => overlay.contains(focused))
}

/**
 * The one way the app deletes project elements (milestones, todos, tasks,
 * comments): at once, into the trash, then a message „Gelöscht“ with
 * „Rückgängig“ for about 8 seconds (docs/auftrag-m2.md §4.1). Undo restores
 * the deletion and loads the project again (useTrashRestore). Afterwards
 * the trash page still has it. Deleting a project and deleting for good keep
 * their confirmation (useConfirmedDelete).
 *
 * When the deleted element held the focus, the focus moves to „Rückgängig“,
 * so the keyboard does not lose its place: when the focus is lost, or the
 * element that had it is gone or was in an overlay that closed. An overlay
 * that closes on delete (task details) is waited for first, so its focus
 * return does not win; the message then goes to the page (F5.1).
 */
export function useDelete<T>(remove: (target: T) => Promise<unknown>, options: DeleteOptions<T> = {}) {
  const { t } = useI18n()
  const toast = useToast()
  const restore = useTrashRestore()

  async function run(target: T): Promise<void> {
    const focused = document.activeElement
    const deletionId = deletionIdOf(await remove(target).catch(() => null))

    if (deletionId === null) {
      return
    }

    const overlays = openOverlays()
    options.onDeleted?.(target)
    await nextTick()
    const closed = overlays.filter(overlay => overlay.open !== true)
    await Promise.all(closed.map(afterHide))

    const item = toast.add({
      title: t('trash.deleted'),
      color: 'neutral',
      duration: UNDO_DURATION,
      action: { label: t('trash.undo'), run: () => restore(deletionId) }
    })

    const button = item.querySelector<HTMLElement & { updateComplete?: Promise<unknown> }>('.toast-action')
    await nextTick()
    await button?.updateComplete
    await new Promise(resolve => requestAnimationFrame(resolve))

    if (button && focusLeft(focused, closed)) {
      button.focus()
    }
  }

  return { remove: run }
}
