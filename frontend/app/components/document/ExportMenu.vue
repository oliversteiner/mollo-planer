<script setup lang="ts">
import { useMutation } from '@vue/apollo-composable'
import type { ActionMenuItem } from '~/components/ActionMenu.vue'
import { exportDocumentPdfMutation } from '~/graphql/documents'
import { base64Blob, downloadBlob, downloadName, downloadText } from '~/utils/planer'

/**
 * Export of one document: Markdown from the content the page already has (in the browser, the same text the
 * server keeps), PDF made by the server (exportDocumentPdf). While the PDF is made, its entry is off and says so.
 */
const props = defineProps<{ document: { id: string, title: string, content: string } }>()

const { t } = useI18n()
const toast = useToast()
const { mutate: exportPdf } = useMutation(exportDocumentPdfMutation, { context: { silentErrors: true } })
const making = ref(false)

function markdown(): void {
  downloadText(downloadName(props.document.title, 'md', 'dokument'), props.document.content, 'text/markdown;charset=utf-8')
}

async function pdf(): Promise<void> {
  making.value = true

  try {
    const result = await exportPdf({ id: props.document.id })
    const file = result?.data?.exportDocumentPdf

    if (!file) {
      throw new Error('no file')
    }
    downloadBlob(file.fileName, base64Blob(file.contentBase64, file.mimeType))
  } catch {
    toast.add({ title: t('documents.exportFailed'), color: 'error' })
  } finally {
    making.value = false
  }
}

const items = computed<ActionMenuItem[]>(() => [
  { label: t('documents.exportMarkdown'), icon: 'file-lines', onSelect: markdown },
  { label: making.value ? t('documents.exportPdfMaking') : t('documents.exportPdf'), icon: 'file-pdf', disabled: making.value, onSelect: pdf }
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
