import { isChord, shortcutBlocked } from '~/utils/shortcuts'

/**
 * The shortcuts of the views (docs/zen-modus.md §4.4): Ctrl/Cmd+K the palette, Ctrl/Cmd+. the calm view, Esc leaves the focus.
 * They do not act while a dialog or drawer is open or a field needs the key itself.
 */
export function useViewShortcuts(): void {
  const { toggleCalm, focusing, leaveFocus } = useViewMode()
  const palette = useCommandPalette()

  function onKeydown(event: KeyboardEvent): void {
    if (shortcutBlocked(event)) {
      return
    }

    if (isChord(event, 'k')) {
      event.preventDefault()
      palette.open.value = !palette.open.value
    } else if (isChord(event, '.')) {
      event.preventDefault()
      toggleCalm()
    } else if (event.key === 'Escape' && focusing.value && !event.defaultPrevented) {
      event.preventDefault()
      void leaveFocus()
    }
  }

  onMounted(() => document.addEventListener('keydown', onKeydown))
  onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown))
}
