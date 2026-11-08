<script setup lang="ts">
import type { NuxtError } from '#app'

const props = defineProps<{ error: NuxtError }>()
const { t } = useI18n()

const message = computed(() => (props.error.statusCode === 404 ? t('error.notFound') : t('error.generic')))
</script>

<template>
  <UApp>
    <UMain class="flex min-h-screen items-center justify-center p-4">
      <UPageCard
        :title="String(error.statusCode)"
        :description="message"
        class="max-w-md"
      >
        <UButton
          :label="t('error.backHome')"
          @click="clearError({ redirect: '/' })"
        />
      </UPageCard>
    </UMain>
  </UApp>
</template>
