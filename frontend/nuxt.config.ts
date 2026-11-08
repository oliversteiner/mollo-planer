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

  // Dev server only on this machine (WSL forwards localhost to Windows),
  // not reachable from the network.
  devServer: {
    host: '127.0.0.1'
  },

  compatibilityDate: '2026-06-30',

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
  }
})
