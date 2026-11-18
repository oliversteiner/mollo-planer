/**
 * The colour scheme "Steingarten" of the zen views (docs/zen-modus.md §4.6): linen, sand, stone, taupe and leather,
 * made from two values, brightness and contrast (0 to 100). A pure function without a DOM, so it gives the same
 * colours on the server and in the browser. Above 50 the scheme tips to a dark ground with light text. Whatever the
 * sliders say, text on the ground is at least 4.5:1, secondary text 3:1 and text on the accent 4.5:1.
 */
export interface Palette {
  bg: string
  surface: string
  border: string
  text: string
  textMuted: string
  accent: string
  onAccent: string
  warn: string
  danger: string
}

type Rgb = [number, number, number]

/** The ramp: position and colour (sRGB), spread evenly between the points. */
export const RAMP: readonly (readonly [number, string])[] = [
  [0, '#F4EEE3'], // Leinen
  [25, '#E2D6C3'], // Sand
  [50, '#A39A8C'], // Stein
  [75, '#5E5348'], // Taupe
  [100, '#2B1E16'] // Leder
]

export const DEFAULT_BRIGHTNESS = 12
export const DEFAULT_CONTRAST = 55

/** The moods of the buttons. */
export const MOODS = [
  { key: 'linen', brightness: 6, contrast: 50 },
  { key: 'sand', brightness: 30, contrast: 45 },
  { key: 'stone', brightness: 52, contrast: 70 },
  { key: 'leather', brightness: 88, contrast: 55 }
] as const

function parse(hex: string): Rgb {
  const value = Number.parseInt(hex.slice(1), 16)

  return [(value >> 16) & 255, (value >> 8) & 255, value & 255]
}

function format(rgb: Rgb): string {
  return `#${rgb.map(channel => Math.round(Math.min(255, Math.max(0, channel))).toString(16).padStart(2, '0')).join('').toUpperCase()}`
}

function mix(from: string, to: string, share: number): string {
  const a = parse(from)
  const b = parse(to)

  return format(a.map((channel, index) => channel + (b[index]! - channel) * share) as Rgb)
}

/** The colour of the ramp at a position 0 to 100. */
export function ramp(position: number): string {
  const at = Math.min(100, Math.max(0, position))
  const upper = RAMP.findIndex(([stop]) => stop >= at)
  const [stopB, colourB] = RAMP[Math.max(upper, 1)]!
  const [stopA, colourA] = RAMP[Math.max(upper, 1) - 1]!

  return mix(colourA, colourB, (at - stopA) / (stopB - stopA))
}

function luminance(hex: string): number {
  const [r, g, b] = parse(hex).map((channel) => {
    const value = channel / 255

    return value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4
  }) as Rgb

  return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

/** WCAG contrast ratio of two colours, 1 to 21. */
export function contrastRatio(a: string, b: string): number {
  const [light, dark] = [luminance(a), luminance(b)].sort((x, y) => y - x) as [number, number]

  return (light + 0.05) / (dark + 0.05)
}

/** The name of the tone at a brightness, for the slider ("Leinen", "Sand", ...). */
export function toneKey(brightness: number): 'linen' | 'sand' | 'stone' | 'taupe' | 'leather' {
  const keys = ['linen', 'sand', 'stone', 'taupe', 'leather'] as const

  return keys[Math.min(4, Math.round(brightness / 25))]!
}

/** "AA", "AAA" or "" for a contrast ratio of normal text. */
export function conformance(ratio: number): 'AAA' | 'AA' | '' {
  return ratio >= 7 ? 'AAA' : ratio >= 4.5 ? 'AA' : ''
}

/** Brightness and contrast to a whole number 0 to 100; anything else is the default. */
export function cleanValue(value: unknown, fallback: number): number {
  const number = typeof value === 'string' && /^\d{1,3}$/.test(value) ? Number(value) : typeof value === 'number' ? value : Number.NaN

  return Number.isInteger(number) && number >= 0 && number <= 100 ? number : fallback
}

export function palette(brightness: number, contrast: number): Palette {
  const b = Math.min(100, Math.max(0, brightness))
  const c = Math.min(100, Math.max(0, contrast)) / 100
  const dark = b > 50
  const bg = ramp(b)

  // Text: further from the ground the more contrast; then pushed on until it reads (4.5:1).
  const reach = 100 * (0.42 + 0.58 * c)
  let position = dark ? b - reach : b + reach
  let text = ramp(position)

  while (contrastRatio(text, bg) < 4.5 && position > 0 && position < 100) {
    position = dark ? position - 1 : position + 1
    text = ramp(position)
  }

  // In the middle of the ramp (stone) the ramp itself cannot reach 4.5:1: then black or white, whichever reads better
  // (the best of the two is always above 4.5:1).
  if (contrastRatio(text, bg) < 4.5) {
    text = contrastRatio('#000000', bg) >= contrastRatio('#FFFFFF', bg) ? '#000000' : '#FFFFFF'
  }

  // Secondary text and border: mixtures of ground and text; the secondary text is moved towards the text until 3:1.
  let share = 0.5 + 0.25 * c
  let textMuted = mix(bg, text, share)

  while (contrastRatio(textMuted, bg) < 3 && share < 1) {
    share = Math.min(1, share + 0.05)
    textMuted = mix(bg, text, share)
  }

  const border = mix(bg, text, 0.12 + 0.2 * c)
  const surface = mix(bg, text, 0.05)

  // Accent: moss green, from light to deep by contrast; light moss on a dark ground.
  const accent = dark ? '#B3C08F' : mix('#55633C', '#343E24', c)
  const warn = dark ? '#D6AA6E' : '#966226'
  // Danger stays red (light on dark grounds) and is moved towards the text until it reads (4.5:1).
  let danger = dark ? '#F2B8B5' : '#B3261E'
  let dangerShare = 0

  while (contrastRatio(danger, bg) < 4.5 && dangerShare < 1) {
    dangerShare = Math.min(1, dangerShare + 0.1)
    danger = mix(dark ? '#F2B8B5' : '#B3261E', text, dangerShare)
  }

  const onAccent = [bg, text, '#FFFFFF', '#000000'].reduce((best, candidate) => (contrastRatio(candidate, accent) > contrastRatio(best, accent) ? candidate : best))

  return { bg, surface, border, text, textMuted, accent, onAccent, warn, danger }
}

/** The palette as CSS variables for <html>. */
export function paletteVariables(colours: Palette): Record<string, string> {
  return {
    '--zen-bg': colours.bg,
    '--zen-surface': colours.surface,
    '--zen-border': colours.border,
    '--zen-text': colours.text,
    '--zen-text-muted': colours.textMuted,
    '--zen-accent': colours.accent,
    '--zen-on-accent': colours.onAccent,
    '--zen-warn': colours.warn,
    '--zen-danger': colours.danger
  }
}
