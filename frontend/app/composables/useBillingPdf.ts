import { base64Blob, downloadBlob } from '~/utils/planer'

/**
 * Downloads a PDF the API returns as Base64 (invoice, preview, sample; Auftrag 39), the way the export of documents
 * does. While one is made, `making` names it; a refusal (for example missing sender data) is said by the api plugin.
 */
export function useBillingPdf() {
  const making = ref<string | null>(null)

  async function download(key: string, request: () => Promise<{ fileName: string, mimeType: string, contentBase64: string } | null | undefined>): Promise<void> {
    if (making.value) {
      return
    }
    making.value = key

    try {
      const file = await request().catch(() => null)

      if (file) {
        downloadBlob(file.fileName, base64Blob(file.contentBase64, file.mimeType))
      }
    } finally {
      making.value = null
    }
  }

  return { making, download }
}
