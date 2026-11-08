<script setup lang="ts">
/**
 * Placeholder until step 10 of the frontend plan (set up the
 * authenticator app or a passkey here). The API is locked until then.
 */
definePageMeta({ layout: 'auth' })

const { t } = useI18n()
const { logout } = useViewer()

useHead({ title: () => t('auth.setup.title') })
</script>

<template>
  <UPageCard
    :title="t('auth.setup.title')"
    :description="t('auth.setup.description')"
  >
    <UAlert
      color="info"
      variant="subtle"
      :title="t('auth.setup.comingSoon')"
    />
    <UButton
      :label="t('auth.logout')"
      color="neutral"
      variant="outline"
      class="mt-4"
      block
      @click="logout"
    />
  </UPageCard>
</template>
