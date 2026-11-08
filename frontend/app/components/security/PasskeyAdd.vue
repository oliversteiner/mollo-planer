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
  <UAlert
    v-if="!supported"
    color="warning"
    variant="subtle"
    :title="t('security.passkeys.unsupported')"
  />
  <form
    v-else
    class="flex flex-wrap items-end gap-2"
    @submit.prevent="add"
  >
    <UFormField
      :label="t('security.passkeys.name')"
      :hint="t('security.passkeys.nameHint')"
      class="min-w-48 flex-1"
    >
      <UInput
        v-model="name"
        :placeholder="t('security.passkeys.defaultName')"
        maxlength="255"
        class="w-full"
      />
    </UFormField>
    <UButton
      type="submit"
      icon="i-fadl-fingerprint"
      :label="t('security.passkeys.add')"
      :loading="pending"
    />
  </form>
</template>
