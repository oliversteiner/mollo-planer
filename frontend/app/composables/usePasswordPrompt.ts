/**
 * Asks for the own password in a dialog (PasswordPromptModal in the
 * layouts). Fortify and the passkey routes need a recent password
 * confirmation before the second factor or a passkey is changed.
 */
let resolvePrompt: ((password: string | null) => void) | null = null

export function usePasswordPrompt() {
  const open = useState('passwordPromptOpen', () => false)
  /** Message of the previous attempt, for example "wrong password". */
  const error = useState<string>('passwordPromptError', () => '')

  /** Resolves with the entered password, or null when cancelled. */
  function ask(previousError = ''): Promise<string | null> {
    resolvePrompt?.(null)
    error.value = previousError
    open.value = true

    return new Promise((resolve) => {
      resolvePrompt = resolve
    })
  }

  function answer(password: string | null): void {
    open.value = false
    resolvePrompt?.(password)
    resolvePrompt = null
  }

  return { open, error, ask, answer }
}
