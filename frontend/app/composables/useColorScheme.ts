/**
 * Light or dark appearance: the choice (system, light, dark) is remembered
 * in the browser; "system" follows the setting of the device. The plugin
 * webawesome.client.ts puts wa-dark / wa-light on <html>; a small script in
 * the page head (nuxt.config.ts) does it before the app starts, so a dark
 * page does not flash white.
 */
export type ColorPreference = 'system' | 'light' | 'dark'

export const COLOR_SCHEME_KEY = 'color-mode'

/** Key of the former colour mode module of Nuxt UI, read once so nobody loses the setting. */
const LEGACY_KEY = 'nuxt-color-mode'

function isPreference(value: unknown): value is ColorPreference {
  return value === 'system' || value === 'light' || value === 'dark'
}

function storedPreference(): ColorPreference {
  try {
    const value = localStorage.getItem(COLOR_SCHEME_KEY) ?? localStorage.getItem(LEGACY_KEY)

    return isPreference(value) ? value : 'system'
  } catch {
    return 'system'
  }
}

function systemPrefersDark(): boolean {
  return typeof window !== 'undefined' && typeof window.matchMedia === 'function' && window.matchMedia('(prefers-color-scheme: dark)').matches
}

export function useColorScheme() {
  const preference = useState<ColorPreference>('colorPreference', storedPreference)
  const systemDark = useState<boolean>('colorSystemDark', systemPrefersDark)

  const dark = computed(() => preference.value === 'dark' || (preference.value === 'system' && systemDark.value))

  function setPreference(value: ColorPreference): void {
    preference.value = value

    try {
      localStorage.setItem(COLOR_SCHEME_KEY, value)
    } catch {
      // Not stored (private mode); the choice is kept for this visit only.
    }
  }

  return { preference, dark, systemDark, setPreference }
}
