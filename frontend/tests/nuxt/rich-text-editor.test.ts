import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import { defineComponent, h, nextTick, ref } from 'vue'
import { UApp } from '#components'
import Editor from '~/components/rich-text/Editor.vue'

const text = { type: 'doc', content: [{ type: 'paragraph', content: [{ type: 'text', text: 'Erster Kommentar' }] }] }

/** The toolbar tooltips need the providers of UApp. */
const value = ref<Record<string, unknown> | null>(text)
const Host = defineComponent(() => () => h(UApp, null, {
  default: () => h(Editor, { 'modelValue': value.value, 'onUpdate:modelValue': (next: Record<string, unknown> | null) => { value.value = next } })
}))

async function settle() {
  await nextTick()
  await new Promise(resolve => setTimeout(resolve, 20))
}

describe('RichTextEditor', () => {
  it('empties the editor when the value is set back to null, as after sending a comment', async () => {
    const host = await mountSuspended(Host)
    await settle()

    expect(host.find('.ProseMirror').text()).toBe('Erster Kommentar')

    value.value = null
    await settle()

    expect(host.find('.ProseMirror').text()).toBe('')
  })
})
