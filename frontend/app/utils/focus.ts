/**
 * Puts the focus on a web component once it is ready (Auftrag 55). A Web Awesome element that was just inserted draws
 * itself first; its inner field takes the focus only after that, and `focus()` before it throws («Cannot read properties
 * of null»). So this waits for `updateComplete`, and does nothing when the element is gone or cannot take the focus:
 * no focus is better than an error, the person clicks into the field.
 */
type Focusable = (HTMLElement & { updateComplete?: Promise<unknown> }) | null | undefined

export async function focusWhenReady(element: Focusable): Promise<void> {
  if (!element) {
    return
  }

  try {
    await element.updateComplete

    // The panel may have closed while the element drew itself.
    if (element.isConnected) {
      element.focus()
    }
  } catch {
    // Not ready or not focusable (for example in a test environment): nothing to do.
  }
}
