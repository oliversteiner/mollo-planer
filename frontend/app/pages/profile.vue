<script setup lang="ts">
import { useMutation } from '@vue/apollo-composable'
import * as uiLocales from '@nuxt/ui/locale'
import { updateMyNotificationsMutation } from '~/graphql/profile'

const { t, locale, setLocale } = useI18n()
const toast = useToast()
const { viewer, load } = useViewer()

useHead({ title: () => t('profile.title') })

const { mutate: updateNotifications, loading: savingNotifications } = useMutation(updateMyNotificationsMutation)

async function setNotifications(enabled: boolean): Promise<void> {
  await updateNotifications({ emailEnabled: enabled }).catch(() => null)
  await load()
  toast.add({ title: enabled ? t('profile.notifications.on') : t('profile.notifications.off'), color: 'success' })
}

const currentLocale = computed({
  get: () => locale.value,
  set: (code: string) => setLocale(code as 'de' | 'en')
})
</script>

<template>
  <UDashboardPanel id="profile">
    <template #header>
      <UDashboardNavbar :title="t('profile.title')" />
    </template>

    <template #body>
      <div class="grid max-w-4xl gap-6 lg:grid-cols-2">
        <ProfileAvatarCard />

        <UCard>
          <template #header>
            <h2 class="font-semibold text-highlighted">
              {{ t('profile.settings') }}
            </h2>
          </template>
          <div class="space-y-6">
            <USwitch
              :model-value="viewer?.notifyByEmail ?? true"
              :label="t('profile.notifications.label')"
              :description="t('profile.notifications.description')"
              :loading="savingNotifications"
              @update:model-value="setNotifications"
            />
            <div class="flex flex-wrap gap-4">
              <UFormField :label="t('settings.language')">
                <ULocaleSelect
                  v-model="currentLocale"
                  :locales="[uiLocales.de, uiLocales.en]"
                  class="w-48"
                />
              </UFormField>
              <UFormField :label="t('settings.colorMode')">
                <UColorModeSelect class="w-48" />
              </UFormField>
            </div>
          </div>
        </UCard>

        <ProfileSecurityCard />
        <ProfileClaudeAccessCard />
        <ProfileDetailsForm />
        <ProfilePasswordForm />
      </div>
    </template>
  </UDashboardPanel>
</template>
