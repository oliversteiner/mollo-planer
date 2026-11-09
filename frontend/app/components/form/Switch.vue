<script setup lang="ts">
/** Switch (wa-switch) with v-model (true/false); the label is the `label` prop or the default slot. */
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
  <wa-switch
    :checked="model"
    :hint="hint"
    @change="onChange"
  >
    <slot>{{ label }}</slot>
  </wa-switch>
</template>
