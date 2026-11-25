<script setup lang="ts">
import { classifyApolloError } from '~/utils/api'

/**
 * Asks before something irreversible, for example deleting. With `requiresPassword` the action needs a recent password
 * confirmation, asked for in a field of this dialog (Auftrag 55, rule R11): the password dialog never opens on top.
 */
const props = defineProps<{
  open: boolean
  title: string
  description: string
  confirmLabel: string
  /** Runs on confirm; the dialog closes when it resolves. */
  action: () => Promise<unknown>
  /** The action needs a recent confirmation of the password: it is asked for here, in the dialog. */
  requiresPassword?: boolean
  /** Colour of the button: danger (default) for deleting, brand for a final step that destroys nothing (issuing an invoice). */
  confirmVariant?: 'danger' | 'brand'
  /** The button is off, for example while a field of the default slot is empty. */
  confirmDisabled?: boolean
}>()

const emit = defineEmits<{ close: [] }>()

const { t } = useI18n()
const pending = ref(false)
const step = usePasswordStep()

const isOpen = computed({
  get: () => props.open,
  set: (value: boolean) => {
    if (!value) {
      step.cancel()
      emit('close')
    }
  }
})

async function perform(): Promise<unknown> {
  pending.value = true

  try {
    await props.action()
    emit('close')
  } catch (error) {
    // Without a recent confirmation the server asks for it: the field shows. Anything else is reported by the api plugin.
    if (props.requiresPassword && classifyApolloError(error).fields.password) {
      return NEEDS_PASSWORD
    }
    // The dialog stays open.
  } finally {
    pending.value = false
  }
}

async function confirm(): Promise<void> {
  if (props.requiresPassword) {
    await step.run(perform)

    return
  }

  await perform()
}
</script>

<template>
  <AppDialog
    v-model:open="isOpen"
    :label="title"
    :description="description"
  >
    <!-- A field the question needs (for example the reason of a cancellation), in this same dialog (R11). -->
    <slot />
    <PasswordConfirmField
      v-if="requiresPassword"
      :step="step"
    />
    <template #footer>
      <wa-button
        appearance="plain"
        variant="neutral"
        @click="isOpen = false"
      >
        {{ t('common.cancel') }}
      </wa-button>
      <wa-button
        :variant="confirmVariant ?? 'danger'"
        :loading="pending"
        :disabled="step.waiting || confirmDisabled"
        @click="confirm"
      >
        {{ confirmLabel }}
      </wa-button>
    </template>
  </AppDialog>
</template>
