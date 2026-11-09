<script setup lang="ts">
import { formatDate, isOverdue } from '~/utils/dates'

/** Due date as a calendar day; overdue dates in red, unless done. */
const props = defineProps<{
  date?: string | null
  done?: boolean
}>()

const { t, localeProperties } = useI18n()

const overdue = computed(() => !props.done && isOverdue(props.date))
const label = computed(() => (props.date ? formatDate(props.date, localeProperties.value.language ?? 'de-CH') : ''))
</script>

<template>
  <span
    v-if="date"
    class="due-date"
    :data-overdue="overdue ? '' : undefined"
  >
    <wa-icon :name="overdue ? 'calendar-exclamation' : 'calendar-day'"></wa-icon>
    <span>{{ label }}</span>
    <span
      v-if="overdue"
      class="wa-visually-hidden"
    >({{ t('due.overdue') }})</span>
  </span>
</template>

<style scoped>
.due-date {
  display: inline-flex;
  align-items: center;
  gap: var(--wa-space-3xs);
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
  white-space: nowrap;
}

.due-date[data-overdue] {
  color: var(--wa-color-danger-on-quiet);
}
</style>
