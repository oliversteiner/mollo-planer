import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { beforeAll, beforeEach, describe, expect, it } from 'vitest'
import ChapterPage from '~/pages/help/[id].vue'

/**
 * A chapter of the help (Auftrag 66 A, B): the right as a quiet line with a next step, the switch only for chapters
 * with pictures, clearer section titles, and an appendix with its free text.
 */
const route = { params: { id: 'agent-anlegen' } as Record<string, string>, query: {}, hash: '' }

mockNuxtImport('useRoute', () => () => route)

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

beforeEach(() => {
  localStorage.clear()
  useState<string[]>('systemRights').value = []
})

const open = async (id: string) => {
  route.params.id = id

  return mountSuspended(ChapterPage)
}

describe('right of a chapter', () => {
  it('says it in a quiet line, not a box, with the next step, when a system right is missing', async () => {
    const page = await open('agent-anlegen')

    expect(page.find('.help-right').text()).toBe('Dafür brauchst du das Recht «Benutzer verwalten, Benutzerliste». Frag eine Person, die Benutzer verwalten darf.')
    expect(page.find('wa-callout').exists()).toBe(false)
  })

  it('says nothing when the person has the right', async () => {
    useState<string[]>('systemRights').value = ['USERS_MANAGE']

    const page = await open('agent-anlegen')

    expect(page.find('.help-right').exists()).toBe(false)
  })
})

describe('pictures switch', () => {
  it('is there for a chapter with pictures', async () => {
    expect((await open('task-anlegen')).findComponent({ name: 'FormSwitch' }).exists()).toBe(true)
  })

  it('is not there for a chapter without pictures', async () => {
    expect((await open('tasks-ueber-mcp')).findComponent({ name: 'FormSwitch' }).exists()).toBe(false)
  })
})

describe('sections and appendix', () => {
  it('has clear section titles (wa-heading-l)', async () => {
    const page = await open('task-anlegen')

    expect(page.findAll('.help-chapter h2').every(heading => heading.classes().includes('wa-heading-l'))).toBe(true)
  })

  it('shows an appendix with its free text, without sections and without a right', async () => {
    const page = await open('anhang-schreibregeln')

    expect(page.find('.help-appendix').exists()).toBe(true)
    expect(page.find('.help-right').exists()).toBe(false)
    expect(page.text()).toContain('Dieses Kapitel folgt')
    expect(page.text()).not.toContain('Beispielprompts')
  })

  it('has the bar with previous and next', async () => {
    expect((await open('task-anlegen')).find('.help-nav').exists()).toBe(true)
  })
})
