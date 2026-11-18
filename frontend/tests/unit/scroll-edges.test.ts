import { describe, expect, it } from 'vitest'
import { scrollEdges } from '../../app/utils/scroll-edges'

describe('scrollEdges (Rechte-Übersicht: Schatten, wo weitere Spalten folgen)', () => {
  it('sees nothing when everything fits', () => {
    expect(scrollEdges(0, 800, 800)).toEqual({ start: false, end: false })
  })

  it('sees more on the right at the start, on both sides in the middle, on the left at the end', () => {
    expect(scrollEdges(0, 600, 900)).toEqual({ start: false, end: true })
    expect(scrollEdges(150, 600, 900)).toEqual({ start: true, end: true })
    expect(scrollEdges(300, 600, 900)).toEqual({ start: true, end: false })
  })

  it('ignores the fraction of a pixel a zoomed page reports', () => {
    expect(scrollEdges(0.5, 600, 900.6)).toEqual({ start: false, end: true })
    expect(scrollEdges(299.6, 600, 900)).toEqual({ start: true, end: false })
  })
})
