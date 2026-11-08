<script setup lang="ts">
import * as uiLocales from '@nuxt/ui/locale'

const { t, locale, setLocale } = useI18n()

const currentLocale = computed({
  get: () => locale.value,
  set: (code: string) => setLocale(code as 'de' | 'en')
})
</script>

<template>
  <div class="flex min-h-screen flex-col">
    <header class="flex items-center justify-between gap-2 p-4">
      <span class="font-semibold text-highlighted">{{ t('app.name') }}</span>
      <div class="flex items-center gap-2">
        <ULocaleSelect
          v-model="currentLocale"
          :locales="[uiLocales.de, uiLocales.en]"
          :aria-label="t('settings.language')"
          class="w-36"
        />
        <UColorModeButton :aria-label="t('settings.colorMode')" />
      </div>
    </header>

    <main class="flex flex-1 items-start justify-center px-4 py-8 sm:items-center">
      <div class="w-full max-w-sm">
        <slot />
      </div>
    </main>
    <PasswordPromptModal />
  </div>
</template>
