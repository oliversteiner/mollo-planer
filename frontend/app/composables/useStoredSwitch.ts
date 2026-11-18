/**
 * A switch whose position the browser remembers (localStorage). Without
 * storage (private window, blocked) it still works for this visit.
 */
export function useStoredSwitch(key: string, fallback = false) {
  function read(): boolean {
    try {
      const stored = localStorage.getItem(key)

      return stored === null ? fallback : stored === '1'
    } catch {
      return fallback
    }
  }

  const value = ref(read())

  watch(value, (on) => {
    try {
      localStorage.setItem(key, on ? '1' : '0')
    } catch {
      // Storage blocked: keep the value for this visit only.
    }
  })

  return value
}
