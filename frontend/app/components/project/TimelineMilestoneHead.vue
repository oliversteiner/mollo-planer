<script setup lang="ts">
import type { ActionMenuItem } from '~/components/ActionMenu.vue'
import { formatMoney } from '~/utils/money'
import { priceBadge, type MilestonePriceInput } from '~/utils/milestone-price'

/**
 * The head of a milestone on the project page (docs/ux-projektseite.md §4.2), open and closed the same. One button
 * (arrow, number, title, status) opens and closes it, like `todo-toggle` does for the todos; version, price, due date
 * and the menu stand to the right. The price may be a link to the invoice, so it cannot sit inside the button.
 */
type ItemStatusName = 'IDEA' | 'PLANNED' | 'OPEN' | 'IN_PROGRESS' | 'REVIEW' | 'DONE'

export interface MilestoneHeadData extends MilestonePriceInput {
  id: string
  title: string
  dueDate?: string | null
  release?: { version: string, status: string, date?: string | null } | null
}

const props = defineProps<{
  milestone: MilestoneHeadData
  number: number
  open: boolean
  menu?: ActionMenuItem[]
  /** The viewer has billing.manage: a done milestone without a price is a hint. */
  canBill?: boolean
  /** «Preise anzeigen» is off (a shared screen): no price and no hint (Auftrag 47 G). */
  hidePrices?: boolean
}>()

const emit = defineEmits<{
  /** The arrow was used; `all`: with the Option key, also all todos. */
  toggle: [all: boolean]
  /** Arrow keys on the focused head: open (`true`) or close (`false`). */
  set: [open: boolean]
}>()

const { t, localeProperties } = useI18n()
const language = computed(() => localeProperties.value.language ?? 'de-CH')
const status = computed(() => props.milestone.status as ItemStatusName)
const badge = computed(() => (props.hidePrices ? null : priceBadge(props.milestone, props.canBill)))
const released = computed(() => props.milestone.release?.status === 'RELEASED')
/** A released milestone shows the date of the release, never both (B8). */
const date = computed(() => (released.value ? props.milestone.release?.date : null) ?? props.milestone.dueDate ?? null)

function onClick(event: MouseEvent): void {
  emit('toggle', event.altKey)
}

/** Right opens, left closes; only when that changes something, so the arrow keys stay free otherwise. */
function onArrow(event: KeyboardEvent, opening: boolean): void {
  if (opening !== props.open) {
    event.preventDefault()
    emit('set', opening)
  }
}
</script>

<template>
  <div
    :id="`milestone-${milestone.id}`"
    class="milestone-head"
  >
    <button
      :id="`milestone-${milestone.id}-toggle`"
      type="button"
      class="milestone-toggle plain-button"
      :class="{ 'milestone-toggle-open': open }"
      :aria-label="t('project.timeline.toggleMilestone', { title: milestone.title })"
      :title="t('project.timeline.toggleMilestoneHint')"
      :aria-expanded="open"
      :aria-controls="`milestone-${milestone.id}-content`"
      @click="onClick"
      @keydown.right.exact="onArrow($event, true)"
      @keydown.left.exact="onArrow($event, false)"
    >
      <wa-icon
        name="chevron-right"
        class="milestone-chevron"
      ></wa-icon>
      <MilestoneNumber :number="number" />
      <span
        class="milestone-title"
        :title="milestone.title"
      >{{ milestone.title }}</span>
      <StatusBadge :status="status" />
    </button>

    <div class="milestone-meta">
      <span
        v-if="milestone.release"
        class="milestone-release wa-caption-s"
        data-zen="hide"
      >{{ milestone.release.version }} · {{ t(`release.${milestone.release.status.toLowerCase()}`) }}</span>

      <!-- Billing (Auftrag 39, 62 C): only with billing.manage the API gives a price; otherwise there is nothing to show. -->
      <div
        v-if="badge"
        class="milestone-price"
        data-zen="hide"
      >
        <wa-badge
          v-if="badge.kind === 'noPrice'"
          variant="warning"
          appearance="plain"
        >
          <wa-icon
            name="coins"
            class="milestone-price-icon"
          ></wa-icon>
          {{ t('project.timeline.noPrice') }}
        </wa-badge>
        <wa-badge
          v-else-if="badge.kind === 'price'"
          variant="neutral"
          appearance="outlined"
        >
          <wa-icon
            name="coins"
            class="milestone-price-icon"
          ></wa-icon>
          {{ t('project.timeline.price', { amount: formatMoney(badge.cents, language) }) }}
        </wa-badge>
        <NuxtLink
          v-else
          :to="`/billing/invoices/${badge.invoiceId}`"
          class="milestone-invoice"
        >
          <wa-badge
            :variant="badge.kind === 'billed' ? 'success' : 'neutral'"
            appearance="outlined"
          >
            <wa-icon
              :name="badge.kind === 'billed' ? 'file-invoice' : 'file-pen'"
              class="milestone-price-icon"
            ></wa-icon>
            {{ t('project.timeline.price', { amount: formatMoney(badge.cents, language) }) }}
            ·
            {{ badge.kind === 'billed' && badge.number ? t('billing.milestone.billed', { number: badge.number }) : t('billing.milestone.billedDraft') }}
          </wa-badge>
        </NuxtLink>
      </div>

      <DueDate
        v-if="date"
        :date="date"
        :done="milestone.status === 'DONE' || released"
        class="milestone-date"
        data-zen="hide"
      />
      <span
        v-else
        class="milestone-date milestone-no-date wa-caption-s"
        data-zen="hide"
      >{{ t('project.timeline.noDate') }}</span>
    </div>

    <ActionMenu
      v-if="menu?.length"
      class="milestone-menu"
      data-zen="hide"
      :items="menu"
      :label="t('project.timeline.milestoneActions', { title: milestone.title })"
    />
  </div>
</template>

<style scoped>
.milestone-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-3xs) var(--wa-space-s);
  inline-size: 100%;
  min-inline-size: 0;
}

.milestone-toggle {
  display: flex;
  flex: 1 1 14rem;
  align-items: center;
  gap: var(--wa-space-xs);
  min-inline-size: 0;
  min-block-size: 1.5rem;
  padding: var(--wa-space-3xs) var(--wa-space-xs);
  border: none;
  border-radius: var(--wa-border-radius-m);
  background: none;
  color: inherit;
  font: inherit;
  text-align: start;
  cursor: pointer;
}

.milestone-toggle:hover {
  background-color: var(--wa-color-neutral-fill-quiet);
}

.milestone-toggle:focus-visible {
  outline: var(--wa-focus-ring);
  outline-offset: var(--wa-focus-ring-offset);
}

.milestone-chevron {
  flex: none;
  color: var(--wa-color-text-quiet);
  transition: transform 150ms ease;
}

.milestone-toggle-open .milestone-chevron {
  transform: rotate(90deg);
}

@media (prefers-reduced-motion: reduce) {
  .milestone-chevron {
    transition: none;
  }
}

.milestone-title {
  flex: 0 1 auto;
  overflow: hidden;
  font-weight: var(--wa-font-weight-semibold);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.milestone-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: var(--wa-space-3xs) var(--wa-space-s);
  margin-inline-start: auto;
}

.milestone-release {
  color: var(--wa-color-text-quiet);
  font-weight: var(--wa-font-weight-normal);
  white-space: nowrap;
}

.milestone-price-icon {
  margin-inline-end: var(--wa-space-3xs);
}

.milestone-invoice {
  display: inline-flex;
  text-decoration: none;
}

.milestone-date {
  min-inline-size: 7rem;
  text-align: end;
  font-weight: var(--wa-font-weight-normal);
}

.milestone-no-date {
  color: var(--wa-color-text-quiet);
}

/* Narrow window: two rows, the arrow row on top and the facts below (docs/ux-projektseite.md §4.7). */
@media (max-width: 40em) {
  .milestone-toggle {
    min-block-size: 2.75rem;
  }

  .milestone-meta {
    order: 3;
    flex-basis: 100%;
    justify-content: flex-start;
    margin-inline-start: 0;
  }

  .milestone-menu {
    order: 2;
  }

  .milestone-date {
    min-inline-size: 0;
    text-align: start;
  }
}
</style>
