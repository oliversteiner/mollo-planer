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
  /** The label is for screen readers only (a field whose purpose is clear from where it stands). */
  hideLabel?: boolean
  hint?: string
  /** Validation message (for example from useServerForm). */
  error?: string
}>()

const model = defineModel<string>({ default: '' })

/**
 * Date and time fields always show the browser's own placeholder
 * (TT.MM.JJJJ). The theme floats the label above only when the field has a
 * placeholder, so an empty one keeps it out of the way (docs/fix-m1-abnahme.md F3.10).
 */
const DATE_TYPES = ['date', 'time', 'datetime-local', 'month', 'week']
const attrs = useAttrs()
const keepsLabelAbove = computed(() => DATE_TYPES.includes(String(attrs.type ?? '')) && attrs.placeholder === undefined)

function onInput(event: Event) {
  model.value = (event.target as HTMLInputElement).value
}
</script>

<template>
  <wa-input
    :value="model"
    :label="label"
    :placeholder.attr="keepsLabelAbove ? '' : undefined"
    :class="{ 'wa-visually-hidden-label': hideLabel }"
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
