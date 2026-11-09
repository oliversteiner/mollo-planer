<script setup lang="ts">
/**
 * Status of a task (Kanban column: OPEN, IN_PROGRESS, REVIEW, DONE) or of
 * a milestone/todo (OPEN, DONE). Same colours as the left edge of a task
 * card: grey open, blue in progress, orange review, green done.
 */
defineProps<{ status: 'OPEN' | 'IN_PROGRESS' | 'REVIEW' | 'DONE' }>()

const { t } = useI18n()
</script>

<template>
  <wa-badge
    appearance="filled"
    class="status-badge"
    :data-status="status"
  >
    {{ t(`status.${status}`) }}
  </wa-badge>
</template>

<style scoped>
/* Light: pale fill, dark text of the same hue. */
.status-badge {
  --wa-color-fill-normal: var(--wa-color-gray-90);
  --wa-color-on-normal: var(--wa-color-gray-30);
}

.status-badge[data-status='IN_PROGRESS'] {
  --wa-color-fill-normal: var(--wa-color-blue-90);
  --wa-color-on-normal: var(--wa-color-blue-30);
}

.status-badge[data-status='REVIEW'] {
  --wa-color-fill-normal: var(--wa-color-orange-90);
  --wa-color-on-normal: var(--wa-color-orange-30);
}

.status-badge[data-status='DONE'] {
  --wa-color-fill-normal: var(--wa-color-green-90);
  --wa-color-on-normal: var(--wa-color-green-30);
}

/* Dark: dark fill, light text. */
:root.wa-dark .status-badge {
  --wa-color-fill-normal: var(--wa-color-gray-20);
  --wa-color-on-normal: var(--wa-color-gray-90);
}

:root.wa-dark .status-badge[data-status='IN_PROGRESS'] {
  --wa-color-fill-normal: var(--wa-color-blue-20);
  --wa-color-on-normal: var(--wa-color-blue-90);
}

:root.wa-dark .status-badge[data-status='REVIEW'] {
  --wa-color-fill-normal: var(--wa-color-orange-20);
  --wa-color-on-normal: var(--wa-color-orange-90);
}

:root.wa-dark .status-badge[data-status='DONE'] {
  --wa-color-fill-normal: var(--wa-color-green-20);
  --wa-color-on-normal: var(--wa-color-green-90);
}
</style>
