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
  <PageFrame
    :title="t('admin.settings.title')"
    :breadcrumbs="[{ label: t('nav.admin') }, { label: t('admin.settings.title') }]"
  >
    <wa-card class="admin-settings-card">
      <h2
        slot="header"
        class="wa-heading-m"
      >
        {{ t('admin.settings.twoFactorTitle') }}
      </h2>

      <wa-skeleton
        v-if="!settings"
        effect="sheen"
        class="admin-settings-skeleton"
      ></wa-skeleton>
      <div
        v-else
        class="wa-stack wa-gap-m"
      >
        <FormSwitch
          :model-value="settings.twoFactorEnabled"
          :label="t('admin.settings.twoFactorLabel')"
          :hint="t('admin.settings.twoFactorDescription')"
          :disabled="!settings.twoFactorSwitchable || loading"
          @update:model-value="setTwoFactor"
        />
        <AppCallout
          v-if="!settings.twoFactorSwitchable"
          variant="brand"
          :title="t('admin.settings.notSwitchable')"
        />
        <AppCallout
          v-else-if="!settings.twoFactorEnabled"
          variant="warning"
          :title="t('admin.settings.offWarning')"
        />
      </div>
    </wa-card>
  </PageFrame>
</template>

<style scoped>
.admin-settings-card {
  max-inline-size: 42rem;
}

.admin-settings-skeleton {
  block-size: 3rem;
}
</style>
