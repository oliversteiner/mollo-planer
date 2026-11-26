<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import { billingOverviewQuery, createInvoiceDraftMutation } from '~/graphql/billing'
import { formatMoney } from '~/utils/money'

/**
 * What can be billed (Auftrag 39 D): done milestones with a price in projects with a customer, held by no invoice,
 * grouped by customer and project with sums. Chosen milestones of one customer become a draft. Hints name done
 * milestones that lack a price, and projects that lack a customer.
 */
const { t, locale } = useI18n()
const toast = useToast()

useHead({ title: () => t('billing.billable.title') })

const { result, loading } = useQuery(billingOverviewQuery, null, { fetchPolicy: 'cache-and-network' })
const overview = computed(() => result.value?.billingOverview ?? null)
const { mutate: createDraft } = useMutation(createInvoiceDraftMutation, { refetchQueries: 'active' })

/** Chosen milestones per customer (one invoice goes to one customer). */
const chosen = reactive<Record<string, string[]>>({})
const creating = ref<string | null>(null)

watch(overview, (current) => {
  for (const group of current?.groups ?? []) {
    const ids = group.projects.flatMap(project => project.milestones.map(milestone => milestone.id))
    // A new group starts with everything chosen; what is gone (billed meanwhile) drops out.
    chosen[group.customer.id] = chosen[group.customer.id]?.filter(id => ids.includes(id)) ?? ids
  }
}, { immediate: true })

function isChosen(customerId: string, milestoneId: string): boolean {
  return chosen[customerId]?.includes(milestoneId) ?? false
}

function toggle(customerId: string, milestoneId: string, on: boolean): void {
  const list = chosen[customerId] ?? []
  chosen[customerId] = on ? [...new Set([...list, milestoneId])] : list.filter(id => id !== milestoneId)
}

function chosenSum(group: NonNullable<typeof overview.value>['groups'][number]): number {
  return group.projects.flatMap(project => project.milestones)
    .filter(milestone => isChosen(group.customer.id, milestone.id))
    .reduce((sum, milestone) => sum + milestone.priceCents, 0)
}

async function create(customerId: string): Promise<void> {
  const ids = chosen[customerId] ?? []

  if (!ids.length || creating.value) {
    return
  }
  creating.value = customerId

  try {
    const made = await createDraft({ customerId, milestoneIds: ids }).catch(() => null)
    const id = made?.data?.createInvoiceDraft.id

    if (id) {
      toast.add({ title: t('billing.billable.created'), color: 'success' })
      await navigateTo(`/billing/invoices/${id}`)
    }
  } finally {
    creating.value = null
  }
}

function money(cents: number): string {
  return formatMoney(cents, locale.value)
}
</script>

<template>
  <PageFrame
    :title="t('billing.billable.title')"
    :breadcrumbs="[{ label: t('nav.billing') }, { label: t('billing.billable.title') }]"
  >
    <p class="wa-body-s wa-color-text-quiet billable-intro">
      {{ t('billing.billable.intro') }}
    </p>

    <wa-skeleton
      v-if="loading && !overview"
      effect="sheen"
      class="billable-skeleton"
    ></wa-skeleton>
    <template v-else-if="overview">
      <EmptyState
        v-if="overview.groups.length === 0"
        icon="list-check"
        :title="t('billing.billable.none')"
        :description="t('billing.billable.noneHint')"
      />

      <wa-card
        v-for="group in overview.groups"
        :key="group.customer.id"
        class="billable-card"
      >
        <div
          slot="header"
          class="wa-split wa-gap-s"
        >
          <h2 class="wa-heading-m">
            {{ group.customer.company || group.customer.name }}
          </h2>
          <span class="billable-amount">CHF {{ money(group.totalCents) }}</span>
        </div>

        <div class="wa-stack wa-gap-l">
          <section
            v-for="project in group.projects"
            :key="project.projectId"
            class="wa-stack wa-gap-xs"
          >
            <h3 class="wa-heading-s billable-project">
              <NuxtLink :to="`/projects/${project.projectId}`">
                {{ project.projectName }}
              </NuxtLink>
              <span class="billable-amount">{{ money(project.totalCents) }}</span>
            </h3>
            <ul class="billable-list wa-list-plain">
              <li
                v-for="milestone in project.milestones"
                :key="milestone.id"
                class="billable-row"
              >
                <FormCheckbox
                  :model-value="isChosen(group.customer.id, milestone.id)"
                  :label="milestone.releaseVersion ? `${milestone.title} (${milestone.releaseVersion})` : milestone.title"
                  @update:model-value="toggle(group.customer.id, milestone.id, $event)"
                />
                <span class="billable-amount">{{ money(milestone.priceCents) }}</span>
              </li>
            </ul>
          </section>
        </div>

        <div
          slot="footer"
          class="wa-split wa-gap-s"
        >
          <span class="wa-body-s">{{ t('billing.billable.chosen', { count: chosen[group.customer.id]?.length ?? 0, amount: money(chosenSum(group)) }) }}</span>
          <wa-button
            variant="brand"
            :disabled="!chosen[group.customer.id]?.length"
            :loading="creating === group.customer.id"
            @click="create(group.customer.id)"
          >
            <wa-icon
              slot="start"
              name="file-invoice-dollar"
            ></wa-icon>
            {{ t('billing.billable.create') }}
          </wa-button>
        </div>
      </wa-card>

      <AppCallout
        v-if="overview.withoutPrice.length"
        variant="warning"
        :title="t('billing.billable.withoutPrice', overview.withoutPrice.length)"
      >
        <ul class="billable-hints">
          <li
            v-for="hint in overview.withoutPrice"
            :key="hint.milestoneId"
          >
            <NuxtLink :to="`/projects/${hint.projectId}#milestone-${hint.milestoneId}`">
              {{ hint.projectName }} · {{ hint.milestoneTitle }}
            </NuxtLink>
          </li>
        </ul>
      </AppCallout>
      <AppCallout
        v-if="overview.withoutCustomer.length"
        variant="warning"
        :title="t('billing.billable.withoutCustomer', overview.withoutCustomer.length)"
      >
        <ul class="billable-hints">
          <li
            v-for="hint in overview.withoutCustomer"
            :key="hint.milestoneId"
          >
            <NuxtLink :to="`/projects/${hint.projectId}/settings`">
              {{ hint.projectName }} · {{ hint.milestoneTitle }}
            </NuxtLink>
          </li>
        </ul>
      </AppCallout>
    </template>
  </PageFrame>
</template>

<style scoped>
.billable-intro {
  max-inline-size: 42rem;
}

.billable-skeleton {
  block-size: 8rem;
}

.billable-card {
  max-inline-size: 48rem;
}

.billable-project {
  display: flex;
  justify-content: space-between;
  gap: var(--wa-space-s);
  margin: 0;
}

.billable-list {
  margin: 0;
  padding: 0;
}

.billable-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--wa-space-s);
  padding-block: var(--wa-space-2xs);
}

.billable-amount {
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.billable-hints {
  margin: var(--wa-space-xs) 0 0;
  padding-inline-start: var(--wa-space-l);
}
</style>
