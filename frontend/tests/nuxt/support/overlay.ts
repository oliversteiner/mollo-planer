/**
 * A wa-drawer that only says whether it is open. The real one needs the
 * dialog element of a browser; the tests switch `open` by hand.
 */
export function standInDrawer(open = true): HTMLElement & { open: boolean } {
  const drawer = document.createElement('wa-drawer')
  Object.defineProperty(drawer, 'open', { value: open, writable: true })

  return drawer as HTMLElement & { open: boolean }
}
