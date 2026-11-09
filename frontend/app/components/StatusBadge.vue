<script setup lang="ts">
/**
 * Status of a task (Kanban column: OPEN, IN_PROGRESS, REVIEW, DONE) or of
 * a milestone/todo (OPEN, DONE).
 */
const props = defineProps<{ status: 'OPEN' | 'IN_PROGRESS' | 'REVIEW' | 'DONE' }>()

const { t } = useI18n()

const variants = {
  OPEN: 'neutral',
  IN_PROGRESS: 'brand',
  REVIEW: 'warning',
  DONE: 'success'
} as const

const variant = computed(() => variants[props.status])
</script>

<template>
  <wa-badge
    :variant="variant"
    appearance="filled"
  >
    {{ t(`status.${status}`) }}
  </wa-badge>
</template>
