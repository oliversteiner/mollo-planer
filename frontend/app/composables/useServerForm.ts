import { FetchError } from 'ofetch'
import { classifyRestError } from '~/utils/api'

/**
 * State of a form that sends a REST request to the backend (Fortify).
 * Validation messages from the server end up per field in `errors`, ready
 * for UFormField's error prop; other failures are already reported by the
 * api plugin.
 */
export function useServerForm() {
  const errors = ref<Record<string, string>>({})
  const pending = ref(false)

  /** Runs the request; returns true if it succeeded. */
  async function run(request: () => Promise<unknown>): Promise<boolean> {
    errors.value = {}
    pending.value = true

    try {
      await request()

      return true
    } catch (error) {
      if (error instanceof FetchError) {
        const apiError = classifyRestError(error.status ?? 0, error.data)
        errors.value = Object.fromEntries(Object.entries(apiError.fields).map(([field, messages]) => [field, messages[0] ?? '']))
      }

      return false
    } finally {
      pending.value = false
    }
  }

  return { errors, pending, run }
}
