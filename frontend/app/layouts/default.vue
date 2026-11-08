<script setup lang="ts">
import type { NavigationMenuItem } from '@nuxt/ui'
import * as uiLocales from '@nuxt/ui/locale'

const { t, locale, setLocale } = useI18n()

const items = computed<NavigationMenuItem[]>(() => [
  { label: t('nav.dashboard'), icon: 'i-lucide-layout-dashboard', to: '/' }
])

const locales = [uiLocales.de, uiLocales.en]

const currentLocale = computed({
  get: () => locale.value,
  set: (code: string) => setLocale(code as 'de' | 'en')
})
</script>

<template>
  <UDashboardGroup>
    <UDashboardSidebar
      collapsible
      resizable
    >
      <template #header="{ collapsed }">
        <NuxtLink
          to="/"
          class="truncate font-semibold text-highlighted"
        >
          {{ collapsed ? t('app.name').charAt(0) : t('app.name') }}
        </NuxtLink>
      </template>

      <template #default="{ collapsed }">
        <UNavigationMenu
          :items="items"
          :collapsed="collapsed"
          :aria-label="t('nav.main')"
          orientation="vertical"
        />
      </template>

      <template #footer="{ collapsed }">
        <div
          v-if="!collapsed"
          class="flex w-full items-center gap-2"
        >
          <ULocaleSelect
            v-model="currentLocale"
            :locales="locales"
            :aria-label="t('settings.language')"
            class="flex-1"
          />
          <UColorModeButton :aria-label="t('settings.colorMode')" />
        </div>
        <UColorModeButton
          v-else
          :aria-label="t('settings.colorMode')"
        />
      </template>
    </UDashboardSidebar>

    <slot />
  </UDashboardGroup>
</template>
