import { afterEach, describe, expect, it } from 'vitest'

afterEach(() => {
  document.querySelectorAll('wa-toast').forEach(element => element.remove())
})

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

  it('is the toast the app uses (the former Nuxt UI one is replaced)', () => {
    // Auto import resolves to app/composables/useToast.ts: its result has no Nuxt UI "toasts" list.
    expect(Object.keys(useToast())).toEqual(['add'])
  })
})
