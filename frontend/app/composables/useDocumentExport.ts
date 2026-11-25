import { useMutation, useQuery } from '@vue/apollo-composable'
import { exportDocumentPdfMutation, letterheadAvailableQuery } from '~/graphql/documents'
import { base64Blob, downloadBlob, downloadName, downloadText } from '~/utils/planer'

/**
 * The two exports of a document, for the menu and for the command palette (calm view): Markdown from the content
 * the page already has (in the browser, the same text the server keeps) and the PDF the server makes, on request with
 * the letterhead (Auftrag 39 F; offered only when one is uploaded, `withLetterhead`). While the PDF is made `making`
 * is true.
 */
export function useDocumentExport(document: () => { id: string, title: string, content: string } | null) {
  const { t } = useI18n()
  const toast = useToast()
  const { mutate: exportPdf } = useMutation(exportDocumentPdfMutation, { context: { silentErrors: true } })
  const making = ref(false)
  const { result: letterhead } = useQuery(letterheadAvailableQuery, null, { fetchPolicy: 'cache-first' })
  const letterheadAvailable = computed(() => letterhead.value?.letterheadAvailable ?? false)

  function markdown(): void {
    const current = document()

    if (current) {
      downloadText(downloadName(current.title, 'md', t('documents.fileFallback')), current.content, 'text/markdown;charset=utf-8')
    }
  }

  async function pdf(withLetterhead = false): Promise<void> {
    const current = document()

    if (!current || making.value) {
      return
    }
    making.value = true

    try {
      const result = await exportPdf({ id: current.id, withLetterhead })
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

  return { markdown, pdf, making, letterheadAvailable }
}
