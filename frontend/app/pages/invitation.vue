<script setup lang="ts">
definePageMeta({ layout: 'auth', public: true })

const { t } = useI18n()
const toast = useToast()

useHead({ title: () => t('auth.invitation.title') })

async function done(): Promise<void> {
  toast.add({ title: t('auth.invitation.done'), color: 'success' })
  await navigateTo('/login')
}
</script>

<template>
  <AuthSetPasswordForm
    endpoint="/invitation"
    :title="t('auth.invitation.title')"
    :description="t('auth.invitation.description')"
    :submit-label="t('auth.invitation.submit')"
    @done="done"
  />
</template>
