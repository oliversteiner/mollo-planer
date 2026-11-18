/**
 * Short notifications at the bottom right (a <wa-toast> stack of Web Awesome).
 * At the top they covered the action buttons of the pages.
 * Same call as the former Nuxt UI toast: `toast.add({ title, description?, color })`.
 * Texts are set as text, never as HTML.
 */
export interface ToastOptions {
  title: string
  description?: string
  /** Meaning of the message; "primary" and "info" are the brand colour. */
  color?: 'success' | 'error' | 'warning' | 'info' | 'primary' | 'neutral'
  /** Milliseconds until it disappears; 0: stays until closed. */
  duration?: number
  /**
   * A button in the message, for example „Rückgängig“. Clicking it runs the
   * action and closes the message. The message stops counting down while the
   * pointer or the focus is on it (holdWhileFocused).
   */
  action?: { label: string, run: () => unknown }
}

type Variant = 'success' | 'danger' | 'warning' | 'brand' | 'neutral'

const variants: Record<NonNullable<ToastOptions['color']>, { variant: Variant, icon: string }> = {
  success: { variant: 'success', icon: 'circle-check' },
  error: { variant: 'danger', icon: 'circle-xmark' },
  warning: { variant: 'warning', icon: 'triangle-exclamation' },
  info: { variant: 'brand', icon: 'circle-info' },
  primary: { variant: 'brand', icon: 'circle-info' },
  neutral: { variant: 'neutral', icon: 'circle-info' }
}

type Overlay = HTMLElement & { open?: boolean }

/**
 * Open modal overlays (wa-dialog, wa-drawer), in document order. While one
 * is open, everything outside it is inert.
 */
export function openOverlays(): Overlay[] {
  return [...document.querySelectorAll<Overlay>('wa-dialog, wa-drawer')].filter(overlay => overlay.open === true)
}

function stackIn(host: HTMLElement): HTMLElement {
  let element = host.querySelector<HTMLElement>(':scope > wa-toast')

  if (!element) {
    element = document.createElement('wa-toast')
    element.setAttribute('placement', 'bottom-end')
    host.append(element)
  }

  return element
}

/**
 * The stack for the next message: the page's own, or, while a modal overlay
 * is open, one inside the topmost overlay, so its buttons can be used (F5.1).
 * When that overlay closes, its messages move on to the page's stack.
 */
function stack(): HTMLElement {
  const overlay = openOverlays().at(-1)

  if (!overlay) {
    return stackIn(document.body)
  }

  const existing = overlay.querySelector<HTMLElement>(':scope > wa-toast')
  if (existing) {
    return existing
  }

  const element = stackIn(overlay)
  const onHide = (event: Event) => {
    if (event.target !== overlay) {
      return
    }
    overlay.removeEventListener('wa-hide', onHide)
    const items = [...element.querySelectorAll(':scope > wa-toast-item')]
    element.remove()
    if (items.length) {
      stackIn(document.body).append(...items)
    }
  }
  overlay.addEventListener('wa-hide', onHide)

  return element
}

/** The parts of wa-toast-item used to hold the countdown. */
type ToastItem = HTMLElement & { isFocused?: boolean, pauseTimer?: () => void, resumeTimer?: () => void }

/**
 * wa-toast-item stops counting down for the pointer and for the focus on
 * its own close button, not for a button in its content. Here the focus
 * anywhere in the message holds it, also when the focus came before the
 * countdown started (after the show animation).
 */
function holdWhileFocused(item: ToastItem): void {
  const hold = () => {
    if (item.contains(document.activeElement)) {
      item.isFocused = true
      item.pauseTimer?.()
    }
  }

  item.addEventListener('focusin', hold)
  // The countdown starts right after this event; hold it once it runs.
  item.addEventListener('wa-after-show', () => queueMicrotask(hold))
  item.addEventListener('focusout', (event) => {
    if (!item.contains(event.relatedTarget as Node | null)) {
      item.isFocused = false
      item.resumeTimer?.()
    }
  })
}

export function useToast() {
  function add(options: ToastOptions): HTMLElement {
    const { variant, icon } = variants[options.color ?? 'neutral']

    const item = document.createElement('wa-toast-item')
    item.setAttribute('variant', variant)
    if (options.duration !== undefined) {
      item.setAttribute('duration', String(options.duration))
    }

    const iconElement = document.createElement('wa-icon')
    iconElement.setAttribute('slot', 'icon')
    iconElement.setAttribute('name', icon)
    item.append(iconElement)

    const title = document.createElement('strong')
    title.textContent = options.title
    item.append(title)

    if (options.description) {
      const description = document.createElement('div')
      description.textContent = options.description
      item.append(description)
    }

    if (options.action) {
      const { label, run } = options.action
      const button = document.createElement('wa-button')
      button.setAttribute('size', 's')
      button.setAttribute('appearance', 'outlined')
      button.setAttribute('variant', 'neutral')
      button.classList.add('toast-action')
      button.textContent = label
      button.addEventListener('click', () => {
        void (item as HTMLElement & { hide?: () => Promise<void> }).hide?.()
        run()
      }, { once: true })
      item.append(button)
      holdWhileFocused(item as unknown as ToastItem)
    }

    stack().append(item)

    return item
  }

  return { add }
}
