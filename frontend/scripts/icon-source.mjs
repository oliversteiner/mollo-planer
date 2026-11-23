// Where the icons of the app come from. ONE place for source and style (task 38, task 36 D).
//
// - Pro: Font Awesome Pro "Duotone Light" from frontend/vendor/fontawesome/<version>/ (not in git: the repository is
//   public, the files are licensed; `npm run icons:fetch` gets them from the private repository, see pro-assets.json).
// - Free: Font Awesome Free (public npm package, style "solid") when the Pro files are missing: forks, outside
//   contributors, pull requests without secrets. Names that Free does not have show the replacement icon.
//
// A deployment never uses Free: with ICONS_REQUIRE_PRO=1 a missing Pro folder stops the build.
import { existsSync, mkdirSync, readdirSync, readFileSync, writeFileSync } from 'node:fs'
import { join } from 'node:path'

/** Search catalogue of the icon picker: [name, label, search terms] per icon. */
export function buildCatalog(names, metadata) {
  return names.map((name) => {
    const entry = metadata[name]

    return [name, entry?.label ?? name, (entry?.search?.terms ?? []).join(' ')]
  })
}

/**
 * @param {string} root the folder frontend/
 * @param {{ requirePro?: boolean }} [options]
 * @returns {{ kind: 'pro' | 'free', version: string, style: string, assets: { dir: string, baseURL: string }[] }}
 */
export function resolveIconSource(root, { requirePro = false } = {}) {
  const config = JSON.parse(readFileSync(join(root, 'pro-assets.json'), 'utf8'))
  const proFolder = join(root, 'vendor/fontawesome', config.version)

  if (existsSync(join(proFolder, 'duotone-light')) && existsSync(join(proFolder, 'catalog.json'))) {
    // The Pro folder keeps its layout (duotone-light/*.svg, catalog.json); both are served under /icons/<version>/.
    return {
      kind: 'pro',
      version: config.version,
      style: 'duotone-light',
      assets: [{ dir: proFolder, baseURL: `/icons/${config.version}` }]
    }
  }

  if (requirePro) {
    throw new Error(
      `The Font Awesome Pro icons ${config.version} are missing in ${proFolder}. A deployment must not use Font Awesome Free. `
      + 'Run `npm run icons:fetch` (access to the private repository ' + config.repository + ').'
    )
  }

  return freeSource(root)
}

function freeSource(root) {
  const packageDir = join(root, 'node_modules/@fortawesome/fontawesome-free')

  if (!existsSync(packageDir)) {
    throw new Error('Neither the Font Awesome Pro icons (npm run icons:fetch) nor @fortawesome/fontawesome-free (npm ci) are installed.')
  }

  const version = `${JSON.parse(readFileSync(join(packageDir, 'package.json'), 'utf8')).version}-free`
  const svgDir = join(packageDir, 'svgs-full/solid')
  const catalogDir = join(root, 'node_modules/.cache/icon-catalog', version)
  const catalogFile = join(catalogDir, 'catalog.json')

  if (!existsSync(catalogFile)) {
    const metadata = JSON.parse(readFileSync(join(packageDir, 'metadata/icon-families.json'), 'utf8'))
    const names = readdirSync(svgDir).filter(file => file.endsWith('.svg')).map(file => file.slice(0, -4)).sort()
    mkdirSync(catalogDir, { recursive: true })
    writeFileSync(catalogFile, JSON.stringify({ version, icons: buildCatalog(names, metadata) }))
  }

  return {
    kind: 'free',
    version,
    style: 'solid',
    assets: [
      { dir: svgDir, baseURL: `/icons/${version}/solid` },
      { dir: catalogDir, baseURL: `/icons/${version}` }
    ]
  }
}
