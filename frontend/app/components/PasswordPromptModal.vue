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
  answer(password.value)
}
</script>

<template>
  <UModal
    v-model:open="isOpen"
    :title="t('security.confirmPassword.title')"
    :description="t('security.confirmPassword.description')"
  >
    <template #body>
      <form
        id="password-prompt"
        @submit.prevent="submit"
      >
        <UFormField
          :label="t('auth.password')"
          :error="error || undefined"
        >
          <UInput
            v-model="password"
            type="password"
            autocomplete="current-password"
            class="w-full"
            autofocus
            required
          />
        </UFormField>
      </form>
    </template>
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton
          :label="t('common.cancel')"
          color="neutral"
          variant="ghost"
          @click="isOpen = false"
        />
        <UButton
          type="submit"
          form="password-prompt"
          :label="t('security.confirmPassword.submit')"
        />
      </div>
    </template>
  </UModal>
</template>
