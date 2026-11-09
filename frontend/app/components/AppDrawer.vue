<script setup lang="ts">
/**
 * Side panel (wa-drawer) with v-model:open. Closing by the close button, the
 * Escape key or a click outside sets `open` to false. The parent keeps the
 * state; the footer slot is for buttons.
 */
defineProps<{
  /** Title (needed for screen readers). */
  label: string
  /** Line under the title, as text. */
  description?: string
  placement?: 'end' | 'start'
}>()

const open = defineModel<boolean>('open', { default: false })

/** Events of things inside bubble up (wa-select, wa-dialog, ...); only the drawer's own count. */
function onHide(event: Event) {
  if (event.target === event.currentTarget) {
    open.value = false
  }
}
</script>

<template>
  <wa-drawer
    :open="open"
    :label="label"
    :placement="placement ?? 'end'"
    light-dismiss
    @wa-hide="onHide"
  >
    <p
      v-if="description"
      data-drawer-description
    >
      {{ description }}
    </p>
    <slot />
    <div
      v-if="$slots.footer"
      slot="footer"
      data-drawer-footer
    >
      <slot name="footer" />
    </div>
  </wa-drawer>
</template>

<style scoped>
[data-drawer-description] {
  margin-block: 0 var(--wa-space-m);
  color: var(--wa-color-text-quiet);
}

[data-drawer-footer] {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: var(--wa-space-s);
  inline-size: 100%;
}
</style>
