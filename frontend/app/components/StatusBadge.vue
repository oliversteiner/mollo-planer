<script setup lang="ts">
/**
 * Status of a task (Kanban column: OPEN, IN_PROGRESS, REVIEW, DONE) or of
 * a milestone/todo (OPEN, DONE).
 */
const props = defineProps<{ status: 'OPEN' | 'IN_PROGRESS' | 'REVIEW' | 'DONE' }>()

const { t } = useI18n()

const colors = {
  OPEN: 'neutral',
  IN_PROGRESS: 'info',
  REVIEW: 'warning',
  DONE: 'success'
} as const

const color = computed(() => colors[props.status])
</script>

<template>
  <UBadge
    :color="color"
    variant="subtle"
    size="sm"
  >
    {{ t(`status.${status}`) }}
  </UBadge>
</template>
