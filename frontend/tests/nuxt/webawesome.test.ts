import { describe, expect, it } from 'vitest'

describe('Web Awesome', () => {
  it('registers the components the app uses', () => {
    for (const tag of ['wa-button', 'wa-input', 'wa-select', 'wa-dialog', 'wa-drawer', 'wa-page', 'wa-icon']) {
      expect(customElements.get(tag), tag).toBeDefined()
    }
  })

  it('follows the colour mode with wa-dark / wa-light on <html>', async () => {
    const root = document.documentElement

    const colorMode = useColorMode()
    colorMode.preference = 'dark'
    await nextTick()
    expect(root.classList.contains('wa-dark')).toBe(true)

    colorMode.preference = 'light'
    await nextTick()
    expect(root.classList.contains('wa-dark')).toBe(false)
    expect(root.classList.contains('wa-light')).toBe(true)
  })
})
