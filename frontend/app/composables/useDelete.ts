/** How long the undo message stays (docs/auftrag-m2.md §8: about 8 seconds). */
export const UNDO_DURATION = 8000

interface DeleteOptions<T> {
  /**
   * Runs after a successful delete, for example to close a panel. A panel
   * closed here must close by the next tick (its `open` false), so the
   * message waits for it and goes to the page.
   */
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

/**
 * Listens to an overlay from now on: whether it starts to close (wa-hide)
 * and when it has closed (wa-after-hide), so neither is missed when it
 * closes early. While its hide animation runs, wa-drawer and wa-dialog
 * report `open` as true again, so `open` alone cannot tell.
 */
function watchHide(overlay: HTMLElement): { closing: () => boolean, done: () => Promise<void>, stop: () => void } {
  let hiding = false
  let hidden = false
  let notify = () => {}
  const onHide = (event: Event) => {
    if (event.target === overlay) {
      hiding = true
    }
  }
  const onAfterHide = (event: Event) => {
    if (event.target === overlay) {
      hidden = true
      notify()
    }
  }
  overlay.addEventListener('wa-hide', onHide)
  overlay.addEventListener('wa-after-hide', onAfterHide)
  const stop = () => {
    overlay.removeEventListener('wa-hide', onHide)
    overlay.removeEventListener('wa-after-hide', onAfterHide)
  }

  return {
    closing: () => hiding || hidden,
    stop,
    done: () => new Promise((resolve) => {
      const timer = setTimeout(() => notify(), HIDE_TIMEOUT)
      notify = () => {
        clearTimeout(timer)
        stop()
        resolve()
      }
      if (hidden) {
        notify()
      }
    })
  }
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
    // From before the delete: reloading may already close a panel that shows the deleted element.
    const overlays = openOverlays().map(overlay => ({ overlay, hide: watchHide(overlay) }))
    const deletionId = deletionIdOf(await remove(target).catch(() => null))

    if (deletionId === null) {
      overlays.forEach(({ hide }) => hide.stop())

      return
    }

    options.onDeleted?.(target)
    await nextTick()
    // The overlay starts to close in its own update after Vue's.
    await new Promise(resolve => requestAnimationFrame(resolve))
    const isClosing = ({ overlay, hide }: (typeof overlays)[number]) => hide.closing() || overlay.open !== true
    const closed = overlays.filter(isClosing)
    overlays.filter(entry => !isClosing(entry)).forEach(({ hide }) => hide.stop())
    await Promise.all(closed.map(({ hide }) => hide.done()))

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

    if (button && focusLeft(focused, closed.map(({ overlay }) => overlay))) {
      button.focus()
    }
  }

  return { remove: run }
}
