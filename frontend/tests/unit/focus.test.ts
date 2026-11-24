import { describe, expect, it, vi } from 'vitest'
import { focusWhenReady } from '../../app/utils/focus'

describe('focusWhenReady', () => {
  it('waits until the element has drawn itself, then focuses it', async () => {
    const order: string[] = []
    const element = {
      isConnected: true,
      updateComplete: Promise.resolve().then(() => order.push('drawn')),
      focus: vi.fn(() => order.push('focus'))
    } as unknown as HTMLElement

    await focusWhenReady(element)

    expect(order).toEqual(['drawn', 'focus'])
  })

  it('does nothing for an element that is not there', async () => {
    await expect(focusWhenReady(null)).resolves.toBeUndefined()
    await expect(focusWhenReady(undefined)).resolves.toBeUndefined()
  })

  it('does not focus an element that left the page while it was drawing', async () => {
    const element = { isConnected: false, updateComplete: Promise.resolve(), focus: vi.fn() } as unknown as HTMLElement

    await focusWhenReady(element)

    expect(element.focus).not.toHaveBeenCalled()
  })

  it('swallows the error of an element whose inner field is not there yet', async () => {
    const element = {
      isConnected: true,
      updateComplete: Promise.resolve(),
      focus: () => {
        throw new TypeError('Cannot read properties of null (reading \'focus\')')
      }
    } as unknown as HTMLElement

    await expect(focusWhenReady(element)).resolves.toBeUndefined()
  })

  it('swallows a failed update', async () => {
    const element = { isConnected: true, updateComplete: Promise.reject(new Error('update failed')), focus: vi.fn() } as unknown as HTMLElement

    await expect(focusWhenReady(element)).resolves.toBeUndefined()
    expect(element.focus).not.toHaveBeenCalled()
  })
})
