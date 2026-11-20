import { diffLines, diffWords } from 'diff'

/** One piece of a line: text, and whether it is what changed inside a changed line. */
export interface DiffSegment {
  text: string
  changed: boolean
}

/** One line of the comparison: unchanged, added (+) or removed (−). */
export interface DiffRow {
  kind: 'same' | 'added' | 'removed'
  segments: DiffSegment[]
}

/** The lines of a block of text from the diff, without the empty piece after the last line break. */
function linesOf(text: string): string[] {
  const lines = text.split('\n')

  if (lines.at(-1) === '') {
    lines.pop()
  }

  return lines
}

/** The words that changed between two lines, for the removed and the added side. */
function wordSegments(removed: string, added: string): { removed: DiffSegment[], added: DiffSegment[] } {
  const removedSide: DiffSegment[] = []
  const addedSide: DiffSegment[] = []

  for (const part of diffWords(removed, added)) {
    if (part.added) {
      addedSide.push({ text: part.value, changed: true })
    } else if (part.removed) {
      removedSide.push({ text: part.value, changed: true })
    } else {
      removedSide.push({ text: part.value, changed: false })
      addedSide.push({ text: part.value, changed: false })
    }
  }

  return { removed: removedSide, added: addedSide }
}

/**
 * Compares two versions of a document's Markdown line by line; a removed line that is followed by an added one
 * (a changed line) is also compared word by word. The result is data for the page to show as text: nothing here
 * makes HTML.
 */
export function compareDocuments(older: string, newer: string): DiffRow[] {
  const parts = diffLines(older, newer)
  const rows: DiffRow[] = []

  for (let index = 0; index < parts.length; index++) {
    const part = parts[index]!
    const next = parts[index + 1]

    if (part.removed && next?.added) {
      const removedLines = linesOf(part.value)
      const addedLines = linesOf(next.value)
      const paired = Math.min(removedLines.length, addedLines.length)
      const removedRows: DiffRow[] = []
      const addedRows: DiffRow[] = []

      for (let line = 0; line < paired; line++) {
        const words = wordSegments(removedLines[line]!, addedLines[line]!)
        removedRows.push({ kind: 'removed', segments: words.removed })
        addedRows.push({ kind: 'added', segments: words.added })
      }
      removedRows.push(...removedLines.slice(paired).map(text => ({ kind: 'removed' as const, segments: [{ text, changed: true }] })))
      addedRows.push(...addedLines.slice(paired).map(text => ({ kind: 'added' as const, segments: [{ text, changed: true }] })))
      rows.push(...removedRows, ...addedRows)
      index++

      continue
    }

    const kind = part.added ? 'added' : part.removed ? 'removed' : 'same'
    rows.push(...linesOf(part.value).map(text => ({ kind: kind as DiffRow['kind'], segments: [{ text, changed: kind !== 'same' }] })))
  }

  return rows
}

/** Whether the comparison has any changed line. */
export function hasChanges(rows: readonly DiffRow[]): boolean {
  return rows.some(row => row.kind !== 'same')
}
