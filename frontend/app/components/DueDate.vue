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
    class="inline-flex items-center gap-1 text-sm"
    :class="overdue ? 'text-error' : 'text-muted'"
  >
    <UIcon
      :name="overdue ? 'i-fadl-calendar-exclamation' : 'i-fadl-calendar-day'"
      class="size-4"
    />
    <span>{{ label }}</span>
    <span
      v-if="overdue"
      class="sr-only"
    >({{ t('due.overdue') }})</span>
  </span>
</template>
