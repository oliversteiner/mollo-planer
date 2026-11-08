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
  <UPageCard
    :title="t('auth.forgot.title')"
    :description="t('auth.forgot.description')"
  >
    <UAlert
      v-if="sent"
      color="success"
      variant="subtle"
      :title="t('auth.forgot.sent')"
    />

    <UForm
      v-else
      :state="state"
      class="space-y-4"
      @submit="submit"
    >
      <UFormField
        :label="t('auth.email')"
        name="email"
        :error="errors.email"
        required
      >
        <UInput
          v-model="state.email"
          type="email"
          autocomplete="username"
          class="w-full"
          autofocus
          required
        />
      </UFormField>

      <UButton
        type="submit"
        :label="t('auth.forgot.submit')"
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
