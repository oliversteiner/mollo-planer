<script setup lang="ts">
import { FetchError } from 'ofetch'
import { classifyRestError } from '~/utils/api'

/**
 * Set up an authenticator app: scan the QR code (or type the key),
 * confirm with a code, then keep the recovery codes.
 */
const emit = defineEmits<{ done: [] }>()

const { t } = useI18n()
const { startTwoFactor, confirmTwoFactor, recoveryCodes } = useSecurity()

const step = ref<'start' | 'scan' | 'codes'>('start')
const pending = ref(false)
const qrCode = ref('')
const secretKey = ref('')
const code = ref('')
const error = ref('')
const codes = ref<string[]>([])

async function start(): Promise<void> {
  pending.value = true
  try {
    const setup = await startTwoFactor()

    if (setup) {
      qrCode.value = setup.qrCode
      secretKey.value = setup.secretKey
      step.value = 'scan'
    }
  } finally {
    pending.value = false
  }
}

async function confirm(): Promise<void> {
  error.value = ''
  pending.value = true
  try {
    await confirmTwoFactor(code.value)
    codes.value = await recoveryCodes() ?? []
    step.value = 'codes'
  } catch (failure) {
    if (failure instanceof FetchError) {
      const apiError = classifyRestError(failure.status ?? 0, failure.data)
      error.value = apiError.fields.code?.[0] ?? ''
    }
    code.value = ''
  } finally {
    pending.value = false
  }
}
</script>

<template>
  <div class="wa-stack wa-gap-m">
    <template v-if="step === 'start'">
      <p class="wa-body-s wa-color-text-quiet">
        {{ t('security.twoFactor.intro') }}
      </p>
      <div>
        <wa-button
          variant="brand"
          :loading="pending"
          @click="start"
        >
          <wa-icon
            slot="start"
            name="mobile-screen"
          ></wa-icon>
          {{ t('security.twoFactor.start') }}
        </wa-button>
      </div>
    </template>

    <form
      v-else-if="step === 'scan'"
      class="wa-stack wa-gap-m"
      @submit.prevent="confirm"
    >
      <p class="wa-body-s">
        {{ t('security.twoFactor.scan') }}
      </p>
      <img
        :src="qrCode"
        :alt="t('security.twoFactor.qrAlt')"
        class="two-factor-qr"
      >
      <p class="wa-body-s wa-color-text-quiet">
        {{ t('security.twoFactor.manual') }}
        <code class="two-factor-key">{{ secretKey }}</code>
      </p>
      <FormOtp
        v-model="code"
        name="code"
        :label="t('auth.challenge.code')"
        :error="error || undefined"
        autosubmit
      />
      <div>
        <wa-button
          type="submit"
          variant="brand"
          :loading="pending"
        >
          {{ t('security.twoFactor.confirm') }}
        </wa-button>
      </div>
    </form>

    <template v-else>
      <AppCallout
        variant="success"
        :title="t('security.twoFactor.enabled')"
      />
      <SecurityRecoveryCodes :codes="codes" />
      <div>
        <wa-button
          variant="brand"
          @click="emit('done')"
        >
          {{ t('security.done') }}
        </wa-button>
      </div>
    </template>
  </div>
</template>

<style scoped>
/* The QR code stays dark on white, also in the dark mode, so it can be scanned. */
.two-factor-qr {
  inline-size: 12rem;
  block-size: 12rem;
  padding: var(--wa-space-xs);
  border-radius: var(--wa-border-radius-m);
  background-color: white;
}

.two-factor-key {
  overflow-wrap: anywhere;
  font-family: var(--wa-font-family-code);
  color: var(--wa-color-text-normal);
}
</style>
