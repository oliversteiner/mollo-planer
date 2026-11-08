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

  css: ['~/assets/css/main.css'],

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

  eslint: {
    config: {
      stylistic: {
        commaDangle: 'never',
        braceStyle: '1tbs'
      }
    }
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
