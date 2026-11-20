<script setup lang="ts">
import { EditorContent, useEditor } from '@tiptap/vue-3'
import { isEmptyDocument, richTextExtensions, type RichTextProfile } from '~/utils/rich-text'

/**
 * Shows rich text read-only. TipTap renders the JSON into the page (no
 * v-html), so only the allowed elements can appear.
 */
const props = withDefaults(defineProps<{
  content?: Record<string, unknown> | null
  /** "document" for whole documents (more elements); the default is for descriptions and comments. */
  profile?: RichTextProfile
}>(), {
  content: undefined,
  profile: 'description'
})

const empty = computed(() => isEmptyDocument(props.content))

const editor = useEditor({
  content: props.content ?? undefined,
  extensions: richTextExtensions(props.profile),
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
