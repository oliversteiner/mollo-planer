<script setup lang="ts">
import { useMutation } from '@vue/apollo-composable'
import { updateMyNotificationsMutation } from '~/graphql/profile'

const { t } = useI18n()
const toast = useToast()
const { viewer, load } = useViewer()

useHead({ title: () => t('profile.title') })

const { mutate: updateNotifications, loading: savingNotifications } = useMutation(updateMyNotificationsMutation)

async function setNotifications(enabled: boolean): Promise<void> {
  await updateNotifications({ emailEnabled: enabled }).catch(() => null)
  await load()
  toast.add({ title: enabled ? t('profile.notifications.on') : t('profile.notifications.off'), color: 'success' })
}
</script>

<template>
  <PageFrame :title="t('profile.title')">
    <div class="profile-grid">
      <ProfileAvatarCard />

      <wa-card>
        <h2
          slot="header"
          class="wa-heading-xs"
        >
          {{ t('profile.settings') }}
        </h2>
        <div class="wa-stack wa-gap-l">
          <FormSwitch
            :model-value="viewer?.notifyByEmail ?? true"
            :label="t('profile.notifications.label')"
            :hint="t('profile.notifications.description')"
            :disabled="savingNotifications"
            @update:model-value="setNotifications"
          />
          <div class="wa-cluster wa-gap-m wa-align-items-start">
            <LocaleSwitch
              :label="t('settings.language')"
              class="profile-select"
            />
            <ColorModeSelect
              :label="t('settings.colorMode')"
              class="profile-select"
            />
          </div>
        </div>
      </wa-card>

      <ProfileSecurityCard class="profile-wide" />
      <ProfileClaudeAccessCard class="profile-wide" />
      <ProfileDetailsForm />
      <ProfilePasswordForm />
    </div>
  </PageFrame>
</template>

<style scoped>
.profile-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 24rem), 1fr));
  gap: var(--wa-space-l);
  max-inline-size: 64rem;
}

.profile-wide {
  grid-column: 1 / -1;
}

.profile-select {
  inline-size: 12rem;
}
</style>
