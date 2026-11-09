<script setup lang="ts">
import { browserSupportsWebAuthn } from '@simplewebauthn/browser'

/** Add a passkey on this device; the browser asks for PIN or biometrics. */
const emit = defineEmits<{ added: [] }>()

const { t } = useI18n()
const toast = useToast()
const { addPasskey } = useSecurity()

const supported = browserSupportsWebAuthn()
const name = ref('')
const pending = ref(false)

async function add(): Promise<void> {
  pending.value = true
  try {
    if (await addPasskey(name.value.trim() || t('security.passkeys.defaultName'))) {
      name.value = ''
      toast.add({ title: t('security.passkeys.added'), color: 'success' })
      emit('added')
    }
  } catch (error) {
    if (error instanceof Error && ['NotAllowedError', 'AbortError'].includes(error.name)) {
      toast.add({ title: t('security.passkeys.cancelled'), color: 'warning' })
    } else if (error instanceof Error && error.name === 'InvalidStateError') {
      toast.add({ title: t('security.passkeys.exists'), color: 'warning' })
    } else {
      throw error
    }
  } finally {
    pending.value = false
  }
}
</script>

<template>
  <AppCallout
    v-if="!supported"
    variant="warning"
    :title="t('security.passkeys.unsupported')"
  />
  <form
    v-else
    class="passkey-form"
    @submit.prevent="add"
  >
    <FormInput
      v-model="name"
      class="passkey-name"
      :label="t('security.passkeys.name')"
      :hint="t('security.passkeys.nameHint')"
      :placeholder="t('security.passkeys.defaultName')"
      maxlength="255"
    />
    <wa-button
      type="submit"
      variant="brand"
      :loading="pending"
    >
      <wa-icon
        slot="start"
        name="fingerprint"
      ></wa-icon>
      {{ t('security.passkeys.add') }}
    </wa-button>
  </form>
</template>

<style scoped>
.passkey-form {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: var(--wa-space-s);
}

.passkey-name {
  flex: 1;
  min-inline-size: 12rem;
}

/* The button sits next to the field, level with its input, not with its label. */
.passkey-form > wa-button {
  margin-block-start: 1.6em;
}
</style>
