import { afterEach, describe, expect, it, vi } from 'vitest'
import { installElementInternals } from './support/element-internals'
import { standInDrawer } from './support/overlay'

installElementInternals()

afterEach(() => {
  document.querySelectorAll('wa-toast, wa-drawer').forEach(element => element.remove())
})

/** An open modal side panel; everything outside it is inert. */
function openDrawer(): HTMLElement & { open: boolean } {
  const drawer = standInDrawer()
  document.body.append(drawer)

  return drawer
}

describe('useToast', () => {
  it('adds a message to one shared stack with the variant of its colour', () => {
    const toast = useToast()

    toast.add({ title: 'Gespeichert', color: 'success' })
    toast.add({ title: 'Fehler', description: 'Nicht erreichbar', color: 'error', duration: 0 })

    expect(document.querySelectorAll('wa-toast')).toHaveLength(1)

    const [first, second] = [...document.querySelectorAll('wa-toast-item')]

    expect(first!.getAttribute('variant')).toBe('success')
    expect(first!.textContent).toBe('Gespeichert')
    expect(second!.getAttribute('variant')).toBe('danger')
    expect(second!.getAttribute('duration')).toBe('0')
    expect(second!.textContent).toBe('FehlerNicht erreichbar')
    expect(second!.querySelector('wa-icon')!.getAttribute('slot')).toBe('icon')
  })

  it('shows texts as text, never as HTML', () => {
    useToast().add({ title: '<img src=x onerror=alert(1)>', description: '<b>fett</b>', color: 'warning' })

    const item = document.querySelector('wa-toast-item')!

    expect(item.querySelector('img')).toBeNull()
    expect(item.querySelector('b')).toBeNull()
    expect(item.textContent).toContain('<img src=x onerror=alert(1)>')
  })

  it('can carry one button that runs its action and closes the message', () => {
    const run = vi.fn()
    const item = useToast().add({ title: 'Gelöscht', duration: 8000, action: { label: 'Rückgängig', run } }) as HTMLElement & { hide: () => Promise<void> }
    item.hide = vi.fn(async () => {})

    const button = [...item.children].find(child => child.classList.contains('toast-action')) as HTMLElement
    expect(button.tagName).toBe('WA-BUTTON')
    expect(button.textContent).toBe('Rückgängig')
    expect(item.getAttribute('duration')).toBe('8000')

    button.dispatchEvent(new MouseEvent('click'))
    button.dispatchEvent(new MouseEvent('click'))

    expect(run).toHaveBeenCalledTimes(1)
    expect(item.hide).toHaveBeenCalledTimes(1)
  })

  it('holds the countdown while the focus is on its button, also when it came before the countdown', async () => {
    const item = useToast().add({ title: 'Gelöscht', duration: 8000, action: { label: 'Rückgängig', run: vi.fn() } }) as HTMLElement & { isFocused?: boolean, pauseTimer: () => void, resumeTimer: () => void }
    item.pauseTimer = vi.fn()
    item.resumeTimer = vi.fn()
    const button = item.querySelector<HTMLElement & { updateComplete: Promise<unknown> }>('.toast-action')!
    await button.updateComplete

    button.focus()
    expect(item.pauseTimer).toHaveBeenCalledTimes(1)
    expect(item.isFocused).toBe(true)

    item.dispatchEvent(new Event('wa-after-show'))
    await Promise.resolve()
    expect(item.pauseTimer).toHaveBeenCalledTimes(2)

    button.blur()
    expect(item.isFocused).toBe(false)
    expect(item.resumeTimer).toHaveBeenCalledTimes(1)
  })

  it('puts the message into an open modal overlay, so its button can be used there', () => {
    const drawer = openDrawer()

    const item = useToast().add({ title: 'Gelöscht', action: { label: 'Rückgängig', run: vi.fn() } })

    expect(item.parentElement!.parentElement).toBe(drawer)
    expect(document.body.querySelector(':scope > wa-toast')).toBeNull()
  })

  it('moves the messages to the page when the overlay closes, not when something inside it does', () => {
    const drawer = openDrawer()
    const item = useToast().add({ title: 'Gelöscht' })
    const inner = document.createElement('wa-select')
    drawer.append(inner)

    inner.dispatchEvent(new Event('wa-hide', { bubbles: true }))
    expect(item.parentElement!.parentElement).toBe(drawer)

    drawer.open = false
    drawer.dispatchEvent(new Event('wa-hide'))

    expect(item.parentElement!.parentElement).toBe(document.body)
    expect(drawer.querySelector('wa-toast')).toBeNull()
  })

  it('is the toast the app uses (the former Nuxt UI one is replaced)', () => {
    // Auto import resolves to app/composables/useToast.ts: its result has no Nuxt UI "toasts" list.
    expect(Object.keys(useToast())).toEqual(['add'])
  })
})
