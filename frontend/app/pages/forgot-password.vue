<script setup lang="ts">
definePageMeta({ layout: 'auth', public: true })

const { t } = useI18n()
const { $backend } = useNuxtApp()
const { errors, pending, run } = useServerForm()

useHead({ title: () => t('auth.forgot.title') })

const state = reactive({ email: '' })
const sent = ref(false)

async function submit(): Promise<void> {
  sent.value = await run(() => $backend('/forgot-password', { method: 'POST', body: state }))
}
</script>

<template>
  <wa-card>
    <h1
      slot="header"
      class="wa-heading-m"
    >
      {{ t('auth.forgot.title') }}
    </h1>

    <div class="wa-stack wa-gap-m">
      <p class="wa-body-s wa-color-text-quiet">
        {{ t('auth.forgot.description') }}
      </p>

      <AppCallout
        v-if="sent"
        variant="success"
        :title="t('auth.forgot.sent')"
      />

      <form
        v-else
        class="wa-stack wa-gap-m"
        @submit.prevent="submit"
      >
        <FormInput
          v-model="state.email"
          type="email"
          name="email"
          autocomplete="username"
          :label="t('auth.email')"
          :error="errors.email"
          autofocus
          required
        />

        <wa-button
          type="submit"
          variant="brand"
          :loading="pending"
          style="width: 100%"
        >
          {{ t('auth.forgot.submit') }}
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
