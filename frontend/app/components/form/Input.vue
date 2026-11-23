<script setup lang="ts">
defineOptions({ inheritAttrs: false })

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
function forwardedAttrs() {
  const { type: _type, ...rest } = attrs
  return rest
}
function keepsLabelAbove() {
  return DATE_TYPES.includes(String(attrs.type ?? '')) && attrs.placeholder === undefined
}
const showsPassword = ref(false)
function isPassword() {
  return attrs.type === 'password'
}
function inputType() {
  return isPassword() && showsPassword.value ? 'text' : attrs.type
}

function onInput(event: Event) {
  model.value = (event.target as HTMLInputElement).value
}

function togglePasswordVisibility(): void {
  showsPassword.value = !showsPassword.value
}
</script>

<template>
  <wa-input
    :value="model"
    :type="inputType()"
    :label="label"
    :placeholder.attr="keepsLabelAbove() ? '' : undefined"
    :class="{ 'wa-visually-hidden-label': hideLabel }"
    :hint="error ? undefined : hint"
    :aria-invalid="error ? 'true' : undefined"
    :data-invalid="error ? '' : undefined"
    v-bind="forwardedAttrs()"
    @input="onInput"
  >
    <slot name="start" />
    <wa-button
      v-if="isPassword()"
      slot="end"
      type="button"
      appearance="plain"
      variant="neutral"
      size="small"
      :aria-label="showsPassword ? 'Passwort verbergen' : 'Passwort anzeigen'"
      :title="showsPassword ? 'Passwort verbergen' : 'Passwort anzeigen'"
      @click.prevent.stop="togglePasswordVisibility"
    >
      <wa-icon :name="showsPassword ? 'eye-slash' : 'eye'"></wa-icon>
    </wa-button>
    <span
      v-if="error"
      slot="hint"
      data-form-error
    >{{ error }}</span>
  </wa-input>
</template>
