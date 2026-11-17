<script setup lang="ts">
/**
 * Dialog of usePasswordPrompt(): confirm the own password. The password field
 * exists only while the dialog is shown: the layouts keep this dialog on every
 * page, and Chrome otherwise takes the first text field of a page (the search
 * on /admin/users) for the user name and fills it (docs/fix-m1-f4.md F4.2).
 * While open, the field sits in its own form with a hidden user name, so the
 * password manager pairs it with the own account, not with fields outside.
 */
const { t } = useI18n()
const { open, error, answer } = usePasswordPrompt()
const { viewer } = useViewer()

const password = ref('')

const isOpen = computed({
  get: () => open.value,
  set: (value: boolean) => {
    if (!value) {
      answer(null)
    }
  }
})

/** True from opening until the dialog has finished hiding, so it does not shrink while it fades. */
const shown = ref(open.value)

watch(open, (value) => {
  password.value = ''
  if (value) {
    shown.value = true
  }
})

function onAfterHide(event: Event): void {
  if (event.target === event.currentTarget && !open.value) {
    shown.value = false
  }
}

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
    @wa-after-hide="onAfterHide"
  >
    <form
      v-if="shown"
      @submit.prevent="submit"
    >
      <!-- For the password manager only: whose password this is. -->
      <input
        type="text"
        name="username"
        autocomplete="username"
        :value="viewer?.email ?? ''"
        readonly
        hidden
      >
      <FormInput
        v-model="password"
        type="password"
        name="password"
        autocomplete="current-password"
        :label="t('auth.password')"
        :error="error || undefined"
        autofocus
        required
        @keydown.enter.prevent="submit"
      />
    </form>
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
