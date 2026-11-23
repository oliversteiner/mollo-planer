import { mountSuspended } from '@nuxt/test-utils/runtime'
import { getSchema } from '@tiptap/core'
import { beforeAll, describe, expect, it } from 'vitest'
import { defineComponent, h, nextTick, ref } from 'vue'

import Editor_ from '~/components/rich-text/Editor.vue'
import View from '~/components/rich-text/View.vue'
import { DOCUMENT_MARKS, DOCUMENT_NODES, RICH_TEXT_MARKS, RICH_TEXT_NODES, richTextExtensions } from '~/utils/rich-text'

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

async function settle() {
  await nextTick()
  await new Promise(resolve => setTimeout(resolve, 20))
}

const names = (profile: 'description' | 'document') => {
  const schema = getSchema(richTextExtensions(profile))

  return { nodes: Object.keys(schema.nodes).sort(), marks: Object.keys(schema.marks).sort() }
}

describe('rich text profiles (task 17)', () => {
  it('keeps the profile "description" exactly as it was: no table, code block, rule, task list, strike-through', () => {
    const { nodes, marks } = names('description')

    expect(nodes).toEqual([...RICH_TEXT_NODES].sort())
    expect(marks).toEqual([...RICH_TEXT_MARKS].sort())
  })

  it('has the elements of the server profile "document" and nothing more', () => {
    const { nodes, marks } = names('document')

    expect(nodes).toEqual([...DOCUMENT_NODES].sort())
    expect(marks).toEqual([...DOCUMENT_MARKS].sort())
  })

  it('allows a table cell exactly one paragraph and no merging of cells', () => {
    const schema = getSchema(richTextExtensions('document'))

    expect(schema.nodes.tableCell!.spec.content).toBe('paragraph')
    expect(schema.nodes.tableHeader!.spec.content).toBe('paragraph')
    expect(schema.nodes.table!.spec.tableRole).toBe('table')
  })

  it('shows headings 1 to 4 and the extra buttons only in the profile "document"', async () => {
    const make = (profile: 'description' | 'document') => {
      const value = ref<Record<string, unknown> | null>(null)

      return defineComponent(() => () => h(Editor_, {
        'profile': profile,
        'modelValue': value.value,
        'onUpdate:modelValue': (next: Record<string, unknown> | null) => {
          value.value = next
        }
      }))
    }
    const document = await mountSuspended(make('document'))
    const description = await mountSuspended(make('description'))
    await settle()

    const labels = (wrapper: typeof document) => wrapper.findAll('wa-button').map(button => button.attributes('aria-label'))

    expect(labels(document)).toEqual(expect.arrayContaining(['Überschrift 1', 'Überschrift 4', 'Durchgestrichen', 'Aufgabenliste', 'Codeblock', 'Trennlinie']))
    expect(labels(description)).not.toEqual(expect.arrayContaining(['Überschrift 1']))
    expect(labels(description)).not.toEqual(expect.arrayContaining(['Durchgestrichen']))
    expect(document.text()).toContain('Tabelle')
    expect(description.text()).not.toContain('Tabelle')
  })

  it('shows a document with a table, a code block and a rule through the read-only view, never as HTML', async () => {
    const content = {
      type: 'doc',
      content: [
        { type: 'heading', attrs: { level: 1 }, content: [{ type: 'text', text: 'Titel' }] },
        { type: 'table', content: [
          { type: 'tableRow', content: [{ type: 'tableHeader', content: [{ type: 'paragraph', content: [{ type: 'text', text: 'Kopf' }] }] }] },
          { type: 'tableRow', content: [{ type: 'tableCell', content: [{ type: 'paragraph', content: [{ type: 'text', text: '<b>Zelle</b>' }] }] }] }
        ] },
        { type: 'codeBlock', attrs: { language: 'php' }, content: [{ type: 'text', text: 'echo 1;' }] },
        { type: 'horizontalRule' }
      ]
    }
    const view = await mountSuspended(View, { props: { content, profile: 'document' } })
    await settle()

    expect(view.find('h1').text()).toBe('Titel')
    expect(view.find('th').text()).toBe('Kopf')
    expect(view.find('td').text()).toBe('<b>Zelle</b>')
    expect(view.find('td b').exists()).toBe(false)
    expect(view.find('pre code').text()).toBe('echo 1;')
    expect(view.find('hr').exists()).toBe(true)
  })

  it('does not render a rule in the profile "description", as before', async () => {
    const content = { type: 'doc', content: [{ type: 'paragraph', content: [{ type: 'text', text: 'Nur Text' }] }, { type: 'horizontalRule' }] }
    const view = await mountSuspended(View, { props: { content } })
    await settle()

    expect(view.find('hr').exists()).toBe(false)
  })
})
