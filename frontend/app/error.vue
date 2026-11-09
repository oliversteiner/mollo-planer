<script setup lang="ts">
import type { NuxtError } from '#app'

const props = defineProps<{ error: NuxtError }>()
const { t, locale } = useI18n()

// app.vue is not rendered for the error page, so the theme is set here as well.
useHead({
  htmlAttrs: { lang: locale, class: 'wa-theme-matter wa-palette-mild wa-brand-purple' },
  title: () => String(props.error.statusCode)
})

const message = computed(() => {
  if (props.error.statusCode === 404) {
    return t('error.notFound')
  }

  return props.error.statusCode === 503 ? t('error.unavailable') : t('error.generic')
})
</script>

<template>
  <main class="error-page">
    <wa-card class="error-card">
      <h1
        slot="header"
        class="wa-heading-l"
      >
        {{ error.statusCode }}
      </h1>
      <div class="wa-stack wa-gap-m">
        <p>{{ message }}</p>
        <div>
          <wa-button
            variant="brand"
            @click="clearError({ redirect: '/' })"
          >
            {{ t('error.backHome') }}
          </wa-button>
        </div>
      </div>
    </wa-card>
  </main>
</template>

<style scoped>
.error-page {
  display: flex;
  align-items: center;
  justify-content: center;
  min-block-size: 100dvh;
  padding: var(--wa-space-m);
}

.error-card {
  inline-size: 100%;
  max-inline-size: 28rem;
}
</style>
