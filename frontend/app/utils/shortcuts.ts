/**
 * Whether the keyboard shortcuts of the app must stay out of the way (docs/zen-modus.md §4.4): an open dialog or
 * drawer has its own keys, and a text field or the editor needs its keys itself. Shortcuts with Ctrl or Cmd are
 * safe in a text field; plain keys (Esc) are not, and not while an overlay is open.
 */
export function shortcutBlocked(event: KeyboardEvent, root: Document = document): boolean {
  if (root.querySelector('wa-dialog[open], wa-drawer[open]') !== null) {
    return true
  }

  const modifier = event.ctrlKey || event.metaKey
  const target = event.target as HTMLElement | null
  const typing = target !== null && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName) || ['WA-INPUT', 'WA-TEXTAREA'].includes(target.tagName))

  return !modifier && typing
}

/** "Ctrl/Cmd + key" (the key by its character, not its place on the keyboard). */
export function isChord(event: KeyboardEvent, key: string): boolean {
  return (event.ctrlKey || event.metaKey) && !event.altKey && !event.shiftKey && event.key === key
}
