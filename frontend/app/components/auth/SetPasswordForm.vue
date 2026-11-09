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
  <wa-card>
    <h1
      slot="header"
      class="wa-heading-m"
    >
      {{ title }}
    </h1>

    <div class="wa-stack wa-gap-m">
      <p class="wa-body-s wa-color-text-quiet">
        {{ description }}
      </p>

      <AppCallout
        v-if="!token || !email"
        variant="danger"
        :title="t('auth.setPassword.invalidLink')"
      />

      <form
        v-else
        class="wa-stack wa-gap-m"
        @submit.prevent="submit"
      >
        <AppCallout
          v-if="errors.email"
          variant="danger"
          :title="errors.email"
        />

        <FormInput
          :model-value="email"
          type="email"
          name="email"
          autocomplete="username"
          :label="t('auth.email')"
          disabled
        />

        <FormInput
          v-model="state.password"
          type="password"
          name="password"
          autocomplete="new-password"
          :label="t('auth.setPassword.password')"
          :hint="t('auth.setPassword.rules')"
          :error="errors.password"
          autofocus
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

        <wa-button
          type="submit"
          variant="brand"
          :loading="pending"
          style="width: 100%"
        >
          {{ submitLabel }}
        </wa-button>
      </form>

      <p class="wa-text-center wa-body-s">
        <NuxtLink
          to="/login"
          class="wa-link"
        >
          {{ t('auth.backToLogin') }}
        </NuxtLink>
      </p>
    </div>
  </wa-card>
</template>
