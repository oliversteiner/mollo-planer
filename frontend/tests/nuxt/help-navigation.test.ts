import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import ChapterNav from '~/components/help/ChapterNav.vue'
import Toc from '~/components/help/Toc.vue'
import { manualGroups, manualOrder } from '~/utils/manual'

/**
 * The navigation of the help (Auftrag 66 E): the order of the chapters, previous and next over the borders of the areas
 * with the appendix last, the ends, the contents with the chapter on the page marked.
 */
const { navigate } = vi.hoisted(() => ({ navigate: vi.fn() }))

mockNuxtImport('navigateTo', () => navigate)

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

const hrefOf = (wrapper: { element: Element }) => wrapper.element.getAttribute('href') ?? (wrapper.element as Element & { href?: string }).href

const ids = () => manualOrder('de').map(chapter => chapter.id)

describe('order of the chapters', () => {
  it('is the order of the overview over the borders of the areas, the appendix last', () => {
    expect(ids()).toEqual(manualGroups('de').flatMap(group => group.chapters.map(chapter => chapter.id)))
    expect(ids().at(-1)).toBe('anhang-schreibregeln')
    expect(manualGroups('de').at(-1)!.area).toBe('appendix')
  })

  it('keeps a chapter the person lacks the right for', () => {
    expect(ids()).toContain('agent-anlegen')
  })
})

describe('bar with previous, contents and next', () => {
  const first = () => ids()[0]!
  const last = () => ids().at(-1)!

  it('links to the chapters before and after, with the title as tooltip and label', async () => {
    const middle = ids()[1]!
    const bar = await mountSuspended(ChapterNav, { props: { current: middle } })
    const previous = manualOrder('de')[0]!
    const next = manualOrder('de')[2]!

    expect(hrefOf(bar.find('#help-previous'))).toBe(`/help/${previous.id}`)
    expect(bar.find('#help-previous').attributes('title')).toBe(previous.title)
    expect(bar.find('#help-previous').attributes('aria-label')).toBe(`Vorheriges Kapitel: ${previous.title}`)
    expect(hrefOf(bar.find('#help-next'))).toBe(`/help/${next.id}`)
    expect(bar.find('#help-toc').text()).toBe('Inhalt')
  })

  it('has no «previous» at the first chapter and no «next» at the last', async () => {
    expect((await mountSuspended(ChapterNav, { props: { current: first() } })).find('#help-previous').exists()).toBe(false)
    expect((await mountSuspended(ChapterNav, { props: { current: last() } })).find('#help-next').exists()).toBe(false)
    expect((await mountSuspended(ChapterNav, { props: { current: first() } })).find('#help-next').exists()).toBe(true)
  })

  it('goes to the chapter inside the app and leaves the new tab to the browser', async () => {
    const bar = await mountSuspended(ChapterNav, { props: { current: ids()[1]! } })

    await bar.find('#help-next').trigger('click', { button: 0 })
    expect(navigate).toHaveBeenCalledWith(`/help/${ids()[2]}`)

    navigate.mockClear()
    await bar.find('#help-next').trigger('click', { button: 0, ctrlKey: true })
    expect(navigate).not.toHaveBeenCalled()
  })

  it('has no data-zen mark: it stays in the Zen view', async () => {
    const bar = await mountSuspended(ChapterNav, { props: { current: ids()[1]! } })

    expect(bar.html()).not.toContain('data-zen')
  })
})

describe('contents', () => {
  it('lists every chapter by area and marks the one on the page', async () => {
    const toc = await mountSuspended(Toc, { props: { current: 'task-anlegen' } })

    expect(toc.findAll('.help-toc-link').map(link => link.attributes('href'))).toEqual(ids().map(id => `/help/${id}`))
    expect(toc.findAll('[aria-current="page"]').map(link => link.text())).toEqual(['Task anlegen und bearbeiten'])
    expect(toc.findAll('.help-toc-area').at(-1)!.text()).toBe('Über dieses Handbuch')
  })
})
