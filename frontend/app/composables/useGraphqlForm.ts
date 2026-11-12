import type { ApiError } from '~/utils/api'
import { classifyApolloError } from '~/utils/api'

/**
 * State of a form that sends a GraphQL mutation. Validation messages
 * (extensions.validation, keys like "input.name") end up per field in
 * `errors` without the "input." prefix; other failures are reported by
 * the api plugin.
 */
export function useGraphqlForm() {
  const errors = ref<Record<string, string>>({})
  const pending = ref(false)

  /** Runs the mutation; returns its result, or null if it failed. */
  async function run<T>(mutation: () => Promise<T>): Promise<T | null> {
    errors.value = {}
    pending.value = true

    try {
      return await mutation()
    } catch (error) {
      const apiError: ApiError = classifyApolloError(error)
      errors.value = Object.fromEntries(
        Object.entries(apiError.fields).map(([field, messages]) => [field.replace(/^input\./, ''), messages[0] ?? ''])
      )

      return null
    } finally {
      pending.value = false
    }
  }

  return { errors, pending, run }
}
