import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import { RICH_TEXT_MARKS } from '../../app/utils/rich-text'

/** The marks the server accepts, read from backend/app/Support/RichTextDocument.php. */
function serverMarks(): string[] {
  const php = readFileSync(new URL('../../../backend/app/Support/RichTextDocument.php', import.meta.url), 'utf8')
  const match = php.match(/private const MARKS = \[([^\]]*)\];/)

  if (!match) {
    throw new Error('MARKS not found in RichTextDocument.php')
  }

  return [...match[1].matchAll(/'([a-z]+)'/g)].map(item => item[1]!)
}

describe('rich text whitelist', () => {
  it('has the same marks in the editor as on the server', () => {
    expect([...RICH_TEXT_MARKS].sort()).toEqual(serverMarks().sort())
  })
})
