import { defineConfig } from '@playwright/test'
import base from './playwright.config'

/**
 * The pictures of the manual (Auftrag 54 F): `npm run manual:shots`. The same throwaway backend, database, storage and
 * frontend as the browser tests (playwright.config.ts, e2e/support/environment.ts): never TEST or PROD, only test data.
 * Not part of the CI (running time); run by hand and by the writing persona after a milestone.
 */
const servers = Array.isArray(base.webServer) ? base.webServer : []

export default defineConfig({
  ...base,
  // The frontend without the Nuxt devtools (their bar would be in every picture).
  webServer: servers.map(server => (server.command.includes('nuxt') ? { ...server, env: { ...server.env, NUXT_DEVTOOLS: 'off' } } : server)),
  testDir: './e2e-manual',
  timeout: 120_000,
  reporter: [['list']],
  use: {
    ...base.use,
    viewport: { width: 1280, height: 800 },
    colorScheme: 'light',
    reducedMotion: 'reduce',
    trace: 'off',
    actionTimeout: 15_000
  }
})
