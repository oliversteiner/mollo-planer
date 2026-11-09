<script setup lang="ts">
/** Change the own password (Fortify: PUT /user/password). */
const { t } = useI18n()
const toast = useToast()
const { $backend } = useNuxtApp()
const { errors, pending, run } = useServerForm()

const state = reactive({ current_password: '', password: '', password_confirmation: '' })

async function submit(): Promise<void> {
  const ok = await run(() => $backend('/user/password', { method: 'PUT', body: state }))

  if (ok) {
    Object.assign(state, { current_password: '', password: '', password_confirmation: '' })
    toast.add({ title: t('profile.password.saved'), color: 'success' })
  }
}
</script>

<template>
  <wa-card>
    <h2
      slot="header"
      class="wa-heading-xs"
    >
      {{ t('profile.password.title') }}
    </h2>

    <form
      class="wa-stack wa-gap-m"
      @submit.prevent="submit"
    >
      <FormInput
        v-model="state.current_password"
        type="password"
        name="current_password"
        autocomplete="current-password"
        :label="t('profile.password.current')"
        :error="errors.current_password"
        required
      />
      <FormInput
        v-model="state.password"
        type="password"
        name="password"
        autocomplete="new-password"
        :label="t('auth.setPassword.password')"
        :hint="t('auth.setPassword.rules')"
        :error="errors.password"
        required
      />
      <FormInput
        v-model="state.password_confirmation"
        type="password"
        name="password_confirmation"
        autocomplete="new-password"
        :label="t('auth.setPassword.confirmation')"
        :error="errors.password_confirmation"
        required
      />
      <div>
        <wa-button
          type="submit"
          variant="brand"
          :loading="pending"
        >
          {{ t('profile.password.submit') }}
        </wa-button>
      </div>
    </form>
  </wa-card>
</template>
