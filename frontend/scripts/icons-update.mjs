#!/usr/bin/env node
// Makes the Font Awesome Pro icon set for the PRIVATE repository (oliversteiner/mollo-pro-assets), never for this one:
// this repository is public and the files are licensed (task 38).
//
//   npm run icons:update -- <folder of the unpacked "For the Web" ZIP> <working copy of mollo-pro-assets>
//
// Writes <working copy>/fontawesome/<version>/ with only the style Duotone Light (svgs-full/duotone-light), the search
// catalogue of the icon picker (from metadata/icon-families.json), the licence and a README. Then commit and tag
// (fontawesome-<version>) in the private repository and set `version` and `ref` in frontend/pro-assets.json.
import { copyFileSync, existsSync, mkdirSync, readdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { buildCatalog } from './icon-source.mjs'

const source = process.argv[2] ? resolve(process.argv[2]) : null
const repoDir = process.argv[3] ? resolve(process.argv[3]) : null

if (!source || !repoDir || !existsSync(join(source, 'svgs-full/duotone-light'))) {
  console.error('Usage: npm run icons:update -- <folder of the unpacked Font Awesome Pro "For the Web" ZIP> <working copy of mollo-pro-assets>')
  console.error('The first folder must contain svgs-full/duotone-light and metadata/icon-families.json.')
  process.exit(1)
}

if (resolve(repoDir).startsWith(resolve(dirname(fileURLToPath(import.meta.url)), '../..'))) {
  console.error('The target must not be inside this (public) repository.')
  process.exit(1)
}

// The ZIP has no package.json; the version is in the header of css/all.css ("Font Awesome Pro 7.3.1").
const header = readFileSync(join(source, 'css/all.css'), 'utf8').slice(0, 400)
const version = header.match(/Font Awesome Pro (\d+\.\d+\.\d+)/)?.[1]

if (!version) {
  console.error('Could not read the version from the header of css/all.css (expected "Font Awesome Pro x.y.z").')
  process.exit(1)
}

const target = join(repoDir, 'fontawesome', version)
const svgDir = join(source, 'svgs-full/duotone-light')
const names = readdirSync(svgDir).filter(file => file.endsWith('.svg')).map(file => file.slice(0, -4)).sort()

rmSync(target, { recursive: true, force: true })
mkdirSync(join(target, 'duotone-light'), { recursive: true })

for (const name of names) {
  copyFileSync(join(svgDir, `${name}.svg`), join(target, 'duotone-light', `${name}.svg`))
}

const metadata = JSON.parse(readFileSync(join(source, 'metadata/icon-families.json'), 'utf8'))
writeFileSync(join(target, 'catalog.json'), JSON.stringify({ version, icons: buildCatalog(names, metadata) }))
copyFileSync(join(source, 'LICENSE.txt'), join(target, 'LICENSE.txt'))

writeFileSync(join(target, 'README.md'), `# Font Awesome Pro ${version} (Duotone Light)

A subset of the commercial package Font Awesome Pro, licensed to Mollo. **Private repository only.** It must never
go into a public repository.

- \`duotone-light/*.svg\`: ${names.length} icons of the style Duotone Light
- \`catalog.json\`: search catalogue of the icon picker (name, label, search terms)
- \`LICENSE.txt\`: licence of the package

Made with \`npm run icons:update\` of the Mollo Planer (frontend/scripts/icons-update.mjs) from the "For the Web" ZIP.
`)

console.log(`Font Awesome Pro ${version}: ${names.length} icons written to ${target}`)
console.log(`Next: commit and tag "fontawesome-${version}" in ${repoDir}, then set version and ref in frontend/pro-assets.json.`)
