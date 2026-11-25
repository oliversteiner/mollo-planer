<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import { customersQuery, setProjectCustomerMutation } from '~/graphql/billing'

/**
 * The customer of the project (Auftrag 39 D), only with billing.manage: done milestones with a price are billed to
 * it. Only active customers are offered; one archived meanwhile stays shown until it is changed.
 */
const props = defineProps<{ projectId: string, customer: { id: string, company?: string | null, name: string } | null }>()

const { t } = useI18n()
const toast = useToast()
const { errors, pending, run } = useGraphqlForm()
const { result } = useQuery(customersQuery, { archived: false }, { fetchPolicy: 'cache-and-network' })
const { mutate } = useMutation(setProjectCustomerMutation, { refetchQueries: 'active' })

const customerId = ref('')

watch(() => props.customer, (customer) => {
  customerId.value = customer?.id ?? ''
}, { immediate: true })

const items = computed(() => {
  const active = (result.value?.customers ?? []).map(customer => ({ label: customer.company || customer.name, value: customer.id }))
  const current = props.customer && !active.some(item => item.value === props.customer?.id)
    ? [{ label: props.customer.company || props.customer.name, value: props.customer.id }]
    : []

  return [{ label: t('billing.project.noCustomer'), value: '' }, ...current, ...active]
})

async function submit(): Promise<void> {
  const saved = await run(() => mutate({ projectId: props.projectId, customerId: customerId.value || null }))

  if (saved) {
    toast.add({ title: t('billing.project.saved'), color: 'success' })
  }
}
</script>

<template>
  <wa-card>
    <h2
      slot="header"
      class="wa-heading-m"
    >
      {{ t('billing.project.title') }}
    </h2>

    <form
      class="wa-stack wa-gap-m"
      @submit.prevent="submit"
    >
      <FormSelect
        v-model="customerId"
        name="customerId"
        :items="items"
        :label="t('billing.project.customer')"
        :hint="t('billing.project.customerHint')"
        :error="errors.customerId"
        searchable
      />
      <div class="wa-cluster wa-gap-s">
        <wa-button
          type="submit"
          variant="brand"
          :loading="pending"
        >
          {{ t('common.save') }}
        </wa-button>
        <wa-button
          appearance="plain"
          variant="neutral"
          href="/billing/customers"
          @click.prevent="navigateTo('/billing/customers')"
        >
          {{ t('billing.project.manage') }}
        </wa-button>
      </div>
    </form>
  </wa-card>
</template>
