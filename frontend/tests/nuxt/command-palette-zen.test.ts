import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { computed, nextTick, ref } from 'vue'
import CommandPalette from '~/components/command/CommandPalette.vue'

/**
 * The command palette in Zen (Auftrag 66 D): «Zen-Ansicht verlassen» and «Stimmung» stand side by side in a row above
 * «Aktionen» and not in the list of actions; outside Zen nothing changes.
 */
const { mode, leaveFocus } = vi.hoisted(() => ({ mode: { value: 'calm' as string }, leaveFocus: vi.fn() }))
const view = ref('calm')

mockNuxtImport('useViewMode', () => () => ({ mode: computed(() => view.value), focusing: computed(() => false), setMode: vi.fn(), leaveFocus }))
mockNuxtImport('useCommandPalette', () => () => ({ open: ref(true), hide: vi.fn(), show: vi.fn() }))

vi.mock('@vue/apollo-composable', () => ({
  useQuery: () => ({ result: ref({ projects: [] }), loading: ref(false), error: ref(null), refetch: vi.fn() }),
  useMutation: () => ({ mutate: vi.fn() }),
  useApolloClient: () => ({ resolveClient: () => ({}) })
}))

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

beforeEach(() => {
  view.value = mode.value
})

const texts = (palette: { findAll: (selector: string) => { text: () => string }[] }, selector: string) => palette.findAll(selector).map(item => item.text())

describe('command palette in Zen', () => {
  it('puts «Zen-Ansicht verlassen» and «Stimmung» in a row of their own above the actions', async () => {
    const palette = await mountSuspended(CommandPalette)

    expect(texts(palette, '.command-zen-row .command-item')).toEqual(['Zen-Ansicht verlassen', 'Stimmung'])
    // Not again in the list of actions.
    expect(texts(palette, '.command-item:not(.command-zen-item)')).not.toContain('Zen-Ansicht verlassen')
    expect(texts(palette, '.command-item:not(.command-zen-item)')).not.toContain('Stimmung')
    // The row is above the heading «Aktionen».
    const html = palette.html()
    expect(html.indexOf('command-zen-row')).toBeLessThan(html.indexOf('command-group'))
  })

  it('reaches the row first: the first entry of the list is the one selected', async () => {
    const palette = await mountSuspended(CommandPalette)
    const first = palette.find('.command-zen-row .command-item')

    expect(first.attributes('aria-selected')).toBe('true')
  })

  it('filters the two with the input, and the row is gone when neither matches', async () => {
    const palette = await mountSuspended(CommandPalette)

    palette.findComponent({ name: 'FormInput' }).vm.$emit('update:modelValue', 'stimm')
    await nextTick()
    expect(texts(palette, '.command-zen-row .command-item')).toEqual(['Stimmung'])

    palette.findComponent({ name: 'FormInput' }).vm.$emit('update:modelValue', 'Passwort')
    await nextTick()
    expect(palette.find('.command-zen-row').exists()).toBe(false)
  })
})

describe('command palette outside Zen', () => {
  it('has no row: «Zen starten» stays with the other actions', async () => {
    view.value = 'full'
    mode.value = 'full'
    const palette = await mountSuspended(CommandPalette)

    expect(palette.find('.command-zen-row').exists()).toBe(false)
    expect(texts(palette, '.command-item').some(text => text.startsWith('Zen'))).toBe(true)
    mode.value = 'calm'
  })
})
