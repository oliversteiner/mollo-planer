<script setup lang="ts">
/**
 * Label, hint and message around a control that is not a wa-* field (for
 * example the icon picker or the rich text editor). The group is named by
 * the label for screen readers.
 */
defineProps<{
  label: string
  hint?: string
  error?: string
}>()

const labelId = useId()
</script>

<template>
  <div
    class="form-field"
    role="group"
    :aria-labelledby="labelId"
  >
    <span
      :id="labelId"
      class="wa-form-control-label"
    >{{ label }}</span>
    <slot />
    <span
      v-if="error"
      data-form-error
      class="form-field-note"
    >{{ error }}</span>
    <span
      v-else-if="hint"
      class="wa-form-control-hint form-field-note"
    >{{ hint }}</span>
  </div>
</template>

<style scoped>
.form-field {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--wa-space-3xs);
}

.form-field-note {
  font-size: var(--wa-font-size-s);
}
</style>
