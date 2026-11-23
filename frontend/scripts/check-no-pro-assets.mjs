#!/usr/bin/env node
// Guard (task 38): this repository is public, Font Awesome Pro files are licensed. Fails when a tracked file is a
// Pro icon (the header "Font Awesome Pro" is in the comment of every Pro SVG) or lies in frontend/vendor/.
import { execFileSync } from 'node:child_process'
import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const marker = /Font Awesome Pro/i

/**
 * @param {string} repoRoot the folder of the repository
 * @returns {string[]} tracked paths that must not be in the repository
 */
export function findProAssets(repoRoot) {
  const files = execFileSync('git', ['ls-files', '-z'], { cwd: repoRoot, maxBuffer: 64 * 1024 * 1024 })
    .toString('utf8')
    .split('\0')
    .filter(Boolean)

  return files.filter((file) => {
    if (file.startsWith('frontend/vendor/')) {
      return true
    }

    if (!file.endsWith('.svg') && !file.endsWith('.css') && !file.endsWith('.json')) {
      return false
    }

    try {
      return marker.test(readFileSync(join(repoRoot, file), 'utf8').slice(0, 400))
    } catch {
      return false
    }
  })
}

if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]) {
  const repoRoot = join(dirname(fileURLToPath(import.meta.url)), '../..')
  const found = findProAssets(repoRoot)

  if (found.length > 0) {
    console.error('Font Awesome Pro files are tracked in this public repository (frontend/vendor/ or a file with the header "Font Awesome Pro"):')
    found.slice(0, 20).forEach(file => console.error(`  ${file}`))
    process.exit(1)
  }

  console.log('No Font Awesome Pro files are tracked.')
}
