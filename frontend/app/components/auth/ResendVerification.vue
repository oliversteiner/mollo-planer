<script setup lang="ts">
/**
 * "Send the confirmation mail again". Logged in (mode "session"): one button. The answer 204
 * means the address is confirmed meanwhile; the page then checks the account again (`confirmed`).
 * Not logged in (mode "address", the link ran out): the address is asked for, and the answer is the
 * same for every address, whether it is known or not. After sending, the button is locked for
 * a minute (the API allows one request a minute); the mail itself can take a minute to arrive.
 */
const props = defineProps<{ mode: 'session' | 'address', email?: string | null }>()
const emit = defineEmits<{ confirmed: [] }>()

const LOCK_SECONDS = 60

const { t } = useI18n()
const toast = useToast()
const { $backend } = useNuxtApp()
const { errors, pending, run } = useServerForm()

const state = reactive({ email: props.email ?? '' })
const remaining = ref(0)
const sent = ref(false)
let timer: ReturnType<typeof setInterval> | undefined

function lock(): void {
  remaining.value = LOCK_SECONDS
  clearInterval(timer)
  timer = setInterval(() => {
    remaining.value -= 1

    if (remaining.value <= 0) {
      clearInterval(timer)
    }
  }, 1000)
}

onBeforeUnmount(() => clearInterval(timer))

const label = computed(() => (remaining.value > 0
  ? t('auth.verify.resendIn', { seconds: remaining.value })
  : t('auth.verify.resend')))

async function send(): Promise<void> {
  if (props.mode === 'session') {
    await run(async () => {
      const response = await $backend.raw('/email/verification-notification', { method: 'POST' })

      if (response.status === 204) {
        emit('confirmed')

        return
      }

      toast.add({ title: t('auth.verify.resent'), color: 'success' })
      lock()
    })

    return
  }

  if (await run(() => $backend('/email/verification-notification/guest', { method: 'POST', body: { email: state.email } }))) {
    sent.value = true
    lock()
  }
}
</script>

<template>
  <div class="wa-stack wa-gap-m">
    <AppCallout
      v-if="sent"
      variant="success"
      :title="t('auth.verify.resentNeutral')"
    />

    <form
      v-if="mode === 'address'"
      class="wa-stack wa-gap-m"
      @submit.prevent="send"
    >
      <FormInput
        v-model="state.email"
        type="email"
        name="email"
        autocomplete="username"
        :label="t('auth.email')"
        :error="errors.email"
        required
      />

      <wa-button
        type="submit"
        variant="brand"
        :loading="pending"
        :disabled="remaining > 0"
        style="width: 100%"
      >
        {{ label }}
      </wa-button>
    </form>

    <wa-button
      v-else
      appearance="outlined"
      variant="neutral"
      :loading="pending"
      :disabled="remaining > 0"
      style="width: 100%"
      @click="send"
    >
      {{ label }}
    </wa-button>
  </div>
</template>
