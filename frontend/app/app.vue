<script setup lang="ts">
import * as uiLocales from '@nuxt/ui/locale'

const { locale, t } = useI18n()

// Nuxt UI translates its own texts (close, search, ...) with the same locale.
const uiLocale = computed(() => uiLocales[locale.value as keyof typeof uiLocales])

useHead({
  htmlAttrs: { lang: locale },
  titleTemplate: title => (title ? `${title} · ${t('app.name')}` : t('app.name')),
  meta: [{ name: 'viewport', content: 'width=device-width, initial-scale=1' }],
  link: [{ rel: 'icon', href: '/favicon.ico' }]
})
</script>

<template>
  <UApp :locale="uiLocale">
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
  </UApp>
</template>
