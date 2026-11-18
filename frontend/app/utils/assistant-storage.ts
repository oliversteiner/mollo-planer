/**
 * The chat of the assistant is kept in the browser tab (sessionStorage) so a reload does not lose it. It belongs to
 * one person: the key carries the user id, and logging out (or an ended session) deletes every key of it
 * (docs/fix-m6.md F9.7).
 */
const PREFIX = 'assistant-draft'

export function assistantStorageKey(userId: string | null | undefined): string {
  return `${PREFIX}-v2:${userId ?? 'anonymous'}`
}

/** Deletes the saved chat of every person. */
export function clearAssistantStorage(): void {
  try {
    for (const key of Object.keys(sessionStorage).filter(name => name.startsWith(PREFIX))) {
      sessionStorage.removeItem(key)
    }
  } catch {
    // No storage: nothing to delete.
  }
}
