<script setup lang="ts">
import { EditorContent, useEditor } from '@tiptap/vue-3'
import { isEmptyDocument, richTextExtensions } from '~/utils/rich-text'

/**
 * Shows rich text read-only. TipTap renders the JSON into the page (no
 * v-html), so only the allowed elements can appear.
 */
const props = defineProps<{ content?: Record<string, unknown> | null }>()

const empty = computed(() => isEmptyDocument(props.content))

const editor = useEditor({
  content: props.content ?? undefined,
  extensions: richTextExtensions(),
  editable: false
})

watch(() => props.content, (value) => {
  if (editor.value && value) {
    editor.value.commands.setContent(value, { emitUpdate: false })
  }
})
</script>

<template>
  <EditorContent
    v-if="!empty"
    :editor="editor"
    class="rich-text"
  />
</template>
