<script setup lang="ts">
import type { DropdownMenuItem, NavigationMenuItem } from '@nuxt/ui'
import * as uiLocales from '@nuxt/ui/locale'

const { t, locale, setLocale } = useI18n()
const { viewer, logout } = useViewer()

const userMenu = computed<DropdownMenuItem[][]>(() => [
  [{ label: viewer.value?.email ?? '', type: 'label' }],
  [{ label: t('profile.title'), icon: 'i-fadl-user-gear', to: '/profile' }],
  [{ label: t('auth.logout'), icon: 'i-fadl-right-from-bracket', onSelect: () => logout() }]
])

const items = computed<NavigationMenuItem[][]>(() => [[
  { label: t('nav.dashboard'), icon: 'i-fadl-gauge-high', to: '/', exact: true },
  { label: t('nav.projects'), icon: 'i-fadl-folder-open', to: '/projects' },
  { label: t('nav.myTasks'), icon: 'i-fadl-square-check', to: '/my-tasks' }
], viewer.value?.isAdmin
  ? [
      { label: t('nav.admin'), type: 'label' },
      { label: t('nav.users'), icon: 'i-fadl-users', to: '/admin/users' },
      { label: t('nav.appSettings'), icon: 'i-fadl-sliders', to: '/admin/settings' }
    ]
  : []])

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
        <div class="flex w-full flex-col gap-2">
          <UDropdownMenu
            v-if="viewer"
            :items="userMenu"
            :content="{ align: 'start', side: 'top' }"
          >
            <UButton
              color="neutral"
              variant="ghost"
              :aria-label="t('auth.userMenu')"
              class="w-full"
              :square="collapsed"
            >
              <UAvatar
                :src="viewer.avatarUrl ?? undefined"
                :alt="viewer.name"
                size="xs"
              />
              <span
                v-if="!collapsed"
                class="truncate"
              >{{ viewer.name }}</span>
            </UButton>
          </UDropdownMenu>

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
        </div>
      </template>
    </UDashboardSidebar>

    <slot />
    <PasswordPromptModal />
  </UDashboardGroup>
</template>
