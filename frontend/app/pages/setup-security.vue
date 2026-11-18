<script setup lang="ts">
import { safeRedirect } from '~/utils/api'

/**
 * First setup after the invitation: an account needs an authenticator app
 * or a passkey before the API opens. The first factor also counts as
 * strong login for this session (backend), so the app opens right away.
 */
definePageMeta({ layout: 'auth' })

const { t } = useI18n()
const route = useRoute()
const toast = useToast()
const { logout } = useViewer()
const { proceed } = useAccountFlow()

useHead({ title: () => t('auth.setup.title') })

const method = ref<'app' | 'passkey' | null>(null)
// After the first passkey: a fixed step with the hint about a second way, not a toast that disappears.
const passkeyAdded = ref(false)

/**
 * After the first factor was set up: ask the API again and go on (see utils/account-outcome.ts: no
 * answer is ignored). A session that does not count as strong yet leads to the login with the new factor.
 */
async function done(): Promise<void> {
  await proceed('continued', {
    onSignedOut: async () => {
      toast.add({ title: t('auth.setup.signInAgain'), color: 'info' })
      await navigateTo({ path: '/login', query: { redirect: safeRedirect(route.query.redirect) } })
    }
  })
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

      <template v-else-if="method === 'passkey' && !passkeyAdded">
        <p class="wa-body-s wa-color-text-quiet">
          {{ t('auth.setup.passkeyExplain') }}
        </p>
        <SecurityPasskeyAdd @added="passkeyAdded = true" />
      </template>
      <div
        v-else-if="method === 'passkey'"
        class="wa-stack wa-gap-m"
      >
        <AppCallout
          variant="success"
          :title="t('auth.setup.passkeyDone')"
        />
        <AppCallout
          variant="brand"
          :title="t('auth.setup.secondFactorHint')"
        />
        <wa-button
          variant="brand"
          style="width: 100%"
          @click="done"
        >
          {{ t('auth.setup.continue') }}
        </wa-button>
        <wa-button
          appearance="outlined"
          variant="neutral"
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
      <SecurityTwoFactorSetup
        v-else
        @done="done"
      />

      <div class="wa-split wa-gap-s">
        <wa-button
          v-if="method !== null && !passkeyAdded"
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
