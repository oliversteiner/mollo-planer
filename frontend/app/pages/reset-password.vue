<script setup lang="ts">
definePageMeta({ layout: 'auth', public: true })

const { t } = useI18n()
const toast = useToast()

useHead({ title: () => t('auth.reset.title') })

async function done(): Promise<void> {
  toast.add({ title: t('auth.reset.done'), color: 'success' })
  await navigateTo('/login')
}
</script>

<template>
  <AuthSetPasswordForm
    endpoint="/reset-password"
    :title="t('auth.reset.title')"
    :description="t('auth.reset.description')"
    :submit-label="t('auth.reset.submit')"
    @done="done"
  />
</template>
