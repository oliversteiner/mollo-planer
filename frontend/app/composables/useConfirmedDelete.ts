import { computed, reactive, ref } from 'vue'

interface ConfirmedDeleteOptions<T> {
  /** Runs after a successful delete, for example for a toast or to close a panel. */
  onDeleted?: (target: T) => void
}

/**
 * Deleting with a confirmation dialog. `ask` keeps the target until the user
 * confirms; `confirm` runs `remove` and closes the dialog only if it succeeds.
 * A failed request keeps the dialog open (the api plugin reports the error).
 *
 * Bind it to a ConfirmModal: :open="deletion.isOpen", :action="deletion.confirm",
 * @close="deletion.close". The target is available as deletion.target.
 */
export function useConfirmedDelete<T>(remove: (target: T) => Promise<unknown>, options: ConfirmedDeleteOptions<T> = {}) {
  const target = ref<T | null>(null)
  const isOpen = computed(() => target.value !== null)

  function ask(item: T): void {
    target.value = item
  }

  function close(): void {
    target.value = null
  }

  async function confirm(): Promise<void> {
    const current = target.value

    if (current === null) {
      return
    }

    await remove(current)
    options.onDeleted?.(current)
    target.value = null
  }

  // reactive() unwraps the refs, so templates can read deletion.target and deletion.isOpen.
  return reactive({ target, isOpen, ask, close, confirm })
}
