<script setup lang="ts">
import { useMutation } from '@vue/apollo-composable'
import { createCustomerMutation, updateCustomerMutation } from '~/graphql/billing'
import type { Locale } from '~/graphql/generated/graphql'

export interface CustomerFormValue {
  id: string
  company?: string | null
  name: string
  street: string
  houseNumber?: string | null
  postalCode: string
  city: string
  country: string
  email?: string | null
  locale: Locale
  notes?: string | null
}

/**
 * Create or change a customer of the billing (Auftrag 39 D): the structured address of the QR bill (street and house
 * number apart, postal code, town, country), e-mail, language of the invoices and an internal note. Invoices already
 * issued keep the address they were issued with.
 */
const props = defineProps<{ open: boolean, customer?: CustomerFormValue | null }>()
const emit = defineEmits<{ close: [], saved: [id: string, name: string] }>()

const { t } = useI18n()
const { errors, pending, run } = useGraphqlForm()
const { mutate: create } = useMutation(createCustomerMutation, { refetchQueries: 'active', awaitRefetchQueries: true })
const { mutate: update } = useMutation(updateCustomerMutation, { refetchQueries: 'active', awaitRefetchQueries: true })

const empty = { company: '', name: '', street: '', houseNumber: '', postalCode: '', city: '', country: 'CH', email: '', locale: 'DE' as Locale, notes: '' }
const state = reactive({ ...empty })

watch(() => props.open, (open) => {
  if (open) {
    errors.value = {}
    const customer = props.customer
    Object.assign(state, customer
      ? { company: customer.company ?? '', name: customer.name, street: customer.street, houseNumber: customer.houseNumber ?? '', postalCode: customer.postalCode, city: customer.city, country: customer.country, email: customer.email ?? '', locale: customer.locale, notes: customer.notes ?? '' }
      : empty)
  }
}, { immediate: true })

const open = computed({
  get: () => props.open,
  set: (value: boolean) => {
    if (!value) {
      emit('close')
    }
  }
})

const locales = computed(() => [
  { label: t('locale.DE'), value: 'DE' },
  { label: t('locale.EN'), value: 'EN' }
])

async function submit(): Promise<void> {
  const input = {
    company: state.company.trim() || null,
    name: state.name.trim(),
    street: state.street.trim(),
    houseNumber: state.houseNumber.trim() || null,
    postalCode: state.postalCode.trim(),
    city: state.city.trim(),
    country: state.country.trim().toUpperCase(),
    email: state.email.trim() || null,
    locale: state.locale,
    notes: state.notes.trim() || null
  }
  const id = props.customer?.id
  const savedId = await run(async () => id
    ? (await update({ id, input }))?.data?.updateCustomer.id
    : (await create({ input }))?.data?.createCustomer.id)

  if (savedId) {
    emit('saved', savedId, input.company ?? input.name)
    emit('close')
  }
}
</script>

<template>
  <AppDialog
    v-model:open="open"
    :label="customer ? t('billing.customers.edit') : t('billing.customers.create')"
    :description="t('billing.customers.formHint')"
  >
    <form
      id="customer-form"
      class="wa-stack wa-gap-m"
      @submit.prevent="submit"
    >
      <FormInput
        v-model="state.company"
        name="company"
        :label="t('billing.customers.company')"
        :error="errors.company"
        autocomplete="organization"
      />
      <FormInput
        v-model="state.name"
        name="name"
        :label="t('billing.customers.name')"
        :error="errors.name"
        autocomplete="name"
        required
      />
      <div class="customer-pair">
        <FormInput
          v-model="state.street"
          name="street"
          :label="t('billing.address.street')"
          :error="errors.street"
          required
        />
        <FormInput
          v-model="state.houseNumber"
          name="houseNumber"
          :label="t('billing.address.houseNumber')"
          :error="errors.houseNumber"
        />
      </div>
      <div class="customer-pair customer-pair-start">
        <FormInput
          v-model="state.postalCode"
          name="postalCode"
          :label="t('billing.address.postalCode')"
          :error="errors.postalCode"
          required
        />
        <FormInput
          v-model="state.city"
          name="city"
          :label="t('billing.address.city')"
          :error="errors.city"
          required
        />
      </div>
      <FormInput
        v-model="state.country"
        name="country"
        :label="t('billing.address.country')"
        :hint="t('billing.address.countryHint')"
        :error="errors.country"
        maxlength="2"
        required
      />
      <FormInput
        v-model="state.email"
        type="email"
        name="email"
        :label="t('billing.customers.email')"
        :error="errors.email"
      />
      <FormSelect
        v-model="state.locale"
        name="locale"
        :items="locales"
        :label="t('billing.customers.locale')"
        :hint="t('billing.customers.localeHint')"
        :error="errors.locale"
      />
      <FormTextarea
        v-model="state.notes"
        name="notes"
        :label="t('billing.customers.notes')"
        :hint="t('billing.customers.notesHint')"
        :error="errors.notes"
      />
    </form>

    <template #footer>
      <wa-button
        appearance="plain"
        variant="neutral"
        @click="open = false"
      >
        {{ t('common.cancel') }}
      </wa-button>
      <wa-button
        type="submit"
        form="customer-form"
        variant="brand"
        :loading="pending"
      >
        {{ t('common.save') }}
      </wa-button>
    </template>
  </AppDialog>
</template>

<style scoped>
.customer-pair {
  display: grid;
  grid-template-columns: minmax(0, 3fr) minmax(0, 1fr);
  gap: var(--wa-space-s);
}

.customer-pair-start {
  grid-template-columns: minmax(0, 1fr) minmax(0, 3fr);
}
</style>
