import { execFileSync } from 'node:child_process'
import { mkdtempSync, mkdirSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'
// @ts-expect-error plain JavaScript module of the scripts folder
import { findPrivateTerms, parseTerms } from '../../scripts/check-no-private-terms.mjs'

/** A throwaway repository; the terms of these tests are made up. */
function repository(files: Record<string, string | Buffer>): string {
  const root = mkdtempSync(join(tmpdir(), 'terms-'))
  execFileSync('git', ['init', '--quiet'], { cwd: root })

  for (const [path, content] of Object.entries(files)) {
    mkdirSync(join(root, path, '..'), { recursive: true })
    writeFileSync(join(root, path), content)
  }
  execFileSync('git', ['add', '-A'], { cwd: root })

  return root
}

describe('guard against private terms in the public repository (Auftrag 56)', () => {
  it('reads the list from the variable, comma-separated, without case', () => {
    expect(parseTerms(' Secret-Host.test , ,ACME Hosting ')).toEqual(['secret-host.test', 'acme hosting'])
    expect(parseTerms(undefined)).toEqual([])
  })

  it('names file and line of every place, without case', () => {
    const root = repository({
      'docs/deploy.md': 'Line one\nServer: app.Secret-Host.test\n',
      'README.md': 'Hosted at ACME hosting.',
      'clean.txt': 'nothing here'
    })

    expect(findPrivateTerms(root, ['secret-host.test', 'acme hosting'])).toEqual([
      { file: 'README.md', line: 1, term: 'acme hosting' },
      { file: 'docs/deploy.md', line: 2, term: 'secret-host.test' }
    ])
  })

  it('skips files that are not text, and finds nothing without terms', () => {
    const root = repository({ 'image.png': Buffer.from([0x89, 0x50, 0x00, 0x73, 0x65, 0x63]), 'a.txt': 'secret-host.test' })

    expect(findPrivateTerms(root, ['sec'])).toEqual([{ file: 'a.txt', line: 1, term: 'sec' }])
    expect(findPrivateTerms(root, [])).toEqual([])
  })
})
