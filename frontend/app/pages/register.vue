<script setup lang="ts">
definePageMeta({ layout: 'auth', public: true })

const { t } = useI18n()
const { $backend } = useNuxtApp()
const { errors, pending, run } = useServerForm()

useHead({ title: () => t('auth.register.title') })

const state = reactive({ name: '', email: '', password: '', password_confirmation: '' })

/** Fortify logs the new account in; the account pages explain what comes next. */
async function submit(): Promise<void> {
  const ok = await run(() => $backend('/register', { method: 'POST', body: state }))

  if (ok) {
    await navigateTo('/account-pending?reason=email')
  }
}
</script>

<template>
  <wa-card>
    <h1
      slot="header"
      class="wa-heading-l"
    >
      {{ t('auth.register.title') }}
    </h1>

    <div class="wa-stack wa-gap-m">
      <p class="wa-body-s wa-color-text-quiet">
        {{ t('auth.register.description') }}
      </p>

      <form
        class="wa-stack wa-gap-m"
        @submit.prevent="submit"
      >
        <FormInput
          v-model="state.name"
          name="name"
          autocomplete="name"
          :label="t('auth.register.name')"
          :error="errors.name"
          autofocus
          required
        />

        <FormInput
          v-model="state.email"
          type="email"
          name="email"
          autocomplete="username"
          :label="t('auth.email')"
          :error="errors.email"
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

        <wa-button
          type="submit"
          variant="brand"
          :loading="pending"
          style="width: 100%"
        >
          {{ t('auth.register.submit') }}
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
