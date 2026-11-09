<script setup lang="ts">
/** Choice from a list (wa-select) with v-model, label, hint and message; see FormInput. */
export interface FormSelectItem {
  label: string
  value: string
  disabled?: boolean
}

defineProps<{
  items: readonly FormSelectItem[]
  label?: string
  hint?: string
  error?: string
}>()

const model = defineModel<string | null>({ default: null })

function onChange(event: Event) {
  model.value = (event.target as HTMLSelectElement).value
}
</script>

<template>
  <wa-select
    :value="model ?? ''"
    :label="label"
    :hint="error ? undefined : hint"
    :aria-invalid="error ? 'true' : undefined"
    :data-invalid="error ? '' : undefined"
    @change="onChange"
  >
    <wa-option
      v-for="item in items"
      :key="item.value"
      :value="item.value"
      :disabled="item.disabled"
    >
      {{ item.label }}
    </wa-option>
    <span
      v-if="error"
      slot="hint"
      data-form-error
    >{{ error }}</span>
  </wa-select>
</template>
