<script setup lang="ts">
/**
 * Code field in segments (wa-otp-input) for the 6-digit app code, with v-model
 * (the digits as one string), label and message; see FormInput. `autosubmit`
 * sends the surrounding form as soon as all segments are filled.
 */
defineProps<{
  label?: string
  hint?: string
  error?: string
}>()

const model = defineModel<string>({ default: '' })

function onInput(event: Event) {
  model.value = (event.target as HTMLInputElement).value ?? ''
}
</script>

<template>
  <wa-otp-input
    :value="model"
    :label="label"
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
  </wa-otp-input>
</template>
