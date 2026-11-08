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
const { load, logout } = useViewer()

useHead({ title: () => t('auth.setup.title') })

const method = ref<'app' | 'passkey' | null>(null)

async function done(): Promise<void> {
  const problem = await load()

  if (problem === null) {
    await navigateTo(safeRedirect(route.query.redirect))
  }
}
</script>

<template>
  <UPageCard
    :title="t('auth.setup.title')"
    :description="t('auth.setup.description')"
  >
    <div
      v-if="method === null"
      class="grid gap-2"
    >
      <UButton
        icon="i-fadl-fingerprint"
        :label="t('auth.setup.passkey')"
        size="lg"
        block
        @click="method = 'passkey'"
      />
      <UButton
        icon="i-fadl-mobile-screen"
        :label="t('auth.setup.app')"
        color="neutral"
        variant="outline"
        size="lg"
        block
        @click="method = 'app'"
      />
    </div>

    <SecurityPasskeyAdd
      v-else-if="method === 'passkey'"
      @added="done"
    />
    <SecurityTwoFactorSetup
      v-else
      @done="done"
    />

    <div class="mt-6 flex justify-between gap-2 text-sm">
      <UButton
        v-if="method !== null"
        :label="t('auth.challenge.back')"
        variant="link"
        color="neutral"
        @click="method = null"
      />
      <UButton
        :label="t('auth.logout')"
        variant="link"
        color="neutral"
        class="ms-auto"
        @click="logout"
      />
    </div>
  </UPageCard>
</template>
