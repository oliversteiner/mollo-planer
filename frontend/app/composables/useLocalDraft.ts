/** A draft as it is kept: when it was written, the revision it started from and the value. */
export interface LocalDraft<T> {
  savedAt: string
  revision: number
  value: T
}

/**
 * A draft of something being typed, kept in this browser (localStorage) so that a crash, a lost connection or a
 * conflict does not lose it (task 17). Only reading, writing and deleting the draft: whether and when to offer
 * it, and when to write, is up to the page. Every access is guarded: a browser without storage, a full storage or
 * a damaged entry leave the draft out and never break the page.
 */
export function useLocalDraft<T>(key: () => string) {
  function read(): LocalDraft<T> | null {
    try {
      const raw = window.localStorage.getItem(key())
      const parsed = raw ? JSON.parse(raw) as Partial<LocalDraft<T>> : null

      return parsed && typeof parsed.savedAt === 'string' && typeof parsed.revision === 'number' && parsed.value !== undefined
        ? parsed as LocalDraft<T>
        : null
    } catch {
      return null
    }
  }

  function write(revision: number, value: T): void {
    try {
      window.localStorage.setItem(key(), JSON.stringify({ savedAt: new Date().toISOString(), revision, value } satisfies LocalDraft<T>))
    } catch {
      // No storage or no room: no draft.
    }
  }

  function clear(): void {
    try {
      window.localStorage.removeItem(key())
    } catch {
      // Nothing to remove.
    }
  }

  return { read, write, clear }
}
