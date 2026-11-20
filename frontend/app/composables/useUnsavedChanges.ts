/**
 * Warns before unsaved changes are lost (task 17): when the page is left through the router (the state `open`, to
 * be shown in a ConfirmModal with `confirm` and `stay`) and when the tab or window is closed (the browser's own
 * question). Nothing happens while `isDirty` is false.
 */
export function useUnsavedChanges(isDirty: () => boolean) {
  const open = ref(false)
  let decide: ((leave: boolean) => void) | null = null

  /** The router guard: lets go at once without changes, otherwise waits for the answer to the question. */
  function guard(): boolean | Promise<boolean> {
    if (!isDirty()) {
      return true
    }
    open.value = true

    return new Promise<boolean>((resolve) => {
      decide = resolve
    })
  }

  onBeforeRouteLeave(guard)

  function beforeUnload(event: BeforeUnloadEvent): void {
    if (isDirty()) {
      event.preventDefault()
    }
  }

  onMounted(() => window.addEventListener('beforeunload', beforeUnload))
  onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload))

  /** Leave anyway. */
  async function confirm(): Promise<void> {
    open.value = false
    decide?.(true)
    decide = null
  }

  /** Stay on the page. Also what closing the question without an answer does. */
  function stay(): void {
    open.value = false
    decide?.(false)
    decide = null
  }

  return { open, confirm, stay, guard }
}
