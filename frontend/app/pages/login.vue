<script setup lang="ts">
import { safeRedirect } from '~/utils/api'

definePageMeta({ layout: 'auth', public: true })

const { t } = useI18n()
const route = useRoute()
const { $backend } = useNuxtApp()
const { load } = useViewer()
const { errors, pending, run } = useServerForm()

useHead({ title: () => t('auth.login.title') })

const step = ref<'credentials' | 'challenge'>('credentials')
const credentials = reactive({ email: '', password: '' })
const code = ref<string[]>([])
const recoveryCode = ref('')
const useRecoveryCode = ref(false)

/** After a successful login: check the session and go where the user wanted. */
async function finish(): Promise<void> {
  const problem = await load()

  if (problem === 'setup-required') {
    await navigateTo('/setup-security')
  } else if (problem === null) {
    await navigateTo(safeRedirect(route.query.redirect))
  }
}

async function submitCredentials(): Promise<void> {
  await run(async () => {
    const response = await $backend<{ two_factor?: boolean }>('/login', { method: 'POST', body: credentials })

    if (response?.two_factor) {
      step.value = 'challenge'
    } else {
      await finish()
    }
  })
}

async function submitChallenge(): Promise<void> {
  await run(async () => {
    await $backend('/two-factor-challenge', {
      method: 'POST',
      body: useRecoveryCode.value ? { recovery_code: recoveryCode.value.trim() } : { code: code.value.join('') }
    })
    await finish()
  })
}

function backToCredentials(): void {
  step.value = 'credentials'
  code.value = []
  recoveryCode.value = ''
  errors.value = {}
}
</script>

<template>
  <UPageCard
    v-if="step === 'credentials'"
    :title="t('auth.login.title')"
  >
    <UForm
      :state="credentials"
      class="space-y-4"
      @submit="submitCredentials"
    >
      <UFormField
        :label="t('auth.email')"
        name="email"
        :error="errors.email"
        required
      >
        <UInput
          v-model="credentials.email"
          type="email"
          autocomplete="username"
          class="w-full"
          autofocus
          required
        />
      </UFormField>

      <UFormField
        :label="t('auth.password')"
        name="password"
        :error="errors.password"
        required
      >
        <UInput
          v-model="credentials.password"
          type="password"
          autocomplete="current-password"
          class="w-full"
          required
        />
      </UFormField>

      <UButton
        type="submit"
        :label="t('auth.login.submit')"
        :loading="pending"
        block
      />

      <div class="text-center text-sm">
        <ULink
          to="/forgot-password"
          class="text-primary"
        >
          {{ t('auth.login.forgot') }}
        </ULink>
      </div>
    </UForm>
  </UPageCard>

  <UPageCard
    v-else
    :title="t('auth.challenge.title')"
    :description="useRecoveryCode ? t('auth.challenge.recoveryHint') : t('auth.challenge.codeHint')"
  >
    <UForm
      :state="{ code, recoveryCode }"
      class="space-y-4"
      @submit="submitChallenge"
    >
      <UFormField
        v-if="!useRecoveryCode"
        :label="t('auth.challenge.code')"
        name="code"
        :error="errors.code"
      >
        <UPinInput
          v-model="code"
          :length="6"
          otp
          autofocus
          @complete="submitChallenge"
        />
      </UFormField>

      <UFormField
        v-else
        :label="t('auth.challenge.recoveryCode')"
        name="recovery_code"
        :error="errors.recovery_code"
      >
        <UInput
          v-model="recoveryCode"
          autocomplete="one-time-code"
          class="w-full"
          autofocus
        />
      </UFormField>

      <UButton
        type="submit"
        :label="t('auth.challenge.submit')"
        :loading="pending"
        block
      />

      <div class="flex justify-between gap-2 text-sm">
        <UButton
          variant="link"
          :label="useRecoveryCode ? t('auth.challenge.useCode') : t('auth.challenge.useRecovery')"
          @click="useRecoveryCode = !useRecoveryCode; errors = {}"
        />
        <UButton
          variant="link"
          color="neutral"
          :label="t('auth.challenge.back')"
          @click="backToCredentials"
        />
      </div>
    </UForm>
  </UPageCard>
</template>
