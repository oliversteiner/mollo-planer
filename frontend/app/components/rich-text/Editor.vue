<script setup lang="ts">
import { Placeholder } from '@tiptap/extensions'
import { EditorContent, useEditor } from '@tiptap/vue-3'
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

const { t } = useI18n()

/** A value of null (for example a comment just sent) empties the editor. */
const EMPTY = { type: 'doc', content: [{ type: 'paragraph' }] }

const editor = useEditor({
  content: model.value ?? EMPTY,
  extensions: [...richTextExtensions(), Placeholder.configure({ placeholder: props.placeholder ?? '' })],
  editorProps: { attributes: { class: 'rich-text-content' } },
  onUpdate: ({ editor }) => {
    model.value = editor.getJSON() as Record<string, unknown>
  }
})

watch(model, (value) => {
  const next = value ?? EMPTY

  if (editor.value && JSON.stringify(editor.value.getJSON()) !== JSON.stringify(next)) {
    editor.value.commands.setContent(next, { emitUpdate: false })
  }
})

interface Tool {
  key: string
  icon: string
  label: string
  active?: boolean
  disabled?: boolean
  run: () => void
}

/** Toolbar by groups; "compact" (comments) has fewer. */
const groups = computed<Tool[][]>(() => {
  const current = editor.value

  if (!current) {
    return []
  }

  const marks: Tool[] = [
    { key: 'bold', icon: 'bold', label: t('richText.bold'), active: current.isActive('bold'), run: () => current.chain().focus().toggleBold().run() },
    { key: 'italic', icon: 'italic', label: t('richText.italic'), active: current.isActive('italic'), run: () => current.chain().focus().toggleItalic().run() }
  ]
  const lists: Tool[] = [
    { key: 'bulletList', icon: 'list-ul', label: t('richText.bulletList'), active: current.isActive('bulletList'), run: () => current.chain().focus().toggleBulletList().run() },
    { key: 'orderedList', icon: 'list-ol', label: t('richText.orderedList'), active: current.isActive('orderedList'), run: () => current.chain().focus().toggleOrderedList().run() }
  ]

  if (props.compact) {
    return [marks, lists]
  }

  return [
    [
      { key: 'undo', icon: 'arrow-rotate-left', label: t('richText.undo'), disabled: !current.can().undo(), run: () => current.chain().focus().undo().run() },
      { key: 'redo', icon: 'arrow-rotate-right', label: t('richText.redo'), disabled: !current.can().redo(), run: () => current.chain().focus().redo().run() }
    ],
    [
      { key: 'h2', icon: 'h2', label: t('richText.heading2'), active: current.isActive('heading', { level: 2 }), run: () => current.chain().focus().toggleHeading({ level: 2 }).run() },
      { key: 'h3', icon: 'h3', label: t('richText.heading3'), active: current.isActive('heading', { level: 3 }), run: () => current.chain().focus().toggleHeading({ level: 3 }).run() }
    ],
    marks,
    [...lists, { key: 'blockquote', icon: 'block-quote', label: t('richText.blockquote'), active: current.isActive('blockquote'), run: () => current.chain().focus().toggleBlockquote().run() }]
  ]
})
</script>

<template>
  <div class="rich-text rich-text-editor">
    <div
      v-if="editor"
      class="rich-text-toolbar"
      role="toolbar"
      :aria-label="t('richText.toolbar')"
    >
      <div
        v-for="(group, index) in groups"
        :key="index"
        class="rich-text-group"
      >
        <wa-button
          v-for="tool in group"
          :key="tool.key"
          :appearance="tool.active ? 'filled' : 'plain'"
          variant="neutral"
          size="small"
          :title="tool.label"
          :aria-label="tool.label"
          :aria-pressed="tool.active === undefined ? undefined : tool.active"
          :disabled="tool.disabled"
          @click="tool.run"
        >
          <wa-icon :name="tool.icon"></wa-icon>
        </wa-button>
      </div>
      <div class="rich-text-group">
        <RichTextLinkPopover :editor="editor" />
      </div>
    </div>
    <EditorContent
      :editor="editor"
      class="rich-text-area"
      :data-compact="compact ? '' : undefined"
    />
  </div>
</template>

<style scoped>
.rich-text-editor {
  inline-size: 100%;
  border: var(--wa-form-control-border-width) var(--wa-form-control-border-style) var(--wa-form-control-border-color);
  border-radius: var(--wa-form-control-border-radius);
  background-color: var(--wa-form-control-background-color);
}

.rich-text-editor:focus-within {
  outline: var(--wa-focus-ring);
  outline-offset: var(--wa-focus-ring-offset);
}

.rich-text-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wa-space-2xs) var(--wa-space-s);
  padding: var(--wa-space-3xs);
  border-block-end: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
}

.rich-text-group {
  display: flex;
  gap: var(--wa-space-3xs);
}

.rich-text-area {
  padding: var(--wa-space-xs) var(--wa-space-s);
  min-block-size: 8rem;
}

.rich-text-area[data-compact] {
  min-block-size: 5rem;
}

.rich-text-area :deep(.ProseMirror) {
  min-block-size: inherit;
}
</style>
