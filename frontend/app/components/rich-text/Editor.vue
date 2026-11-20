<script setup lang="ts">
import { Placeholder } from '@tiptap/extensions'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import type { ActionMenuItem } from '~/components/ActionMenu.vue'
import { richTextExtensions, type RichTextProfile } from '~/utils/rich-text'

/**
 * Editor for descriptions, comments and documents: TipTap JSON in and out, only the
 * elements the server allows (see utils/rich-text.ts). "compact" gives a
 * smaller toolbar for comments. The profile "document" adds headings 1 to 4,
 * strike-through, code block, rule, task list and table. The content comes from
 * outside (v-model), so a later live editing does not need a new component.
 */
const props = withDefaults(defineProps<{
  placeholder?: string
  compact?: boolean
  profile?: RichTextProfile
}>(), {
  placeholder: undefined,
  compact: false,
  profile: 'description'
})

const model = defineModel<Record<string, unknown> | null>({ default: null })

const { t } = useI18n()

/** A value of null (for example a comment just sent) empties the editor. */
const EMPTY = { type: 'doc', content: [{ type: 'paragraph' }] }

const editor = useEditor({
  content: model.value ?? EMPTY,
  extensions: [...richTextExtensions(props.profile), Placeholder.configure({ placeholder: props.placeholder ?? '' })],
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

const isDocument = computed(() => props.profile === 'document')

/** The language of the code block the cursor is in; only the characters a language name has. */
const codeLanguage = computed({
  get: () => (editor.value?.getAttributes('codeBlock').language as string | null | undefined) ?? '',
  set: (value: string) => {
    editor.value?.chain().updateAttributes('codeBlock', { language: value.replace(/[^A-Za-z0-9_+#.-]/g, '').slice(0, 30) || null }).run()
  }
})

/** The table menu: insert one, or change the one the cursor is in. No merging of cells, the head row stays. */
const tableItems = computed<ActionMenuItem[][]>(() => {
  const current = editor.value

  if (!current) {
    return []
  }
  const inTable = current.isActive('table')

  return [
    [{ label: t('richText.tableInsert'), icon: 'table', disabled: inTable, onSelect: () => current.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run() }],
    [
      { label: t('richText.tableRowBefore'), icon: 'arrow-up-to-line', disabled: !inTable || !current.can().addRowBefore(), onSelect: () => current.chain().focus().addRowBefore().run() },
      { label: t('richText.tableRowAfter'), icon: 'arrow-down-to-line', disabled: !inTable, onSelect: () => current.chain().focus().addRowAfter().run() },
      { label: t('richText.tableColumnBefore'), icon: 'arrow-left-to-line', disabled: !inTable, onSelect: () => current.chain().focus().addColumnBefore().run() },
      { label: t('richText.tableColumnAfter'), icon: 'arrow-right-to-line', disabled: !inTable, onSelect: () => current.chain().focus().addColumnAfter().run() }
    ],
    [
      { label: t('richText.tableDeleteRow'), icon: 'trash', color: 'error', disabled: !inTable || !current.can().deleteRow(), onSelect: () => current.chain().focus().deleteRow().run() },
      { label: t('richText.tableDeleteColumn'), icon: 'trash', color: 'error', disabled: !inTable || !current.can().deleteColumn(), onSelect: () => current.chain().focus().deleteColumn().run() },
      { label: t('richText.tableDelete'), icon: 'trash', color: 'error', disabled: !inTable, onSelect: () => current.chain().focus().deleteTable().run() }
    ]
  ]
})

/** Toolbar by groups; "compact" (comments) has fewer. */
const groups = computed<Tool[][]>(() => {
  const current = editor.value

  if (!current) {
    return []
  }

  const marks: Tool[] = [
    { key: 'bold', icon: 'bold', label: t('richText.bold'), active: current.isActive('bold'), run: () => current.chain().focus().toggleBold().run() },
    { key: 'italic', icon: 'italic', label: t('richText.italic'), active: current.isActive('italic'), run: () => current.chain().focus().toggleItalic().run() },
    { key: 'code', icon: 'code', label: t('richText.code'), active: current.isActive('code'), run: () => current.chain().focus().toggleCode().run() }
  ]
  const lists: Tool[] = [
    { key: 'bulletList', icon: 'list-ul', label: t('richText.bulletList'), active: current.isActive('bulletList'), run: () => current.chain().focus().toggleBulletList().run() },
    { key: 'orderedList', icon: 'list-ol', label: t('richText.orderedList'), active: current.isActive('orderedList'), run: () => current.chain().focus().toggleOrderedList().run() }
  ]

  if (props.compact) {
    return [marks, lists]
  }

  if (isDocument.value) {
    return [
      [
        { key: 'undo', icon: 'arrow-rotate-left', label: t('richText.undo'), disabled: !current.can().undo(), run: () => current.chain().focus().undo().run() },
        { key: 'redo', icon: 'arrow-rotate-right', label: t('richText.redo'), disabled: !current.can().redo(), run: () => current.chain().focus().redo().run() }
      ],
      ([1, 2, 3, 4] as const).map(level => ({ key: `h${level}`, icon: `h${level}`, label: t('richText.headingLevel', { level }), active: current.isActive('heading', { level }), run: () => current.chain().focus().toggleHeading({ level }).run() })),
      [
        ...marks.slice(0, 2),
        { key: 'strike', icon: 'strikethrough', label: t('richText.strike'), active: current.isActive('strike'), run: () => current.chain().focus().toggleStrike().run() },
        marks[2]!
      ],
      [
        ...lists,
        { key: 'taskList', icon: 'list-check', label: t('richText.taskList'), active: current.isActive('taskList'), run: () => current.chain().focus().toggleTaskList().run() },
        { key: 'blockquote', icon: 'block-quote', label: t('richText.blockquote'), active: current.isActive('blockquote'), run: () => current.chain().focus().toggleBlockquote().run() }
      ],
      [
        { key: 'codeBlock', icon: 'square-code', label: t('richText.codeBlock'), active: current.isActive('codeBlock'), run: () => current.chain().focus().toggleCodeBlock().run() },
        { key: 'horizontalRule', icon: 'horizontal-rule', label: t('richText.horizontalRule'), run: () => current.chain().focus().setHorizontalRule().run() }
      ]
    ]
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
          size="s"
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
      <div
        v-if="isDocument"
        class="rich-text-group"
      >
        <ActionMenu
          :items="tableItems"
          :label="t('richText.table')"
          :text="t('richText.table')"
          icon="table"
          placement="bottom-start"
        />
        <FormInput
          v-if="editor.isActive('codeBlock')"
          v-model="codeLanguage"
          class="rich-text-language"
          :label="t('richText.codeLanguage')"
          :placeholder="t('richText.codeLanguage')"
          hide-label
          maxlength="30"
          size="s"
        />
      </div>
    </div>
    <EditorContent
      :editor="editor"
      class="rich-text-area"
      :data-compact="compact ? '' : undefined"
      :data-profile="profile"
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

.rich-text-language {
  inline-size: 8rem;
}

.rich-text-area {
  padding: var(--wa-space-xs) var(--wa-space-s);
  min-block-size: 8rem;
}

.rich-text-area[data-compact] {
  min-block-size: 5rem;
}

.rich-text-area[data-profile='document'] {
  min-block-size: 20rem;
}

.rich-text-area :deep(.ProseMirror) {
  min-block-size: inherit;
}
</style>
