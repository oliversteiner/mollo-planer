<script setup lang="ts">
import type { EditorToolbarItem } from '@nuxt/ui'
import { richTextExtensions } from '~/utils/rich-text'

/**
 * Editor for descriptions and comments: TipTap JSON in and out, only the
 * elements the server allows (see utils/rich-text.ts). "compact" gives a
 * smaller toolbar for comments.
 */
const props = withDefaults(defineProps<{
  placeholder?: string
  compact?: boolean
}>(), {
  placeholder: undefined,
  compact: false
})

const model = defineModel<Record<string, unknown> | null>({ default: null })

/**
 * UEditor ignores null, so after "model = null" (for example a comment
 * just sent) it would keep the old text. It gets an empty document then.
 */
const content = computed({
  get: () => model.value ?? { type: 'doc', content: [{ type: 'paragraph' }] },
  set: (value: Record<string, unknown> | null) => {
    model.value = value
  }
})

const { t } = useI18n()

const extensions = richTextExtensions()

const items = computed<EditorToolbarItem[][]>(() => {
  const marks: EditorToolbarItem[] = [
    { 'kind': 'mark', 'mark': 'bold', 'icon': 'i-fadl-bold', 'aria-label': t('richText.bold'), 'tooltip': { text: t('richText.bold') } },
    { 'kind': 'mark', 'mark': 'italic', 'icon': 'i-fadl-italic', 'aria-label': t('richText.italic'), 'tooltip': { text: t('richText.italic') } }
  ]
  const lists: EditorToolbarItem[] = [
    { 'kind': 'bulletList', 'icon': 'i-fadl-list-ul', 'aria-label': t('richText.bulletList'), 'tooltip': { text: t('richText.bulletList') } },
    { 'kind': 'orderedList', 'icon': 'i-fadl-list-ol', 'aria-label': t('richText.orderedList'), 'tooltip': { text: t('richText.orderedList') } }
  ]
  const link: EditorToolbarItem[] = [{ slot: 'link' }]

  if (props.compact) {
    return [marks, lists, link]
  }

  return [
    [
      { 'kind': 'undo', 'icon': 'i-fadl-arrow-rotate-left', 'aria-label': t('richText.undo'), 'tooltip': { text: t('richText.undo') } },
      { 'kind': 'redo', 'icon': 'i-fadl-arrow-rotate-right', 'aria-label': t('richText.redo'), 'tooltip': { text: t('richText.redo') } }
    ],
    [
      { 'kind': 'heading', 'level': 2, 'icon': 'i-fadl-h2', 'aria-label': t('richText.heading2'), 'tooltip': { text: t('richText.heading2') } },
      { 'kind': 'heading', 'level': 3, 'icon': 'i-fadl-h3', 'aria-label': t('richText.heading3'), 'tooltip': { text: t('richText.heading3') } }
    ],
    marks,
    [...lists, { 'kind': 'blockquote', 'icon': 'i-fadl-block-quote', 'aria-label': t('richText.blockquote'), 'tooltip': { text: t('richText.blockquote') } }],
    link
  ]
})
</script>

<template>
  <UEditor
    v-slot="{ editor }"
    v-model="content"
    content-type="json"
    :starter-kit="false"
    :image="false"
    :mention="false"
    :extensions="extensions"
    :placeholder="placeholder"
    class="rounded-md border border-default focus-within:border-primary"
    :ui="{ content: compact ? 'min-h-20 px-3 py-2' : 'min-h-32 px-3 py-2' }"
  >
    <UEditorToolbar
      :editor="editor"
      :items="items"
      class="border-b border-default px-1 py-1"
    >
      <template #link>
        <RichTextLinkPopover :editor="editor" />
      </template>
    </UEditorToolbar>
  </UEditor>
</template>
