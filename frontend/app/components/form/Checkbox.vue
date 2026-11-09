<script setup lang="ts">
/** Checkbox (wa-checkbox) with v-model (true/false); the label is the `label` prop or the default slot. */
defineProps<{
  label?: string
  /** Explanation under the label. */
  hint?: string
}>()

const model = defineModel<boolean>({ default: false })

function onChange(event: Event) {
  model.value = (event.target as HTMLInputElement).checked
}
</script>

<template>
  <wa-checkbox
    :checked="model"
    :hint="hint"
    @change="onChange"
  >
    <slot>{{ label }}</slot>
  </wa-checkbox>
</template>
