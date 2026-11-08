<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import { appSettingsQuery, updateAppSettingsMutation } from '~/graphql/admin'

definePageMeta({ middleware: 'admin' })

const { t } = useI18n()
const toast = useToast()
const { load } = useViewer()

useHead({ title: () => t('admin.settings.title') })

const { result } = useQuery(appSettingsQuery, null, { fetchPolicy: 'network-only' })
const settings = computed(() => result.value?.appSettings)

const { mutate, loading } = useMutation(updateAppSettingsMutation)

async function setTwoFactor(enabled: boolean): Promise<void> {
  const saved = await mutate({ twoFactorEnabled: enabled }).catch(() => null)

  if (saved) {
    toast.add({ title: enabled ? t('admin.settings.twoFactorOn') : t('admin.settings.twoFactorOff'), color: enabled ? 'success' : 'warning' })
    // Switching on may lock this very session (password-only login).
    await load()
  }
}
</script>

<template>
  <UDashboardPanel id="admin-settings">
    <template #header>
      <UDashboardNavbar :title="t('admin.settings.title')" />
    </template>

    <template #body>
      <UCard class="max-w-2xl">
        <template #header>
          <h2 class="font-semibold text-highlighted">
            {{ t('admin.settings.twoFactorTitle') }}
          </h2>
        </template>

        <USkeleton
          v-if="!settings"
          class="h-12"
        />
        <div
          v-else
          class="space-y-4"
        >
          <USwitch
            :model-value="settings.twoFactorEnabled"
            :label="t('admin.settings.twoFactorLabel')"
            :description="t('admin.settings.twoFactorDescription')"
            :disabled="!settings.twoFactorSwitchable"
            :loading="loading"
            @update:model-value="setTwoFactor"
          />
          <UAlert
            v-if="!settings.twoFactorSwitchable"
            color="info"
            variant="subtle"
            icon="i-fadl-lock"
            :title="t('admin.settings.notSwitchable')"
          />
          <UAlert
            v-else-if="!settings.twoFactorEnabled"
            color="warning"
            variant="subtle"
            icon="i-fadl-triangle-exclamation"
            :title="t('admin.settings.offWarning')"
          />
        </div>
      </UCard>
    </template>
  </UDashboardPanel>
</template>
