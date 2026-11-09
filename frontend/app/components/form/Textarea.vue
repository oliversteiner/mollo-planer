<script setup lang="ts">
/** Multi-line field (wa-textarea) with v-model, label, hint and message; see FormInput. */
defineProps<{
  label?: string
  /** The label is for screen readers only (a field whose purpose is clear from where it stands). */
  hideLabel?: boolean
  hint?: string
  error?: string
}>()

const model = defineModel<string>({ default: '' })

function onInput(event: Event) {
  model.value = (event.target as HTMLTextAreaElement).value
}
</script>

<template>
  <wa-textarea
    :value="model"
    :label="label"
    :class="{ 'wa-visually-hidden-label': hideLabel }"
    :hint="error ? undefined : hint"
    :aria-invalid="error ? 'true' : undefined"
    :data-invalid="error ? '' : undefined"
    @input="onInput"
  >
    <span
      v-if="error"
      slot="hint"
      data-form-error
    >{{ error }}</span>
  </wa-textarea>
</template>
