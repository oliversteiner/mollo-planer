import { defineConfig } from '@playwright/test'
import { backendEnv, BACKEND_URL, FRONTEND_URL } from './e2e/support/environment'

/**
 * Browser tests of the sign-in flow (docs/entwicklung.md, "Browser-Tests"). They start their OWN backend (port 8100,
 * a throwaway SQLite database, queue "sync", leak check off, mails to the local Mailpit) and their own dev server of
 * the frontend (port 3100). Nothing here touches the development database, a test server or production.
 */
export default defineConfig({
  testDir: './e2e',
  // One browser after the other: the tests share one database and the throttles of one address.
  workers: 1,
  fullyParallel: false,
  timeout: 60_000,
  expect: { timeout: 10_000 },
  reporter: [['list']],
  globalSetup: './e2e/support/global-setup.ts',
  use: {
    baseURL: FRONTEND_URL,
    locale: 'de-CH',
    trace: 'retain-on-failure'
  },
  projects: [{ name: 'chromium', use: { browserName: 'chromium' } }],
  webServer: [
    {
      // --no-reload: the environment below reaches the server unchanged (without it, artisan serve hands the variables of
      // backend/.env back to the server and could point it at the development database).
      command: 'php artisan serve --host=127.0.0.1 --port=8100 --no-reload',
      cwd: '../backend',
      url: `${BACKEND_URL}/up`,
      env: backendEnv,
      reuseExistingServer: false,
      timeout: 60_000
    },
    {
      command: 'npx nuxt dev --port 3100 --host 127.0.0.1',
      url: FRONTEND_URL,
      env: { NUXT_PUBLIC_API_BASE: BACKEND_URL, NUXT_BUILD_DIR: '.nuxt-e2e', NUXT_IGNORE_LOCK: '1' },
      reuseExistingServer: false,
      timeout: 180_000
    }
  ]
})
