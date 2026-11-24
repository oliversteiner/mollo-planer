import { FetchError } from 'ofetch'
import { classifyRestError } from '~/utils/api'

/**
 * What an action returns when the server asked for a recent password confirmation (the answer of a GraphQL form:
 * `errors.password` is set). The step then shows the field again and keeps the action for after the confirmation.
 */
export const NEEDS_PASSWORD = 'needs-password' as const

type Action = () => Promise<unknown>

/**
 * Password confirmation inside a dialog, never in a dialog of its own (Auftrag 55, rule R11): an action that needs a
 * recent confirmation (Fortify, `/user/confirmed-password-status`) runs through `run()`. When the confirmation is
 * old, `waiting` turns true and the dialog shows a password field (PasswordConfirmField) above its button; after the
 * right password the action goes on, without another click. A wrong password stays at the field. `cancel()` closes the
 * field and runs nothing. What the person typed in the dialog is untouched, so nothing is lost.
 *
 * Actions that do not happen in a dialog keep `useSecurity().ensurePasswordConfirmed`.
 */
export function usePasswordStep() {
  const { $backend } = useNuxtApp()

  /** The field is shown. */
  const waiting = ref(false)
  const password = ref('')
  const error = ref('')
  /** The password is being checked. */
  const checking = ref(false)
  /** The action itself is running (after the confirmation as well as without one). */
  const working = ref(false)

  let held: Action | null = null

  /** Runs the action; when it says the confirmation ran out meanwhile, the field comes back with the action kept. */
  async function perform(action: Action): Promise<void> {
    const result = await action()

    if (result === NEEDS_PASSWORD) {
      ask(action)
    }
  }

  function ask(action: Action): void {
    held = action
    password.value = ''
    error.value = ''
    waiting.value = true
  }

  /**
   * Runs the action, after asking for the password first if the confirmation is old. From the first moment (the
   * question to the server comes first) until the end this counts as working, so a second click does nothing.
   */
  async function run(action: Action): Promise<void> {
    if (working.value || checking.value) {
      return
    }

    working.value = true

    try {
      const status = await $backend<{ confirmed: boolean }>('/user/confirmed-password-status')

      if (status.confirmed) {
        await perform(action)
      } else {
        ask(action)
      }
    } finally {
      working.value = false
    }
  }

  /** The person gave the password: confirm it, then run the held action. */
  async function submit(): Promise<void> {
    const action = held

    if (!action || !password.value || checking.value) {
      return
    }

    checking.value = true
    error.value = ''

    try {
      await $backend('/user/confirm-password', { method: 'POST', body: { password: password.value } })
    } catch (failure) {
      const apiError = failure instanceof FetchError ? classifyRestError(failure.status ?? 0, failure.data) : null

      if (apiError?.kind !== 'validation') {
        throw failure
      }
      error.value = apiError.fields.password?.[0] ?? apiError.message ?? ''

      return
    } finally {
      checking.value = false
    }

    waiting.value = false
    held = null
    password.value = ''
    working.value = true

    try {
      await perform(action)
    } finally {
      working.value = false
    }
  }

  /** Close the field, run nothing. */
  function cancel(): void {
    waiting.value = false
    held = null
    password.value = ''
    error.value = ''
  }

  /** The field writes here (a component must not change a property of the step it was given). */
  function setPassword(value: string): void {
    password.value = value
  }

  return reactive({ waiting, password, error, checking, working, run, submit, cancel, setPassword })
}

export type PasswordStep = ReturnType<typeof usePasswordStep>
