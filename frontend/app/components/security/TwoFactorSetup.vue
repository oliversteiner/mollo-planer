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
const code = ref<string[]>([])
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
    await confirmTwoFactor(code.value.join(''))
    codes.value = await recoveryCodes() ?? []
    step.value = 'codes'
  } catch (failure) {
    if (failure instanceof FetchError) {
      const apiError = classifyRestError(failure.status ?? 0, failure.data)
      error.value = apiError.fields.code?.[0] ?? ''
    }
    code.value = []
  } finally {
    pending.value = false
  }
}
</script>

<template>
  <div class="space-y-4">
    <template v-if="step === 'start'">
      <p class="text-sm text-muted">
        {{ t('security.twoFactor.intro') }}
      </p>
      <UButton
        icon="i-fadl-mobile-screen"
        :label="t('security.twoFactor.start')"
        :loading="pending"
        @click="start"
      />
    </template>

    <form
      v-else-if="step === 'scan'"
      class="space-y-4"
      @submit.prevent="confirm"
    >
      <p class="text-sm">
        {{ t('security.twoFactor.scan') }}
      </p>
      <img
        :src="qrCode"
        :alt="t('security.twoFactor.qrAlt')"
        class="size-48 rounded-md bg-white p-2"
      >
      <p class="text-sm text-muted">
        {{ t('security.twoFactor.manual') }}
        <code class="break-all font-mono text-highlighted">{{ secretKey }}</code>
      </p>
      <UFormField
        :label="t('auth.challenge.code')"
        :error="error || undefined"
      >
        <UPinInput
          v-model="code"
          :length="6"
          otp
          @complete="confirm"
        />
      </UFormField>
      <UButton
        type="submit"
        :label="t('security.twoFactor.confirm')"
        :loading="pending"
      />
    </form>

    <template v-else>
      <UAlert
        color="success"
        variant="subtle"
        :title="t('security.twoFactor.enabled')"
      />
      <SecurityRecoveryCodes :codes="codes" />
      <UButton
        :label="t('security.done')"
        @click="emit('done')"
      />
    </template>
  </div>
</template>
