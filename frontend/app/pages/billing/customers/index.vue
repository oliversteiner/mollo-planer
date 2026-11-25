<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import { archiveCustomerMutation, customersQuery } from '~/graphql/billing'
import type { ActionMenuItem } from '~/components/ActionMenu.vue'
import type { CustomerFormValue } from '~/components/billing/CustomerFormModal.vue'

/**
 * Customers of the billing (Auftrag 39 D): list, create, change, archive and bring back. Customers are never deleted;
 * an archived one keeps its invoices and is not offered for projects any more.
 */
const { t } = useI18n()
const toast = useToast()

useHead({ title: () => t('billing.customers.title') })

const showArchived = ref(false)
const { result, loading } = useQuery(customersQuery, () => ({ archived: showArchived.value }), { fetchPolicy: 'cache-and-network' })
type Customer = NonNullable<typeof result.value>['customers'][number]
const customers = computed(() => result.value?.customers ?? [])
const { mutate: archive } = useMutation(archiveCustomerMutation, { refetchQueries: 'active' })

const editing = ref<CustomerFormValue | null>(null)
const creating = ref(false)

function onSaved(_id: string, name: string): void {
  toast.add({ title: t('billing.customers.saved', { name }), color: 'success' })
}

async function setArchived(customer: Customer, archived: boolean): Promise<void> {
  const done = await archive({ id: customer.id, archived }).catch(() => null)

  if (done) {
    toast.add({ title: t(archived ? 'billing.customers.archivedDone' : 'billing.customers.backDone', { name: displayName(customer) }), color: 'success' })
  }
}

function displayName(customer: { company?: string | null, name: string }): string {
  return customer.company || customer.name
}

function actions(customer: Customer): ActionMenuItem[][] {
  return [[
    { label: t('billing.customers.edit'), icon: 'pen', onSelect: () => { editing.value = customer } },
    customer.archived
      ? { label: t('billing.customers.bringBack'), icon: 'box-open', onSelect: () => { void setArchived(customer, false) } }
      : { label: t('billing.customers.archive'), icon: 'box-archive', onSelect: () => { void setArchived(customer, true) } }
  ]]
}
</script>

<template>
  <PageFrame
    :title="t('billing.customers.title')"
    :breadcrumbs="[{ label: t('nav.billing') }, { label: t('billing.customers.title') }]"
  >
    <template #actions>
      <wa-button
        variant="brand"
        @click="creating = true"
      >
        <wa-icon
          slot="start"
          name="plus"
        ></wa-icon>
        {{ t('billing.customers.create') }}
      </wa-button>
    </template>

    <FormSwitch
      v-model="showArchived"
      :label="t('billing.customers.showArchived')"
    />

    <wa-skeleton
      v-if="loading && !result"
      effect="sheen"
      class="customers-skeleton"
    ></wa-skeleton>
    <EmptyState
      v-else-if="customers.length === 0"
      icon="address-book"
      :title="showArchived ? t('billing.customers.noneArchived') : t('billing.customers.none')"
      :description="showArchived ? undefined : t('billing.customers.noneHint')"
    />
    <ul
      v-else
      class="customers-list wa-list-plain"
    >
      <li
        v-for="customer in customers"
        :key="customer.id"
        class="customers-row"
      >
        <span class="customers-text">
          <span class="customers-name">
            <NuxtLink
              :to="`/billing/customers/${customer.id}`"
              class="customers-name-text"
            >{{ displayName(customer) }}</NuxtLink>
            <wa-badge
              v-if="customer.archived"
              variant="neutral"
              appearance="filled"
            >
              {{ t('billing.customers.archivedBadge') }}
            </wa-badge>
          </span>
          <span class="customers-meta">{{ [customer.company ? customer.name : null, `${customer.postalCode} ${customer.city}`].filter(Boolean).join(' · ') }}</span>
          <span class="customers-meta">{{ t('billing.customers.projectCount', customer.projects.length) }}</span>
        </span>
        <ActionMenu
          :items="actions(customer)"
          :label="t('billing.customers.actions', { name: displayName(customer) })"
          size="m"
        />
      </li>
    </ul>

    <BillingCustomerFormModal
      :open="creating || editing !== null"
      :customer="editing"
      @close="creating = false; editing = null"
      @saved="onSaved"
    />
  </PageFrame>
</template>

<style scoped>
.customers-skeleton {
  block-size: 6rem;
}

.customers-list {
  margin: 0;
  padding: 0;
  border: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  border-radius: var(--wa-border-radius-l);
}

.customers-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-s);
  padding: var(--wa-space-s) var(--wa-space-m);
}

.customers-row + .customers-row {
  border-block-start: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
}

.customers-text {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-inline-size: 0;
}

.customers-name {
  display: flex;
  align-items: center;
  gap: var(--wa-space-xs);
  font-weight: var(--wa-font-weight-semibold);
}

.customers-name-text {
  overflow: hidden;
  color: inherit;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.customers-meta {
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}
</style>
