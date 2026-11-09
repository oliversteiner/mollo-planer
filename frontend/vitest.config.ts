import { defineVitestProject } from '@nuxt/test-utils/config'
import { defineConfig } from 'vitest/config'

/**
 * unit: plain functions (tests/unit), fast, without Nuxt.
 * nuxt: components and route middleware in a Nuxt environment (tests/nuxt).
 */
export default defineConfig({
  test: {
    projects: [
      {
        test: {
          name: 'unit',
          include: ['tests/unit/**/*.test.ts'],
          environment: 'node'
        }
      },
      await defineVitestProject({
        test: {
          name: 'nuxt',
          include: ['tests/nuxt/**/*.test.ts'],
          // The first mount of a file starts Nuxt and the Web Awesome
          // components; on a busy machine that takes longer than 5 s.
          testTimeout: 15000,
          environment: 'nuxt',
          environmentOptions: {
            nuxt: { domEnvironment: 'happy-dom' }
          }
        }
      })
    ]
  }
})
