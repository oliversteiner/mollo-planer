import { computed } from 'vue'

export type ViewMode = 'full' | 'calm' | 'focus'

/** What "focus" shows: a kind (task, todo, later documents) and its id. */
export interface FocusTarget {
  kind: string
  id: string
}

/** The calm mode survives the page: a cookie for a year (docs/zen-modus.md §4.1). */
export function parseStoredMode(value: unknown): 'full' | 'calm' {
  return value === 'calm' ? 'calm' : 'full'
}

/**
 * The only source of the view (docs/zen-modus.md): "full" (as before), "calm" (stored per device in the cookie
 * `view_mode`) and "focus" (one element, bound to the URL: `?focus=1`, so back, reload and shared links work).
 * Focus ends when the element is left; then the stored mode applies again.
 */
export function useViewMode() {
  const route = useRoute()
  const router = useRouter()
  const stored = useCookie<string>('view_mode', { default: () => 'full', maxAge: 60 * 60 * 24 * 365, sameSite: 'lax', path: '/' })

  const calm = computed(() => parseStoredMode(stored.value) === 'calm')
  const focusing = computed(() => route.query.focus === '1')
  const mode = computed<ViewMode>(() => (focusing.value ? 'focus' : calm.value ? 'calm' : 'full'))

  /** Sets the stored mode ("focus" is not stored: it comes from the URL). */
  function setMode(next: 'full' | 'calm'): void {
    stored.value = next
  }

  function toggleCalm(): void {
    setMode(calm.value ? 'full' : 'calm')
  }

  /** Opens the element alone. The caller knows the element; it is the page of the route with `?focus=1`. */
  async function enterFocus(target?: FocusTarget): Promise<void> {
    const query: Record<string, string> = { ...(route.query as Record<string, string>), focus: '1' }

    if (target?.kind === 'task') {
      query.task = target.id
    } else if (target?.kind === 'todo') {
      query.todo = target.id
    }

    await router.push({ path: route.path, query })
  }

  /** Back to the stored mode: removes `?focus=1` (the history entry of the focus is replaced, not piled up). */
  async function leaveFocus(): Promise<void> {
    const { focus: _focus, todo: _todo, ...query } = route.query

    await router.replace({ path: route.path, query })
  }

  return { mode, calm, focusing, setMode, toggleCalm, enterFocus, leaveFocus }
}
