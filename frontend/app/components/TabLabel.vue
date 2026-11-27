<script setup lang="ts">
/**
 * Title of a tab or a section with a count (UX rule 6): the count is an extra, not part of the title, so it is a pill,
 * not a number in brackets. For screen readers the name reads "Title, 4" (a hidden comma separates them).
 */
defineProps<{
  label: string
  count: number
}>()
</script>

<template>
  <span class="tab-label">
    {{ label }}<span class="wa-visually-hidden">, </span>
    <wa-badge
      pill
      appearance="outlined"
      variant="neutral"
    >
      {{ count }}
    </wa-badge>
  </span>
</template>

<style scoped>
.tab-label {
  display: inline-flex;
  align-items: center;
  gap: var(--wa-space-xs);
}
</style>
