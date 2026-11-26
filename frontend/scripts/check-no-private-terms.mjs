#!/usr/bin/env node
// Guard (Auftrag 56): this repository is public. Domains, addresses, the web host and details of the server of the own
// installation must not be in it. Fails with file and line for every tracked file that contains one of the terms.
// The terms are NOT in the repository (else they would be in it after all): they come from the variable PRIVATE_TERMS
// (comma-separated; GitHub: vars.PRIVATE_TERMS, locally an environment variable). Without it: a warning, no failure.
// next-work/ (not tracked anyway) and .git are never searched; files that are not text (with a NUL byte) are skipped.
import { execFileSync } from 'node:child_process'
import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

/**
 * @param {string | undefined} value the content of PRIVATE_TERMS
 * @returns {string[]} the terms, trimmed, without empty ones, in lower case
 */
export function parseTerms(value) {
  return (value ?? '').split(',').map(term => term.trim().toLowerCase()).filter(Boolean)
}

/**
 * @param {string} repoRoot the folder of the repository
 * @param {string[]} terms terms in lower case
 * @returns {{ file: string, line: number, term: string }[]} every place a term is found
 */
export function findPrivateTerms(repoRoot, terms) {
  if (terms.length === 0) {
    return []
  }

  const files = execFileSync('git', ['ls-files', '-z'], { cwd: repoRoot, maxBuffer: 64 * 1024 * 1024 })
    .toString('utf8')
    .split('\0')
    .filter(file => file && !file.startsWith('next-work/'))
  const found = []

  for (const file of files) {
    let content

    try {
      content = readFileSync(join(repoRoot, file))
    } catch {
      continue
    }

    if (content.includes(0)) {
      continue
    }

    content.toString('utf8').split('\n').forEach((text, index) => {
      const lower = text.toLowerCase()

      for (const term of terms) {
        if (lower.includes(term)) {
          found.push({ file, line: index + 1, term })
        }
      }
    })
  }

  return found
}

if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]) {
  const repoRoot = join(dirname(fileURLToPath(import.meta.url)), '../..')
  const terms = parseTerms(process.env.PRIVATE_TERMS)

  if (terms.length === 0) {
    console.warn('PRIVATE_TERMS is not set: no search for private terms (set it in GitHub as a variable, locally in the environment).')
    process.exit(0)
  }

  const found = findPrivateTerms(repoRoot, terms)

  if (found.length > 0) {
    // The term itself is not printed: the log of a public repository is public as well.
    console.error('Private terms are in the public repository (file:line, term number in PRIVATE_TERMS):')
    found.slice(0, 50).forEach(place => console.error(`  ${place.file}:${place.line} (term ${terms.indexOf(place.term) + 1})`))
    process.exit(1)
  }

  console.log(`No private terms in the repository (${terms.length} checked).`)
}
