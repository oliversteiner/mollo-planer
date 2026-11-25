<script setup lang="ts">
import { useQuery } from '@vue/apollo-composable'
import { customerQuery, invoicesQuery } from '~/graphql/billing'
import { formatDate } from '~/utils/dates'
import { formatMoney } from '~/utils/money'

/** One customer (Auftrag 39 D): the address, its projects and its invoices. */
const { t, locale } = useI18n()
const route = useRoute()
const toast = useToast()
const id = computed(() => String(route.params.id))

const { result, loading } = useQuery(customerQuery, () => ({ id: id.value }), { fetchPolicy: 'cache-and-network' })
const customer = computed(() => result.value?.customer ?? null)
const { result: invoiceList } = useQuery(invoicesQuery, () => ({ customerId: id.value }), { fetchPolicy: 'cache-and-network' })
const invoices = computed(() => invoiceList.value?.invoices ?? [])
const title = computed(() => customer.value ? (customer.value.company || customer.value.name) : t('billing.customers.title'))
const editing = ref(false)

useHead({ title: () => title.value })
</script>

<template>
  <PageFrame
    :title="title"
    :breadcrumbs="[{ label: t('nav.billing') }, { label: t('billing.customers.title'), to: '/billing/customers' }, { label: title }]"
  >
    <template
      v-if="customer"
      #actions
    >
      <wa-button
        appearance="outlined"
        variant="neutral"
        @click="editing = true"
      >
        <wa-icon
          slot="start"
          name="pen"
        ></wa-icon>
        {{ t('billing.customers.edit') }}
      </wa-button>
    </template>

    <wa-skeleton
      v-if="loading && !customer"
      effect="sheen"
      class="customer-skeleton"
    ></wa-skeleton>
    <EmptyState
      v-else-if="!customer"
      icon="address-book"
      :title="t('billing.customers.notFound')"
    />
    <div
      v-else
      class="customer-grid"
    >
      <wa-card>
        <h2
          slot="header"
          class="wa-heading-m"
        >
          {{ t('billing.customers.address') }}
        </h2>
        <div class="wa-stack wa-gap-2xs wa-body-s">
          <span v-if="customer.company">{{ customer.company }}</span>
          <span>{{ customer.name }}</span>
          <span>{{ [customer.street, customer.houseNumber].filter(Boolean).join(' ') }}</span>
          <span>{{ customer.country !== 'CH' ? `${customer.country}-` : '' }}{{ customer.postalCode }} {{ customer.city }}</span>
          <span v-if="customer.email">{{ customer.email }}</span>
          <span class="wa-color-text-quiet">{{ t('billing.customers.locale') }}: {{ t(`locale.${customer.locale}`) }}</span>
          <p
            v-if="customer.notes"
            class="wa-color-text-quiet customer-notes"
          >
            {{ customer.notes }}
          </p>
        </div>
      </wa-card>

      <wa-card>
        <h2
          slot="header"
          class="wa-heading-m"
        >
          {{ t('billing.customers.projects') }}
        </h2>
        <p
          v-if="customer.projects.length === 0"
          class="wa-body-s"
        >
          {{ t('billing.customers.noProjects') }}
        </p>
        <ul
          v-else
          class="wa-stack wa-gap-2xs customer-links"
        >
          <li
            v-for="project in customer.projects"
            :key="project.id"
          >
            <NuxtLink :to="`/projects/${project.id}`">
              {{ project.name }}
            </NuxtLink>
          </li>
        </ul>
      </wa-card>

      <wa-card class="customer-invoices">
        <h2
          slot="header"
          class="wa-heading-m"
        >
          {{ t('billing.invoices.title') }}
        </h2>
        <p
          v-if="invoices.length === 0"
          class="wa-body-s"
        >
          {{ t('billing.invoices.none') }}
        </p>
        <ul
          v-else
          class="wa-stack wa-gap-xs customer-links"
        >
          <li
            v-for="invoice in invoices"
            :key="invoice.id"
            class="customer-invoice"
          >
            <NuxtLink :to="`/billing/invoices/${invoice.id}`">
              {{ invoice.number ?? t('billing.invoices.draft') }}
            </NuxtLink>
            <span class="wa-color-text-quiet">{{ invoice.issueDate ? formatDate(invoice.issueDate, locale) : '' }}</span>
            <span class="customer-amount">{{ formatMoney(invoice.totalCents, locale) }}</span>
            <BillingInvoiceStatusBadge
              :status="invoice.status"
              :overdue="invoice.overdue"
            />
          </li>
        </ul>
      </wa-card>
    </div>

    <BillingCustomerFormModal
      :open="editing"
      :customer="customer"
      @close="editing = false"
      @saved="(_id, name) => toast.add({ title: t('billing.customers.saved', { name }), color: 'success' })"
    />
  </PageFrame>
</template>

<style scoped>
.customer-skeleton {
  block-size: 8rem;
}

.customer-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(18rem, 1fr));
  gap: var(--wa-space-l);
  align-items: start;
}

.customer-invoices {
  grid-column: 1 / -1;
}

.customer-notes {
  margin: var(--wa-space-xs) 0 0;
  white-space: pre-line;
}

.customer-links {
  margin: 0;
  padding: 0;
  list-style: none;
}

.customer-invoice {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-s);
}

.customer-amount {
  margin-inline-start: auto;
  font-variant-numeric: tabular-nums;
}
</style>
