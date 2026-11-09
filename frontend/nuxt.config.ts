import { fileURLToPath } from 'node:url'
import { uiIcons } from './app/ui-icons'
import { ensureIconCatalog } from './scripts/icon-catalog'

// Font Awesome Pro, style "Duotone Light", fixed-width SVGs (640 grid).
const fontAwesome = fileURLToPath(new URL('./node_modules/@fortawesome/fontawesome-pro', import.meta.url))
const duotoneLight = `${fontAwesome}/svgs-full/duotone-light`

// Search catalogue for the icon picker (/icons/catalog.json).
const iconCatalog = fileURLToPath(new URL('./node_modules/.cache/icon-catalog', import.meta.url))
ensureIconCatalog(fontAwesome, iconCatalog)

// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  modules: [
    '@nuxt/eslint',
    '@nuxt/ui',
    '@nuxtjs/i18n'
  ],

  // Single-page app: built with "nuxt generate" to static files, because
  // the hosting (Hostpoint) runs PHP only. All data comes from the API.
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

  // Web Awesome first, so its cascade layers come before the Tailwind ones.
  css: ['~/assets/css/webawesome.css', '~/assets/css/main.css', '~/assets/css/rich-text.css'],

  // Web Awesome components are custom elements (wa-*), not Vue components.
  vue: {
    compilerOptions: {
      isCustomElement: tag => tag.startsWith('wa-')
    }
  },

  runtimeConfig: {
    public: {
      // Address of the Laravel backend; override with NUXT_PUBLIC_API_BASE.
      apiBase: 'http://localhost:8000'
    }
  },

  // Dev server only on this machine (WSL forwards localhost to Windows),
  // not reachable from the network.
  devServer: {
    host: '127.0.0.1'
  },

  compatibilityDate: '2026-06-30',

  nitro: {
    // All Duotone Light SVGs, loaded one by one on demand by <ItemIcon>
    // (icons chosen in the app are stored as names, so they are not known
    // at build time).
    publicAssets: [
      {
        dir: duotoneLight,
        baseURL: '/icons/duotone-light',
        maxAge: 60 * 60 * 24 * 30
      },
      {
        dir: iconCatalog,
        baseURL: '/icons'
      }
    ]
  },

  hooks: {
    // The app has its own useToast (composables/useToast.ts, Web Awesome).
    // It has the same name as the one of Nuxt UI, so it is registered with a
    // higher priority and wins the auto import.
    'imports:extend'(imports) {
      imports.push({
        name: 'useToast',
        from: fileURLToPath(new URL('./app/composables/useToast.ts', import.meta.url)),
        priority: 100
      })
    },

    // The theme "Matter" loads its fonts from fonts.bunny.net. The app makes
    // no requests to third parties, so that @import is dropped after
    // postcss-import has inlined the theme (the body font is Public Sans,
    // see assets/css/webawesome.css).
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
  },

  // Font Awesome Pro "Duotone Light" for every icon of the interface, as
  // the collection "fadl" (i-fadl-rocket). Only icons used in the code go
  // into the bundle; nothing is fetched from the Iconify API at runtime.
  icon: {
    customCollections: [
      {
        prefix: 'fadl',
        dir: duotoneLight
      }
    ],
    provider: 'none',
    fallbackToApi: false,
    clientBundle: {
      scan: true,
      // "i-fadl-check" -> "fadl:check"
      icons: Object.values(uiIcons).map(icon => icon.replace(/^i-fadl-/, 'fadl:')),
      includeCustomCollections: false
    }
  }
})
