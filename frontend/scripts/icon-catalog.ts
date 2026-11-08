import { existsSync, mkdirSync, readdirSync, readFileSync, writeFileSync } from 'node:fs'
import { join } from 'node:path'

/**
 * Search catalogue for the icon picker: every Duotone Light icon with its
 * label and search terms, as compact rows [name, label, terms]. Built from
 * the Font Awesome metadata (115 MB) once per package version, served as
 * /icons/catalog.json and loaded only when the picker opens.
 */
export function ensureIconCatalog(packageDir: string, outDir: string): void {
  const version = JSON.parse(readFileSync(join(packageDir, 'package.json'), 'utf8')).version as string
  const outFile = join(outDir, 'catalog.json')

  if (existsSync(outFile) && JSON.parse(readFileSync(outFile, 'utf8')).version === version) {
    return
  }

  const metadata = JSON.parse(readFileSync(join(packageDir, 'metadata/icon-families.json'), 'utf8')) as Record<string, { label?: string, search?: { terms?: string[] } }>
  const names = readdirSync(join(packageDir, 'svgs-full/duotone-light'))
    .filter(file => file.endsWith('.svg'))
    .map(file => file.slice(0, -4))
    .sort()

  const icons = names.map((name) => {
    const entry = metadata[name]

    return [name, entry?.label ?? name, (entry?.search?.terms ?? []).join(' ')]
  })

  mkdirSync(outDir, { recursive: true })
  writeFileSync(outFile, JSON.stringify({ version, icons }))
}
