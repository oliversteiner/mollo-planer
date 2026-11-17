<script setup lang="ts">
/**
 * Short number of a milestone ("M1", "M2", ...) by its place on the page:
 * milestones in their order (position), as on the overview.
 */
defineProps<{ number: number }>()

const { t } = useI18n()
</script>

<template>
  <wa-badge
    variant="brand"
    appearance="filled"
    :aria-label="t('project.milestoneNumberLabel', { number })"
  >
    {{ t('project.milestoneNumber', { number }) }}
  </wa-badge>
</template>
