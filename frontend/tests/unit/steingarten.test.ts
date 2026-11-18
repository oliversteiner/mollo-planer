import { describe, expect, it } from 'vitest'
import { DEFAULT_BRIGHTNESS, DEFAULT_CONTRAST, MOODS, cleanValue, conformance, contrastRatio, palette, ramp, toneKey } from '../../app/utils/steingarten'

describe('Steingarten ramp', () => {
  it('has the five colours at 0, 25, 50, 75 and 100', () => {
    expect(ramp(0)).toBe('#F4EEE3')
    expect(ramp(25)).toBe('#E2D6C3')
    expect(ramp(50)).toBe('#A39A8C')
    expect(ramp(75)).toBe('#5E5348')
    expect(ramp(100)).toBe('#2B1E16')
  })

  it('goes evenly between the points and stays inside', () => {
    expect(ramp(12.5)).toBe('#EBE2D3')
    expect(ramp(-10)).toBe('#F4EEE3')
    expect(ramp(500)).toBe('#2B1E16')
  })

  it('names the tones', () => {
    expect([0, 25, 50, 75, 100].map(toneKey)).toEqual(['linen', 'sand', 'stone', 'taupe', 'leather'])
  })
})

describe('Steingarten palette', () => {
  it('is light ground with dark text up to 50 and dark ground with light text above', () => {
    const light = palette(12, 55)
    const dark = palette(88, 55)

    expect(light.bg).toBe(ramp(12))
    expect(light.text < light.bg).toBe(true)
    expect(dark.bg).toBe(ramp(88))
    expect(dark.text > dark.bg).toBe(true)
    expect(contrastRatio(palette(50, 50).text, palette(50, 50).bg)).toBeGreaterThanOrEqual(4.5)
    expect(palette(51, 50).bg).toBe(ramp(51))
    expect(palette(51, 50).accent).toBe('#B3C08F')
    expect(palette(50, 50).accent).not.toBe('#B3C08F')
  })

  it('keeps the minimum contrast on the whole grid, whatever the sliders say', () => {
    for (let brightness = 0; brightness <= 100; brightness += 5) {
      for (let contrast = 0; contrast <= 100; contrast += 5) {
        const colours = palette(brightness, contrast)
        const at = `${brightness}/${contrast}`

        expect(contrastRatio(colours.text, colours.bg), `text ${at}`).toBeGreaterThanOrEqual(4.5)
        expect(contrastRatio(colours.textMuted, colours.bg), `muted ${at}`).toBeGreaterThanOrEqual(3)
        expect(contrastRatio(colours.onAccent, colours.accent), `on accent ${at}`).toBeGreaterThanOrEqual(4.5)
      }
    }
  })

  it('gives the same colours for the same values', () => {
    expect(palette(30, 45)).toEqual(palette(30, 45))
  })

  it('has the four moods of the buttons', () => {
    expect(MOODS.map(mood => [mood.key, mood.brightness, mood.contrast])).toEqual([['linen', 6, 50], ['sand', 30, 45], ['stone', 52, 70], ['leather', 88, 55]])
  })
})

describe('Steingarten values', () => {
  it('takes whole numbers 0 to 100, anything else is the default', () => {
    expect(cleanValue('30', 12)).toBe(30)
    expect(cleanValue('100', 12)).toBe(100)
    expect(cleanValue('101', 12)).toBe(12)
    expect(cleanValue('-3', 12)).toBe(12)
    expect(cleanValue('4.5', 12)).toBe(12)
    expect(cleanValue('abc', 12)).toBe(12)
    expect(cleanValue(undefined, DEFAULT_CONTRAST)).toBe(55)
    expect(DEFAULT_BRIGHTNESS).toBe(12)
  })

  it('names AA and AAA', () => {
    expect(conformance(7.5)).toBe('AAA')
    expect(conformance(5)).toBe('AA')
    expect(conformance(3)).toBe('')
  })
})
