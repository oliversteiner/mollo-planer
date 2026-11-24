import { mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import Picker from '~/components/icon/Picker.vue'

const catalog = [
  ['rocket', 'Rocket', 'launch'],
  ['rake', 'Rake', 'garden'],
  ['rabbit', 'Rabbit', 'animal'],
  ['book', 'Book', 'read'],
  ['star', 'Star', 'favorite']
]

type Observer = { callback: IntersectionObserverCallback, observed: Element[] }
const observers: Observer[] = []

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

beforeEach(() => {
  observers.length = 0
  vi.useFakeTimers()
  vi.stubGlobal('IntersectionObserver', class {
    private readonly entry: Observer

    constructor(callback: IntersectionObserverCallback) {
      this.entry = { callback, observed: [] }
      observers.push(this.entry)
    }

    observe(element: Element) {
      this.entry.observed.push(element)
    }

    unobserve(element: Element) {
      this.entry.observed = this.entry.observed.filter(item => item !== element)
    }

    disconnect() {
      this.entry.observed = []
    }
  })
  useState('iconCatalog').value = catalog
})

afterEach(() => {
  vi.useRealTimers()
  vi.unstubAllGlobals()
})

async function opened() {
  const view = await mountSuspended(Picker, { props: { fallback: 'circle' } })

  await view.find('wa-button').trigger('click')
  await flushPromises()

  return view
}

const names = (view: Awaited<ReturnType<typeof opened>>) => view.findAll('.icon-picker-choice').map(tile => tile.attributes('data-icon'))

async function type(view: Awaited<ReturnType<typeof opened>>, text: string) {
  const input = view.find('wa-input').element as unknown as { value: string }

  input.value = text
  view.find('wa-input').element.dispatchEvent(new Event('input'))
  await flushPromises()
}

function show(...elements: Element[]) {
  for (const observer of observers) {
    observer.callback(elements.map(target => ({ target, isIntersecting: true }) as IntersectionObserverEntry), {} as IntersectionObserver)
  }
}

describe('icon picker, few requests (Auftrag 36 B)', () => {
  it('searches only 250 ms after the last key and not under two characters', async () => {
    const view = await opened()
    const suggestions = names(view)

    await type(view, 'r')
    vi.advanceTimersByTime(300)
    await flushPromises()
    expect(names(view)).toEqual(suggestions)

    await type(view, 'ra')
    vi.advanceTimersByTime(200)
    await flushPromises()
    expect(names(view)).toEqual(suggestions)

    await type(view, 'rak')
    vi.advanceTimersByTime(200)
    await flushPromises()
    expect(names(view)).toEqual(suggestions)

    vi.advanceTimersByTime(100)
    await flushPromises()
    expect(names(view)).toEqual(['rake'])
  })

  it('renders no icon for a tile until it has come into view, and then loads only that one', async () => {
    const view = await opened()
    await type(view, 'ra')
    vi.advanceTimersByTime(300)
    await flushPromises()

    expect(names(view)).toEqual(['rabbit', 'rake'])
    expect(view.findAll('.icon-picker-choice wa-icon')).toHaveLength(0)
    expect(view.findAll('.icon-picker-pending')).toHaveLength(2)

    show(view.findAll('li')[0]!.element)
    await flushPromises()

    expect(view.findAll('.icon-picker-choice wa-icon')).toHaveLength(1)
    expect((view.find('[data-icon="rabbit"] wa-icon').element as unknown as { name: string }).name).toBe('rabbit')
    expect(view.find('[data-icon="rake"] wa-icon').exists()).toBe(false)
  })

  it('has one tooltip for all tiles, which follows the tile that is hovered', async () => {
    const view = await opened()

    expect(view.findAll('wa-tooltip')).toHaveLength(1)

    await view.find('[data-icon="rocket"]').trigger('pointerover')
    const tooltip = view.find('wa-tooltip').element as unknown as { for: string, open: boolean }
    expect(tooltip.for).toBe(view.find('[data-icon="rocket"]').attributes('id'))
    expect(tooltip.open).toBe(true)
    expect(view.find('wa-tooltip').text()).toBe('Rocket (rocket)')

    await view.find('[data-icon="book"]').trigger('pointerover')
    expect(tooltip.for).toBe(view.find('[data-icon="book"]').attributes('id'))
    expect(view.find('wa-tooltip').text()).toBe('Book (book)')

    await view.find('.icon-picker-grid').trigger('pointerleave')
    expect(tooltip.open).toBe(false)
  })

  it('keeps the name of every tile for screen readers and chooses on a click', async () => {
    const view = await opened()
    const tile = view.find('[data-icon="rocket"]')

    expect(tile.attributes('aria-label')).toBe('Rocket')

    await tile.trigger('click')
    expect(view.emitted('update:modelValue')?.[0]).toEqual(['rocket'])
  })
})
