// Puts the address of this installation's MCP server into the built MCP guide (.output/public/mcp: the two pages and
// llms.txt). The files in public/mcp carry a placeholder, so no real domain is in the public repository (Auftrag 56);
// the address comes from NUXT_PUBLIC_API_BASE, the API the build talks to anyway (deploy.yml: vars.API_BASE; locally
// frontend/.env, as Nuxt reads it).
// Runs after every build (`postbuild`). Without NUXT_PUBLIC_API_BASE the placeholder stays and a warning says so.
import { readFileSync, writeFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'

export const PLACEHOLDER = 'https://api.planer.example.com'

/** The text with the placeholder replaced by the base address of the API (no trailing slash). */
export function withAddress(text, apiBase) {
  return text.replaceAll(PLACEHOLDER, apiBase.replace(/\/+$/, ''))
}

const isMain = process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]

if (isMain) {
  // Like Nuxt: the variable of the environment first, else frontend/.env (local builds).
  if (!process.env.NUXT_PUBLIC_API_BASE) {
    try {
      process.loadEnvFile(fileURLToPath(new URL('../.env', import.meta.url)))
    } catch {
      // No .env: the warning below says what is missing.
    }
  }

  const apiBase = (process.env.NUXT_PUBLIC_API_BASE ?? '').trim()

  if (!/^https?:\/\//.test(apiBase)) {
    console.warn('mcp-address: NUXT_PUBLIC_API_BASE is not set, the MCP guide keeps its placeholder address.')
  } else {
    for (const file of ['mcp/index.html', 'mcp/de/index.html', 'mcp/llms.txt']) {
      const path = fileURLToPath(new URL(`../.output/public/${file}`, import.meta.url))
      writeFileSync(path, withAddress(readFileSync(path, 'utf8'), apiBase))
    }
  }
}
