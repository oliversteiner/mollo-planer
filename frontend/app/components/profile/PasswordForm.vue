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
  <UCard>
    <template #header>
      <h2 class="font-semibold text-highlighted">
        {{ t('profile.password.title') }}
      </h2>
    </template>

    <UForm
      :state="state"
      class="space-y-4"
      @submit="submit"
    >
      <UFormField
        :label="t('profile.password.current')"
        name="current_password"
        :error="errors.current_password"
        required
      >
        <UInput
          v-model="state.current_password"
          type="password"
          autocomplete="current-password"
          class="w-full"
          required
        />
      </UFormField>
      <UFormField
        :label="t('auth.setPassword.password')"
        :hint="t('auth.setPassword.rules')"
        name="password"
        :error="errors.password"
        required
      >
        <UInput
          v-model="state.password"
          type="password"
          autocomplete="new-password"
          class="w-full"
          required
        />
      </UFormField>
      <UFormField
        :label="t('auth.setPassword.confirmation')"
        name="password_confirmation"
        :error="errors.password_confirmation"
        required
      >
        <UInput
          v-model="state.password_confirmation"
          type="password"
          autocomplete="new-password"
          class="w-full"
          required
        />
      </UFormField>
      <UButton
        type="submit"
        :label="t('profile.password.submit')"
        :loading="pending"
      />
    </UForm>
  </UCard>
</template>
