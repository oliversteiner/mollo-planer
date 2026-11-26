<script setup lang="ts">
import type { MilestoneVersionOrderQuery } from '~/graphql/generated/graphql'

/**
 * Preview of «Nach Version ordnen» (Auftrag 47 C): the new order of the milestones with the moved ones marked. The
 * backend decides the order and every condition (MilestoneVersionOrder::plan); this only shows it. Nothing opens from
 * here (R11): «Neu ordnen» applies, «Abbrechen» closes.
 */
type Plan = NonNullable<MilestoneVersionOrderQuery['milestoneVersionOrder']>

defineProps<{
  plan: Plan
  /** The order is being written. */
  busy?: boolean
}>()

const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ apply: [] }>()

const { t } = useI18n()
</script>

<template>
  <AppDialog
    v-model:open="open"
    :label="t('project.timeline.order.title')"
    :description="t('project.timeline.order.description')"
  >
    <p
      class="order-summary"
      role="status"
    >
      {{ t('project.timeline.order.summary', { count: plan.moveCount }, plan.moveCount) }}
    </p>
    <ol class="order-rows wa-list-plain">
      <li
        v-for="row in plan.order"
        :key="row.id"
        class="order-row"
        :class="{ 'order-row-moved': row.moved }"
      >
        <MilestoneNumber :number="row.to + 1" />
        <span
          class="order-title"
          :title="row.title"
        >{{ row.title }}</span>
        <span class="order-version wa-caption-s">{{ row.version ?? t('project.timeline.order.noVersion') }}</span>
        <wa-badge
          v-if="row.moved"
          variant="warning"
          appearance="outlined"
          class="order-moved"
        >
          <wa-icon
            :name="row.to < row.from ? 'arrow-up' : 'arrow-down'"
            class="order-moved-icon"
          ></wa-icon>
          {{ t('project.timeline.order.movedFrom', { from: row.from + 1 }) }}
        </wa-badge>
      </li>
    </ol>

    <template #footer>
      <wa-button
        appearance="plain"
        variant="neutral"
        @click="open = false"
      >
        {{ t('common.cancel') }}
      </wa-button>
      <wa-button
        variant="brand"
        :loading="busy"
        :disabled="busy || !plan.canApply"
        @click="emit('apply')"
      >
        {{ t('project.timeline.order.apply') }}
      </wa-button>
    </template>
  </AppDialog>
</template>

<style scoped>
.order-summary {
  margin-block: 0 var(--wa-space-s);
  font-weight: var(--wa-font-weight-semibold);
}

.order-rows {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-3xs);
  max-block-size: 50vh;
  overflow-y: auto;
  margin: 0;
  padding: 0;
}

.order-row {
  display: flex;
  align-items: center;
  gap: var(--wa-space-s);
  padding: var(--wa-space-2xs) var(--wa-space-s);
  border-radius: var(--wa-border-radius-m);
}

.order-row-moved {
  background: var(--wa-color-warning-fill-quiet);
}

.order-title {
  flex: 1;
  min-inline-size: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.order-version {
  color: var(--wa-color-text-quiet);
  white-space: nowrap;
}

.order-moved {
  white-space: nowrap;
}

.order-moved-icon {
  margin-inline-end: var(--wa-space-3xs);
}

@media (max-width: 40em) {
  .order-row {
    flex-wrap: wrap;
  }
}
</style>
