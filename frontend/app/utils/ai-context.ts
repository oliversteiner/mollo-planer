/** The limit of text and file together (backend: PlanerLimits::AI_CONTEXT_CHARACTERS). */
export const AI_CONTEXT_LIMIT = 20_000

/** Largest file the server takes (100 KB); checked here first so nothing larger is sent. */
export const AI_CONTEXT_FILE_BYTES = 100 * 1024

/** The characters of text and file together, counted like the server (code points). */
export function contextCharacters(text: string, fileText: string | null | undefined): number {
  return [...text].length + [...(fileText ?? '')].length
}

export function fileProblem(file: { name: string, size: number }): 'type' | 'size' | null {
  if (!/\.(txt|md)$/i.test(file.name)) {
    return 'type'
  }

  return file.size > AI_CONTEXT_FILE_BYTES ? 'size' : null
}
