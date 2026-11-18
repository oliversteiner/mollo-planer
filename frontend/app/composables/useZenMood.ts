import { DEFAULT_BRIGHTNESS, DEFAULT_CONTRAST, cleanValue, palette } from '~/utils/steingarten'

/**
 * The mood of the zen views (docs/zen-modus.md §4.6): brightness and contrast, per device in the cookies
 * `zen_brightness` and `zen_contrast` (a year, Lax). A change works at once; the cookies are written 300 ms after
 * the last change, so a slider that is dragged does not write at every step. Invalid cookie values mean the default.
 */
export function useZenMood() {
  const options = { maxAge: 60 * 60 * 24 * 365, sameSite: 'lax' as const, path: '/' }
  const brightnessCookie = useCookie<string | number | null | undefined>('zen_brightness', options)
  const contrastCookie = useCookie<string | number | null | undefined>('zen_contrast', options)

  const brightness = useState<number>('zen-brightness', () => cleanValue(brightnessCookie.value, DEFAULT_BRIGHTNESS))
  const contrast = useState<number>('zen-contrast', () => cleanValue(contrastCookie.value, DEFAULT_CONTRAST))
  const colours = computed(() => palette(brightness.value, contrast.value))

  let timer: ReturnType<typeof setTimeout> | undefined

  function setMood(nextBrightness: number, nextContrast: number): void {
    brightness.value = cleanValue(nextBrightness, DEFAULT_BRIGHTNESS)
    contrast.value = cleanValue(nextContrast, DEFAULT_CONTRAST)

    clearTimeout(timer)
    timer = setTimeout(() => {
      brightnessCookie.value = String(brightness.value)
      contrastCookie.value = String(contrast.value)
    }, 300)
  }

  function resetMood(): void {
    setMood(DEFAULT_BRIGHTNESS, DEFAULT_CONTRAST)
  }

  return { brightness, contrast, colours, setMood, resetMood }
}
