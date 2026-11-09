import { mountSuspended } from '@nuxt/test-utils/runtime'
import type { Editor } from '@tiptap/vue-3'
import { beforeAll, describe, expect, it } from 'vitest'
import { defineComponent, h, nextTick, ref } from 'vue'

import Editor_ from '~/components/rich-text/Editor.vue'
import View from '~/components/rich-text/View.vue'

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

const text = { type: 'doc', content: [{ type: 'paragraph', content: [{ type: 'text', text: 'Erster Kommentar' }] }] }

async function settle() {
  await nextTick()
  await new Promise(resolve => setTimeout(resolve, 20))
}

/** Host that keeps the model, as a form does. */
function host(initial: Record<string, unknown> | null, props: Record<string, unknown> = {}) {
  const value = ref<Record<string, unknown> | null>(initial)
  const component = defineComponent(() => () => h(Editor_, {
    ...props,
    'modelValue': value.value,
    'onUpdate:modelValue': (next: Record<string, unknown> | null) => { value.value = next }
  }))

  return { value, component }
}

describe('RichTextEditor', () => {
  it('shows the text of the model', async () => {
    const { component } = host(text)
    const wrapper = await mountSuspended(component)
    await settle()

    expect(wrapper.find('.ProseMirror').text()).toBe('Erster Kommentar')
  })

  it('empties the editor when the value is set back to null, as after sending a comment', async () => {
    const { value, component } = host(text)
    const wrapper = await mountSuspended(component)
    await settle()

    value.value = null
    await settle()

    expect(wrapper.find('.ProseMirror').text()).toBe('')
  })

  it('writes what is typed back as TipTap JSON, only with allowed elements', async () => {
    const { value, component } = host(null)
    const wrapper = await mountSuspended(component)
    await settle()

    const editor = (wrapper.findComponent({ name: 'EditorContent' }).props('editor')) as Editor
    editor.commands.setContent('<h1>Gross</h1><p><strong>fett</strong> <s>durch</s></p><hr>', { emitUpdate: true })
    await settle()

    const json = JSON.stringify(value.value)

    expect(json).toContain('fett')
    expect(json).toContain('bold')
    expect(json).not.toContain('strike')
    expect(json).not.toContain('horizontalRule')
    expect(json).not.toContain('"level":1')
  })

  it('has the toolbar buttons with names, a smaller toolbar for comments', async () => {
    const full = await mountSuspended(host(text).component)
    const compact = await mountSuspended(host(text, { compact: true }).component)
    await settle()

    const names = (wrapper: typeof full) => wrapper.findAll('[role="toolbar"] wa-button').map(button => button.attributes('aria-label'))

    expect(names(full)).toEqual(expect.arrayContaining(['Fett', 'Kursiv', 'Aufzählung', 'Nummerierte Liste', 'Zitat', 'Rückgängig']))
    expect(names(compact)).toEqual(expect.arrayContaining(['Fett', 'Kursiv', 'Aufzählung']))
    expect(names(compact)).not.toContain('Rückgängig')
    expect(names(compact).length).toBeLessThan(names(full).length)
  })

  it('makes the selected text bold with the button', async () => {
    const { value, component } = host(text)
    const wrapper = await mountSuspended(component)
    await settle()

    const editor = (wrapper.findComponent({ name: 'EditorContent' }).props('editor')) as Editor
    editor.commands.selectAll()
    await wrapper.find('wa-button[aria-label="Fett"]').trigger('click')
    await settle()

    expect(JSON.stringify(value.value)).toContain('"type":"bold"')
    expect(wrapper.find('wa-button[aria-label="Fett"]').attributes('aria-pressed')).toBe('true')
  })
})

describe('RichTextView', () => {
  it('shows the text read-only, nothing for an empty document', async () => {
    const view = await mountSuspended(View, { props: { content: text } })
    await settle()

    expect(view.find('.ProseMirror').text()).toBe('Erster Kommentar')
    expect(view.find('.ProseMirror').attributes('contenteditable')).toBe('false')

    const empty = await mountSuspended(View, { props: { content: null } })

    expect(empty.find('.ProseMirror').exists()).toBe(false)
  })
})
