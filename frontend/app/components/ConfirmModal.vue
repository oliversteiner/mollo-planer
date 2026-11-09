<script setup lang="ts">
/** Asks before something irreversible, for example deleting. */
const props = defineProps<{
  open: boolean
  title: string
  description: string
  confirmLabel: string
  /** Runs on confirm; the dialog closes when it resolves. */
  action: () => Promise<unknown>
}>()

const emit = defineEmits<{ close: [] }>()

const { t } = useI18n()
const pending = ref(false)

const isOpen = computed({
  get: () => props.open,
  set: (value: boolean) => {
    if (!value) {
      emit('close')
    }
  }
})

async function confirm(): Promise<void> {
  pending.value = true

  try {
    await props.action()
    emit('close')
  } catch {
    // Reported by the api plugin; the dialog stays open.
  } finally {
    pending.value = false
  }
}
</script>

<template>
  <AppDialog
    v-model:open="isOpen"
    :label="title"
    :description="description"
  >
    <template #footer>
      <wa-button
        appearance="plain"
        variant="neutral"
        @click="isOpen = false"
      >
        {{ t('common.cancel') }}
      </wa-button>
      <wa-button
        variant="danger"
        :loading="pending"
        @click="confirm"
      >
        {{ confirmLabel }}
      </wa-button>
    </template>
  </AppDialog>
</template>
