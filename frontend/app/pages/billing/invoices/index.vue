<script setup lang="ts">
import { useQuery } from '@vue/apollo-composable'
import { customersQuery, invoicesQuery } from '~/graphql/billing'
import type { InvoiceStatus } from '~/graphql/generated/graphql'
import { formatDate } from '~/utils/dates'
import { formatMoney } from '~/utils/money'

/**
 * Invoices of the billing (Auftrag 39 D): number, customer, dates, total and status (overdue derived), filtered by
 * status, customer and year; the sums of what is open and overdue. Drafts come first.
 */
const { t, locale } = useI18n()

useHead({ title: () => t('billing.invoices.title') })

const status = ref('')
const customerId = ref('')
const year = ref('')

const { result, loading } = useQuery(invoicesQuery, () => ({
  status: (status.value || null) as InvoiceStatus | null,
  customerId: customerId.value || null,
  year: year.value ? Number(year.value) : null
}), { fetchPolicy: 'cache-and-network' })
const { result: customerList } = useQuery(customersQuery, { archived: false }, { fetchPolicy: 'cache-and-network' })

const invoices = computed(() => result.value?.invoices ?? [])
const totals = computed(() => result.value?.invoiceTotals ?? null)
const filtered = computed(() => Boolean(status.value || customerId.value || year.value))

const statusItems = computed(() => [
  { label: t('billing.invoices.allStatuses'), value: '' },
  ...(['DRAFT', 'ISSUED', 'PAID', 'CANCELLED'] as const).map(value => ({ label: t(`billing.status.${value}`), value }))
])
const customerItems = computed(() => [
  { label: t('billing.invoices.allCustomers'), value: '' },
  ...(customerList.value?.customers ?? []).map(customer => ({ label: customerName(customer), value: customer.id }))
])
const yearItems = computed(() => {
  const now = new Date().getFullYear()

  return [{ label: t('billing.invoices.allYears'), value: '' }, ...[0, 1, 2, 3, 4].map(back => ({ label: String(now - back), value: String(now - back) }))]
})

function customerName(customer: { company?: string | null, name: string }): string {
  return customer.company || customer.name
}

function money(cents: number): string {
  return formatMoney(cents, locale.value)
}
</script>

<template>
  <PageFrame
    :title="t('billing.invoices.title')"
    :breadcrumbs="[{ label: t('nav.billing') }, { label: t('billing.invoices.title') }]"
  >
    <template #actions>
      <wa-button
        variant="brand"
        href="/billing/billable"
        @click.prevent="navigateTo('/billing/billable')"
      >
        <wa-icon
          slot="start"
          name="plus"
        ></wa-icon>
        {{ t('billing.invoices.create') }}
      </wa-button>
    </template>

    <ul
      v-if="totals"
      class="invoices-totals wa-list-plain"
    >
      <li>
        <wa-card>
          <span class="invoices-total">
            <span class="invoices-total-label">{{ t('billing.invoices.open') }}</span>
            <span class="invoices-total-value">CHF {{ money(totals.openCents) }}</span>
            <span class="invoices-total-sub">{{ t('billing.invoices.count', totals.openCount) }}</span>
          </span>
        </wa-card>
      </li>
      <li>
        <wa-card>
          <span class="invoices-total">
            <span class="invoices-total-label">{{ t('billing.invoices.overdue') }}</span>
            <span
              class="invoices-total-value"
              :data-alert="totals.overdueCount ? '' : undefined"
            >CHF {{ money(totals.overdueCents) }}</span>
            <span class="invoices-total-sub">{{ t('billing.invoices.count', totals.overdueCount) }}</span>
          </span>
        </wa-card>
      </li>
    </ul>

    <div class="invoices-filters wa-cluster wa-gap-s">
      <FormSelect
        v-model="status"
        name="status"
        :items="statusItems"
        :label="t('billing.invoices.status')"
      />
      <FormSelect
        v-model="customerId"
        name="customer"
        :items="customerItems"
        :label="t('billing.invoices.customer')"
      />
      <FormSelect
        v-model="year"
        name="year"
        :items="yearItems"
        :label="t('billing.invoices.year')"
      />
    </div>

    <wa-skeleton
      v-if="loading && !result"
      effect="sheen"
      class="invoices-skeleton"
    ></wa-skeleton>
    <EmptyState
      v-else-if="invoices.length === 0"
      icon="file-invoice-dollar"
      :title="filtered ? t('billing.invoices.noneFiltered') : t('billing.invoices.none')"
      :description="filtered ? undefined : t('billing.invoices.noneHint')"
    />
    <div
      v-else
      class="invoices-frame"
      tabindex="0"
      :aria-label="t('billing.invoices.title')"
    >
      <table class="invoices-table">
        <thead>
          <tr>
            <th scope="col">
              {{ t('billing.invoices.number') }}
            </th>
            <th scope="col">
              {{ t('billing.invoices.customer') }}
            </th>
            <th scope="col">
              {{ t('billing.invoices.issueDate') }}
            </th>
            <th scope="col">
              {{ t('billing.invoices.dueDate') }}
            </th>
            <th
              scope="col"
              class="invoices-cell-number"
            >
              {{ t('billing.invoices.total') }}
            </th>
            <th scope="col">
              {{ t('billing.invoices.status') }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="invoice in invoices"
            :key="invoice.id"
          >
            <th scope="row">
              <NuxtLink
                :to="`/billing/invoices/${invoice.id}`"
                class="invoices-link"
              >
                {{ invoice.number ?? t('billing.invoices.draft') }}
              </NuxtLink>
            </th>
            <td>{{ customerName(invoice.customer) }}</td>
            <td>{{ invoice.issueDate ? formatDate(invoice.issueDate, locale) : '–' }}</td>
            <td>{{ invoice.dueDate ? formatDate(invoice.dueDate, locale) : '–' }}</td>
            <td class="invoices-cell-number">
              {{ money(invoice.totalCents) }}
            </td>
            <td>
              <BillingInvoiceStatusBadge
                :status="invoice.status"
                :overdue="invoice.overdue"
              />
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </PageFrame>
</template>

<style scoped>
.invoices-totals {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(14rem, 1fr));
  gap: var(--wa-space-m);
  margin: 0;
  padding: 0;
}

.invoices-total {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-3xs);
}

.invoices-total-label,
.invoices-total-sub {
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}

.invoices-total-value {
  font-size: var(--wa-font-size-xl);
  font-weight: var(--wa-font-weight-semibold);
  font-variant-numeric: tabular-nums;
}

.invoices-total-value[data-alert] {
  color: var(--wa-color-danger-on-quiet);
}

.invoices-skeleton {
  block-size: 6rem;
}

.invoices-frame {
  overflow: auto;
}

.invoices-frame:focus-visible {
  outline: var(--wa-focus-ring);
  outline-offset: var(--wa-focus-ring-offset);
}

.invoices-table {
  inline-size: 100%;
  border-collapse: separate;
  border-spacing: 0;
  font-size: var(--wa-font-size-s);
}

.invoices-table th,
.invoices-table td {
  padding: var(--wa-space-xs) var(--wa-space-s);
  border-block-end: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  text-align: start;
  vertical-align: middle;
  white-space: nowrap;
}

.invoices-table tbody tr:last-child th,
.invoices-table tbody tr:last-child td {
  border-block-end: none;
}

.invoices-table thead th {
  color: var(--wa-color-text-quiet);
  font-weight: var(--wa-font-weight-semibold);
}

.invoices-table .invoices-cell-number {
  font-variant-numeric: tabular-nums;
  text-align: end;
}

.invoices-link {
  color: inherit;
  font-weight: var(--wa-font-weight-semibold);
}
</style>
