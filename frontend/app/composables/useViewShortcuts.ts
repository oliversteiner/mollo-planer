import { isChord, shortcutBlocked } from '~/utils/shortcuts'

/**
 * The shortcuts of the views (docs/zen-modus.md §4.4): Ctrl/Cmd+K opens the search, Esc leaves the focus. There is no shortcut for Zen (it starts from the search or the button).
 * They do not act while a dialog or drawer is open or a field needs the key itself.
 */
export function useViewShortcuts(): void {
  const { focusing, leaveFocus } = useViewMode()
  const palette = useCommandPalette()

  function onKeydown(event: KeyboardEvent): void {
    if (shortcutBlocked(event)) {
      return
    }

    if (isChord(event, 'k')) {
      event.preventDefault()
      palette.open.value = !palette.open.value
    } else if (event.key === 'Escape' && focusing.value && !event.defaultPrevented) {
      event.preventDefault()
      void leaveFocus()
    }
  }

  onMounted(() => document.addEventListener('keydown', onKeydown))
  onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown))
}
