<script setup lang="ts">
/**
 * Dialog (wa-dialog) with v-model:open. Closing by the close button, the
 * Escape key or a click outside (light dismiss) sets `open` to false; the
 * parent only has to keep the state. Footer buttons go into the `footer`
 * slot and are laid out to the right.
 */
defineProps<{
  /** Title of the dialog (needed for screen readers). */
  label: string
  /** Explanation under the title, as text. */
  description?: string
  /** Closes on a click outside of the dialog. */
  lightDismiss?: boolean
}>()

const open = defineModel<boolean>('open', { default: false })

/** Events of wa-select, wa-tooltip and others inside bubble up; only the dialog's own count. */
function onHide(event: Event) {
  if (event.target === event.currentTarget) {
    open.value = false
  }
}
</script>

<template>
  <wa-dialog
    :open="open"
    :label="label"
    :light-dismiss="lightDismiss"
    @wa-hide="onHide"
  >
    <p
      v-if="description"
      data-dialog-description
    >
      {{ description }}
    </p>
    <slot />
    <div
      v-if="$slots.footer"
      slot="footer"
      data-dialog-footer
    >
      <slot name="footer" />
    </div>
  </wa-dialog>
</template>

<style scoped>
[data-dialog-description] {
  margin-block: 0 var(--wa-space-m);
  color: var(--wa-color-text-quiet);
}

[data-dialog-footer] {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: var(--wa-space-s);
  inline-size: 100%;
}
</style>
