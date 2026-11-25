import { fileURLToPath } from 'node:url'
import { resolveIconSource } from './scripts/icon-source.mjs'

// Icons: Font Awesome Pro "Duotone Light" from frontend/vendor (private repository, `npm run icons:fetch`), else
// Font Awesome Free. The source and the style are decided in scripts/icon-source.mjs. A deployment sets
// ICONS_REQUIRE_PRO=1 and stops here when the Pro icons are missing.
const iconSource = resolveIconSource(fileURLToPath(new URL('.', import.meta.url)), { requirePro: process.env.ICONS_REQUIRE_PRO === '1' })

// The Markdown view of a planer file is the one of tools/planer (docs/auftrag-m4.md §7): only
// modules without node: imports (render-planer, planer-words) are used in the browser.
const planerCore = fileURLToPath(new URL('../tools/planer/core', import.meta.url))

// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  // The browser tests (frontend/e2e) run their own dev server next to the one of the developer: another build
  // folder, so the two do not overwrite each other. Not set otherwise.
  ...(process.env.NUXT_BUILD_DIR ? { buildDir: process.env.NUXT_BUILD_DIR } : {}),

  modules: [
    '@nuxt/eslint',
    '@nuxt/fonts',
    '@nuxtjs/i18n'
  ],

  // Single-page app: built with "nuxt generate" to static files, because
  // the hosting (a web host) runs PHP only. All data comes from the API.
  ssr: false,

  devtools: {
    enabled: true
  },

  app: {
    head: {
      script: [
        {
          // Dark or light before the app starts (same rule as
          // composables/useColorScheme.ts), so a dark page does not flash white.
          tagPosition: 'head',
          innerHTML: `(function(){try{var p=localStorage.getItem('color-mode')||localStorage.getItem('nuxt-color-mode')||'system';var d=p==='dark'||(p!=='light'&&window.matchMedia('(prefers-color-scheme: dark)').matches);document.documentElement.classList.add(d?'wa-dark':'wa-light')}catch(e){}})()`
        }
      ]
    }
  },

  // Web Awesome (theme, native styles, utilities), then the own styles.
  css: ['~/assets/css/webawesome.css', '~/assets/css/base.css', '~/assets/css/rich-text.css', '~/assets/css/animations.css', '~/assets/css/zen.css'],

  // Web Awesome components are custom elements (wa-*), not Vue components.
  vue: {
    compilerOptions: {
      isCustomElement: tag => tag.startsWith('wa-')
    }
  },

  runtimeConfig: {
    public: {
      // Address of the Laravel backend; set it with NUXT_PUBLIC_API_BASE. Empty: the development server uses the
      // host of the page with port 8000, a build falls back to http://localhost:8000 (utils/api-base.ts).
      apiBase: '',
      // Version and style of the icon files (part of the icon URLs, see scripts/icon-source.mjs).
      iconVersion: iconSource.version,
      iconStyle: iconSource.style
    }
  },
  alias: {
    '#planer-core': planerCore
  },

  // Development server (not used by "nuxt generate" and never in production): reachable from the local
  // network, for example from a tablet at http://<IP of the Mac>:3000.
  devServer: {
    host: '0.0.0.0',
    port: 3000
  },

  compatibilityDate: '2026-06-30',

  nitro: {
    // The icon SVGs (/icons/<version>/<style>/<name>.svg), loaded one by one on demand by <ItemIcon> (icons chosen in
    // the app are stored as names, so they are not known at build time), and the search catalogue of the icon picker
    // (/icons/<version>/catalog.json). The version is in the URL, so the files may be cached for a long time.
    publicAssets: iconSource.assets.map(asset => ({ ...asset, maxAge: 60 * 60 * 24 * 365 }))
  },

  vite: {
    server: {
      fs: { allow: ['..'] },
      // The dev server answers to the IP of the Mac too (Vite blocks unknown host names otherwise).
      allowedHosts: true
    }
  },

  hooks: {
    // The theme "Matter" loads its fonts from fonts.bunny.net. The app makes
    // no requests to third parties, so that @import is dropped after
    // postcss-import has inlined the theme; @nuxt/fonts serves the fonts
    // instead (see "fonts" below).
    'vite:extendConfig'(config) {
      const plugins = (config.css as { postcss?: { plugins?: unknown } } | undefined)?.postcss?.plugins
      if (!Array.isArray(plugins)) {
        throw new TypeError('Unexpected PostCSS config: cannot drop the external font import of the Web Awesome theme.')
      }
      plugins.push({
        postcssPlugin: 'webawesome-no-external-fonts',
        AtRule: {
          import(node: { params: string, remove: () => void }) {
            if (node.params.includes('fonts.bunny.net')) node.remove()
          }
        }
      })
    }
  },

  eslint: {
    config: {
      stylistic: {
        commaDangle: 'never',
        braceStyle: '1tbs'
      }
    }
  },

  // Fonts of the Web Awesome theme Matter (see the font tokens in its CSS).
  // @nuxt/fonts downloads them at build time and serves them from the app,
  // so the browser makes no requests to a font provider.
  fonts: {
    families: [
      { name: 'Wix Madefor Text', global: true, weights: [400, 500, 600, 700, 800], styles: ['normal', 'italic'] },
      { name: 'Roboto Mono', global: true, weights: [400, 500, 700], styles: ['normal', 'italic'] },
      { name: 'Roboto Serif', global: true, weights: [400, 700], styles: ['normal', 'italic'] }
    ]
  },

  i18n: {
    locales: [
      { code: 'de', language: 'de-CH', name: 'Deutsch', file: 'de.json' },
      { code: 'en', language: 'en-GB', name: 'English', file: 'en.json' }
    ],
    defaultLocale: 'de',
    // No locale in the URL: the links in the mails (/invitation, /reset-password,
    // /projects/:id/kanban) must work as they are.
    strategy: 'no_prefix',
    detectBrowserLanguage: {
      useCookie: true,
      cookieKey: 'locale',
      fallbackLocale: 'de'
    }
  }
})
