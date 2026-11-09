<script setup lang="ts">
const { locale, t } = useI18n()

useHead({
  htmlAttrs: {
    lang: locale,
    // Theme Matter with its palette and brand colour (assets/css/webawesome.css).
    class: 'wa-theme-matter wa-palette-mild wa-brand-purple'
  },
  titleTemplate: title => (title ? `${title} · ${t('app.name')}` : t('app.name')),
  meta: [{ name: 'viewport', content: 'width=device-width, initial-scale=1' }],
  link: [{ rel: 'icon', href: '/favicon.ico' }]
})
</script>

<template>
  <NuxtLayout>
    <NuxtPage />
  </NuxtLayout>
</template>
