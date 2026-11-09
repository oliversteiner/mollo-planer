/**
 * Short notifications at the top right (a <wa-toast> stack of Web Awesome).
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

/** One stack for the whole app, created at the first message. */
function stack(): HTMLElement {
  let element = document.querySelector<HTMLElement>('wa-toast')

  if (!element) {
    element = document.createElement('wa-toast')
    document.body.append(element)
  }

  return element
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

    stack().append(item)

    return item
  }

  return { add }
}
