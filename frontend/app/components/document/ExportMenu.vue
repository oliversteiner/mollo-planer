<script setup lang="ts">
import type { ActionMenuItem } from '~/components/ActionMenu.vue'

/**
 * Export of one document (Markdown, PDF); the work is in useDocumentExport, which the command palette uses too.
 * While the PDF is made, its entry is off and says so.
 */
const props = defineProps<{ document: { id: string, title: string, content: string } }>()

const { t } = useI18n()
const { markdown, pdf, making } = useDocumentExport(() => props.document)

const items = computed<ActionMenuItem[]>(() => [
  { label: t('documents.exportMarkdown'), icon: 'file-lines', onSelect: markdown },
  { label: making.value ? t('documents.exportPdfMaking') : t('documents.exportPdf'), icon: 'file-pdf', disabled: making.value, onSelect: () => { void pdf() } }
])
</script>

<template>
  <ActionMenu
    :items="items"
    :label="t('documents.export')"
    :text="t('documents.export')"
    icon="file-export"
    size="m"
  />
</template>
