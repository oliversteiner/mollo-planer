import { describe, expect, it } from 'vitest'

describe('Web Awesome', () => {
  it('registers the components the app uses', () => {
    for (const tag of ['wa-button', 'wa-input', 'wa-select', 'wa-dialog', 'wa-drawer', 'wa-page', 'wa-icon']) {
      expect(customElements.get(tag), tag).toBeDefined()
    }
  })

  it('follows the colour mode with wa-dark / wa-light on <html>', async () => {
    const root = document.documentElement

    const { setPreference, systemDark } = useColorScheme()
    setPreference('dark')
    await nextTick()
    expect(root.classList.contains('wa-dark')).toBe(true)

    setPreference('light')
    await nextTick()
    expect(root.classList.contains('wa-dark')).toBe(false)

    // "System" follows the device.
    setPreference('system')
    systemDark.value = true
    await nextTick()
    expect(root.classList.contains('wa-dark')).toBe(true)

    systemDark.value = false
    await nextTick()
    expect(root.classList.contains('wa-dark')).toBe(false)
    expect(root.classList.contains('wa-light')).toBe(true)
  })
})
