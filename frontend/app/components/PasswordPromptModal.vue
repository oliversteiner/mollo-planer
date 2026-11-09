<script setup lang="ts">
/** Dialog of usePasswordPrompt(): confirm the own password. */
const { t } = useI18n()
const { open, error, answer } = usePasswordPrompt()

const password = ref('')

const isOpen = computed({
  get: () => open.value,
  set: (value: boolean) => {
    if (!value) {
      answer(null)
    }
  }
})

watch(open, () => {
  password.value = ''
})

function submit(): void {
  if (password.value) {
    answer(password.value)
  }
}
</script>

<template>
  <AppDialog
    v-model:open="isOpen"
    :label="t('security.confirmPassword.title')"
    :description="t('security.confirmPassword.description')"
  >
    <FormInput
      v-model="password"
      type="password"
      autocomplete="current-password"
      :label="t('auth.password')"
      :error="error || undefined"
      autofocus
      required
      @keydown.enter.prevent="submit"
    />
    <template #footer>
      <wa-button
        appearance="plain"
        variant="neutral"
        @click="isOpen = false"
      >
        {{ t('common.cancel') }}
      </wa-button>
      <wa-button
        variant="brand"
        :disabled="!password"
        @click="submit"
      >
        {{ t('security.confirmPassword.submit') }}
      </wa-button>
    </template>
  </AppDialog>
</template>
