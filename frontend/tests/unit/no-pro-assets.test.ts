import { execFileSync } from 'node:child_process'
import { mkdtempSync, mkdirSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'
// @ts-expect-error plain JavaScript module of the scripts folder
import { findProAssets } from '../../scripts/check-no-pro-assets.mjs'

function repository(files: Record<string, string>): string {
  const root = mkdtempSync(join(tmpdir(), 'guard-'))
  execFileSync('git', ['init', '--quiet'], { cwd: root })

  for (const [path, content] of Object.entries(files)) {
    mkdirSync(join(root, path, '..'), { recursive: true })
    writeFileSync(join(root, path), content)
  }
  execFileSync('git', ['add', '-A'], { cwd: root })

  return root
}

describe('guard against Font Awesome Pro files in the public repository', () => {
  it('finds a Pro icon by its header and everything in frontend/vendor/', () => {
    const root = repository({
      'frontend/public/rocket.svg': '<svg><!--! Font Awesome Pro 7.3.1 by @fontawesome --></svg>',
      'frontend/vendor/fontawesome/7.3.1/catalog.json': '{}',
      'frontend/public/logo.svg': '<svg></svg>'
    })

    expect(findProAssets(root).sort()).toEqual(['frontend/public/rocket.svg', 'frontend/vendor/fontawesome/7.3.1/catalog.json'])
  })

  it('finds nothing in a clean repository', () => {
    expect(findProAssets(repository({ 'frontend/public/logo.svg': '<svg></svg>' }))).toEqual([])
  })

  it('finds nothing in this repository', () => {
    expect(findProAssets(join(__dirname, '../../..'))).toEqual([])
  })
})
