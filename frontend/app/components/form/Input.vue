<script setup lang="ts">
/**
 * Single-line field (wa-input) with v-model, label, hint and a message from
 * the server. All other attributes (type, name, autocomplete, placeholder,
 * required, autofocus, ...) go to the wa-input. The message is shown under
 * the field in place of the hint; the field is marked as invalid for
 * screen readers. The slot `start` takes an icon (`<wa-icon slot="start">`).
 */
defineProps<{
  label?: string
  hint?: string
  /** Validation message (for example from useServerForm). */
  error?: string
}>()

const model = defineModel<string>({ default: '' })

function onInput(event: Event) {
  model.value = (event.target as HTMLInputElement).value
}
</script>

<template>
  <wa-input
    :value="model"
    :label="label"
    :hint="error ? undefined : hint"
    :aria-invalid="error ? 'true' : undefined"
    :data-invalid="error ? '' : undefined"
    @input="onInput"
  >
    <slot name="start" />
    <span
      v-if="error"
      slot="hint"
      data-form-error
    >{{ error }}</span>
  </wa-input>
</template>
