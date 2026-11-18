<script setup lang="ts">
import { pendingAccountPath, safeRedirect } from '~/utils/api'

/**
 * First setup after the invitation: an account needs an authenticator app
 * or a passkey before the API opens. The first factor also counts as
 * strong login for this session (backend), so the app opens right away.
 */
definePageMeta({ layout: 'auth' })

const { t } = useI18n()
const route = useRoute()
const toast = useToast()
const { load, logout } = useViewer()

useHead({ title: () => t('auth.setup.title') })

const method = ref<'app' | 'passkey' | null>(null)

/**
 * After the first factor was set up: ask the API again and go on. Every answer leads
 * somewhere or says why not; none is ignored (the viewer query is silent, so the
 * app-wide handler in plugins/api-errors.ts does not speak up here).
 */
async function done(): Promise<void> {
  const problem = await load()

  if (problem === null) {
    await navigateTo(safeRedirect(route.query.redirect))
  } else if (pendingAccountPath(problem)) {
    await navigateTo(pendingAccountPath(problem))
  } else if (problem === 'unauthenticated' || problem === 'reauth-required') {
    // The session does not count as strong (or is gone): log in with the new factor.
    toast.add({ title: t('auth.setup.signInAgain'), color: 'info' })
    await navigateTo({ path: '/login', query: { redirect: safeRedirect(route.query.redirect) } })
  } else if (problem !== 'setup-required') {
    // Same messages as the app-wide handler; the page stays and the person can try again.
    const failures = {
      unauthorized: { key: 'apiError.unauthorized', color: 'error' },
      csrf: { key: 'apiError.csrf', color: 'warning' },
      network: { key: 'apiError.network', color: 'error' }
    } as const
    const failure = failures[problem as keyof typeof failures] ?? { key: 'apiError.unknown', color: 'error' }

    toast.add({ title: t(failure.key), color: failure.color })
  }
}
</script>

<template>
  <wa-card>
    <h1
      slot="header"
      class="wa-heading-l"
    >
      {{ t('auth.setup.title') }}
    </h1>

    <div class="wa-stack wa-gap-m">
      <p class="wa-body-s wa-color-text-quiet">
        {{ t('auth.setup.description') }}
      </p>

      <div
        v-if="method === null"
        class="wa-stack wa-gap-s"
      >
        <wa-button
          class="setup-choice"
          variant="brand"
          size="l"
          style="width: 100%"
          @click="method = 'passkey'"
        >
          <wa-icon
            slot="start"
            name="fingerprint"
          ></wa-icon>
          {{ t('auth.setup.passkey') }}
        </wa-button>
        <wa-button
          class="setup-choice"
          appearance="outlined"
          variant="neutral"
          size="l"
          style="width: 100%"
          @click="method = 'app'"
        >
          <wa-icon
            slot="start"
            name="mobile-screen"
          ></wa-icon>
          {{ t('auth.setup.app') }}
        </wa-button>
      </div>

      <SecurityPasskeyAdd
        v-else-if="method === 'passkey'"
        @added="done"
      />
      <SecurityTwoFactorSetup
        v-else
        @done="done"
      />

      <div class="wa-split wa-gap-s">
        <wa-button
          v-if="method !== null"
          appearance="plain"
          variant="neutral"
          @click="method = null"
        >
          {{ t('auth.challenge.back') }}
        </wa-button>
        <wa-button
          appearance="plain"
          variant="neutral"
          style="margin-inline-start: auto"
          @click="logout"
        >
          {{ t('auth.logout') }}
        </wa-button>
      </div>
    </div>
  </wa-card>
</template>

<style scoped>
/* Long labels wrap inside the button instead of running over its edge. */
.setup-choice::part(label) {
  white-space: normal;
  text-align: center;
}
</style>
