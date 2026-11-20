import { useMutation } from '@vue/apollo-composable'
import { exportDocumentPdfMutation } from '~/graphql/documents'
import { base64Blob, downloadBlob, downloadName, downloadText } from '~/utils/planer'

/**
 * The two exports of a document, for the menu and for the command palette (calm view): Markdown from the content
 * the page already has (in the browser, the same text the server keeps) and the PDF the server makes. While the
 * PDF is made `making` is true.
 */
export function useDocumentExport(document: () => { id: string, title: string, content: string } | null) {
  const { t } = useI18n()
  const toast = useToast()
  const { mutate: exportPdf } = useMutation(exportDocumentPdfMutation, { context: { silentErrors: true } })
  const making = ref(false)

  function markdown(): void {
    const current = document()

    if (current) {
      downloadText(downloadName(current.title, 'md', t('documents.fileFallback')), current.content, 'text/markdown;charset=utf-8')
    }
  }

  async function pdf(): Promise<void> {
    const current = document()

    if (!current || making.value) {
      return
    }
    making.value = true

    try {
      const result = await exportPdf({ id: current.id })
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

  return { markdown, pdf, making }
}
