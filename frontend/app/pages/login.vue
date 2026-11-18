<script setup lang="ts">
import { browserSupportsWebAuthn } from '@simplewebauthn/browser'
import { pendingAccountPath, safeRedirect } from '~/utils/api'

definePageMeta({ layout: 'auth', public: true })

const { t } = useI18n()
const route = useRoute()
const { $backend } = useNuxtApp()
const { load } = useViewer()
const { errors, pending, run } = useServerForm()

useHead({ title: () => t('auth.login.title') })

const step = ref<'credentials' | 'challenge'>('credentials')
const credentials = reactive({ email: '', password: '' })
const code = ref('')
const recoveryCode = ref('')
const useRecoveryCode = ref(false)

const { loginWithPasskey } = useSecurity()
const toast = useToast()
const passkeysSupported = browserSupportsWebAuthn()
const passkeyPending = ref(false)

// The login page is public, so it is also reached with a session that is
// still logged in (bookmark, reload after the backend was down). Laravel
// then refuses a new login and redirects; go on with the session instead.
onMounted(() => finish())

/** One step: the browser asks for PIN or biometrics, no code needed. */
async function submitPasskey(): Promise<void> {
  passkeyPending.value = true
  try {
    await loginWithPasskey()
    await finish()
  } catch (error) {
    if (error instanceof Error && ['NotAllowedError', 'AbortError'].includes(error.name)) {
      toast.add({ title: t('security.passkeys.cancelled'), color: 'warning' })
    }
  } finally {
    passkeyPending.value = false
  }
}

/** After a successful login: check the session and go where the user wanted. */
async function finish(): Promise<void> {
  const problem = await load()

  const pendingPath = pendingAccountPath(problem)

  if (problem === 'setup-required') {
    await navigateTo('/setup-security')
  } else if (pendingPath) {
    await navigateTo(pendingPath)
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
      body: useRecoveryCode.value ? { recovery_code: recoveryCode.value.trim() } : { code: code.value }
    })
    await finish()
  })
}

function backToCredentials(): void {
  step.value = 'credentials'
  code.value = ''
  recoveryCode.value = ''
  errors.value = {}
}
</script>

<template>
  <wa-card v-if="step === 'credentials'">
    <h1
      slot="header"
      class="wa-heading-l"
    >
      {{ t('auth.login.title') }}
    </h1>

    <form
      class="wa-stack wa-gap-m"
      @submit.prevent="submitCredentials"
    >
      <FormInput
        v-model="credentials.email"
        type="email"
        name="email"
        autocomplete="username"
        :label="t('auth.email')"
        :error="errors.email"
        autofocus
        required
      />

      <FormInput
        v-model="credentials.password"
        type="password"
        name="password"
        autocomplete="current-password"
        :label="t('auth.password')"
        :error="errors.password"
        required
      />

      <wa-button
        type="submit"
        variant="brand"
        :loading="pending"
        style="width: 100%"
      >
        {{ t('auth.login.submit') }}
      </wa-button>

      <template v-if="passkeysSupported">
        <div
          class="wa-cluster wa-gap-s"
          role="separator"
        >
          <wa-divider style="flex: 1"></wa-divider>
          <span class="wa-caption-s wa-color-text-quiet">{{ t('auth.login.or') }}</span>
          <wa-divider style="flex: 1"></wa-divider>
        </div>
        <wa-button
          appearance="outlined"
          variant="neutral"
          :loading="passkeyPending"
          style="width: 100%"
          @click="submitPasskey"
        >
          <wa-icon
            slot="start"
            name="fingerprint"
          ></wa-icon>
          {{ t('auth.login.passkey') }}
        </wa-button>
      </template>

      <p class="wa-text-center wa-body-s">
        <NuxtLink
          to="/forgot-password"
          class="wa-link"
        >
          {{ t('auth.login.forgot') }}
        </NuxtLink>
      </p>

      <p class="wa-text-center wa-body-s">
        <NuxtLink
          to="/register"
          class="wa-link"
        >
          {{ t('auth.login.register') }}
        </NuxtLink>
      </p>
    </form>
  </wa-card>

  <wa-card v-else>
    <h1
      slot="header"
      class="wa-heading-l"
    >
      {{ t('auth.challenge.title') }}
    </h1>

    <form
      class="wa-stack wa-gap-m"
      @submit.prevent="submitChallenge"
    >
      <p class="wa-body-s wa-color-text-quiet">
        {{ useRecoveryCode ? t('auth.challenge.recoveryHint') : t('auth.challenge.codeHint') }}
      </p>

      <FormOtp
        v-if="!useRecoveryCode"
        v-model="code"
        name="code"
        :label="t('auth.challenge.code')"
        :error="errors.code"
        autofocus
        autosubmit
      />

      <FormInput
        v-else
        v-model="recoveryCode"
        name="recovery_code"
        autocomplete="one-time-code"
        :label="t('auth.challenge.recoveryCode')"
        :error="errors.recovery_code"
        autofocus
      />

      <wa-button
        type="submit"
        variant="brand"
        :loading="pending"
        style="width: 100%"
      >
        {{ t('auth.challenge.submit') }}
      </wa-button>

      <div class="wa-split wa-gap-s">
        <wa-button
          appearance="plain"
          variant="brand"
          @click="useRecoveryCode = !useRecoveryCode; errors = {}"
        >
          {{ useRecoveryCode ? t('auth.challenge.useCode') : t('auth.challenge.useRecovery') }}
        </wa-button>
        <wa-button
          appearance="plain"
          variant="neutral"
          @click="backToCredentials"
        >
          {{ t('auth.challenge.back') }}
        </wa-button>
      </div>
    </form>
  </wa-card>
</template>
