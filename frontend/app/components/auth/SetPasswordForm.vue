<script setup lang="ts">
/**
 * New password with confirmation, for the password reset and for the
 * invitation. The rules are checked by the backend (at least 8 characters).
 */
const props = defineProps<{
  /** Backend route: /reset-password or /invitation. */
  endpoint: string
  title: string
  description: string
  submitLabel: string
}>()

const emit = defineEmits<{ done: [] }>()

const { t } = useI18n()
const route = useRoute()
const { $backend } = useNuxtApp()
const { errors, pending, run } = useServerForm()

const token = computed(() => (typeof route.query.token === 'string' ? route.query.token : ''))
const email = computed(() => (typeof route.query.email === 'string' ? route.query.email : ''))
const state = reactive({ password: '', password_confirmation: '' })

async function submit(): Promise<void> {
  const ok = await run(() => $backend(props.endpoint, {
    method: 'POST',
    body: { token: token.value, email: email.value, ...state }
  }))

  if (ok) {
    emit('done')
  }
}
</script>

<template>
  <UPageCard
    :title="title"
    :description="description"
  >
    <UAlert
      v-if="!token || !email"
      color="error"
      variant="subtle"
      :title="t('auth.setPassword.invalidLink')"
    />

    <UForm
      v-else
      :state="state"
      class="space-y-4"
      @submit="submit"
    >
      <UAlert
        v-if="errors.email"
        color="error"
        variant="subtle"
        :title="errors.email"
      />

      <UFormField
        :label="t('auth.email')"
        name="email"
      >
        <UInput
          :model-value="email"
          type="email"
          autocomplete="username"
          class="w-full"
          disabled
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
          autofocus
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
        :label="submitLabel"
        :loading="pending"
        block
      />
    </UForm>

    <div class="mt-4 text-center text-sm">
      <ULink
        to="/login"
        class="text-primary"
      >
        {{ t('auth.backToLogin') }}
      </ULink>
    </div>
  </UPageCard>
</template>
