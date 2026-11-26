<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import { billingSettingsQuery, updateBillingSettingsMutation } from '~/graphql/billing'

/**
 * Sender of the invoices and the terms (Auftrag 39 D): address (structured, as the QR bill wants it), contact, IBAN,
 * UID (may stay empty, no VAT), payment terms and the format of the number. Saving needs a fresh password, asked in
 * a field of this card (R11). What an invoice still needs is said by field.
 */
const { t } = useI18n()
const toast = useToast()
const step = usePasswordStep()
const { errors, run } = useGraphqlForm()

const { result } = useQuery(billingSettingsQuery, null, { fetchPolicy: 'cache-and-network' })
const settings = computed(() => result.value?.billingSettings ?? null)
const { mutate } = useMutation(updateBillingSettingsMutation, { refetchQueries: ['BillingSettings'], awaitRefetchQueries: true })

const state = reactive({ name: '', street: '', houseNumber: '', postalCode: '', city: '', country: 'CH', email: '', phone: '', website: '', iban: '', uid: '', paymentTermsDays: '30', numberFormat: '{YYYY}-{NNNN}' })

watch(settings, (current) => {
  if (current) {
    Object.assign(state, {
      name: current.name ?? '',
      street: current.street ?? '',
      houseNumber: current.houseNumber ?? '',
      postalCode: current.postalCode ?? '',
      city: current.city ?? '',
      country: current.country,
      email: current.email ?? '',
      phone: current.phone ?? '',
      website: current.website ?? '',
      iban: current.iban ?? '',
      uid: current.uid ?? '',
      paymentTermsDays: String(current.paymentTermsDays),
      numberFormat: current.numberFormat
    })
  }
}, { immediate: true })

/** The labels of the fields an invoice still needs. */
const missing = computed(() => (settings.value?.missingFields ?? []).map(field => t(`billing.sender.fields.${field}`)))

async function submit(): Promise<void> {
  if (step.working) {
    return
  }

  await step.run(async () => {
    const saved = await run(() => mutate({
      input: {
        name: state.name,
        street: state.street,
        houseNumber: state.houseNumber,
        postalCode: state.postalCode,
        city: state.city,
        country: state.country.trim().toUpperCase(),
        email: state.email,
        phone: state.phone,
        website: state.website,
        iban: state.iban,
        uid: state.uid,
        paymentTermsDays: Number(state.paymentTermsDays) || 0,
        numberFormat: state.numberFormat.trim()
      }
    }))

    if (errors.value.password) {
      return NEEDS_PASSWORD
    }

    if (saved) {
      toast.add({ title: t('billing.sender.saved'), color: 'success' })
    }
  })
}
</script>

<template>
  <wa-card>
    <h2
      slot="header"
      class="wa-heading-m"
    >
      {{ t('billing.sender.title') }}
    </h2>

    <wa-skeleton
      v-if="!settings"
      effect="sheen"
      class="sender-skeleton"
    ></wa-skeleton>
    <form
      v-else
      class="wa-stack wa-gap-m"
      @submit.prevent="submit"
    >
      <AppCallout
        v-if="missing.length"
        variant="warning"
        :title="t('billing.sender.missing', { fields: missing.join(', ') })"
      />
      <FormInput
        v-model="state.name"
        name="name"
        :label="t('billing.sender.fields.name')"
        :error="errors.name"
        autocomplete="organization"
      />
      <div class="sender-pair">
        <FormInput
          v-model="state.street"
          name="street"
          :label="t('billing.address.street')"
          :error="errors.street"
        />
        <FormInput
          v-model="state.houseNumber"
          name="houseNumber"
          :label="t('billing.address.houseNumber')"
          :error="errors.houseNumber"
        />
      </div>
      <div class="sender-pair sender-pair-start">
        <FormInput
          v-model="state.postalCode"
          name="postalCode"
          :label="t('billing.address.postalCode')"
          :error="errors.postalCode"
        />
        <FormInput
          v-model="state.city"
          name="city"
          :label="t('billing.address.city')"
          :error="errors.city"
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
        :label="t('billing.sender.fields.email')"
        :error="errors.email"
      />
      <FormInput
        v-model="state.phone"
        type="tel"
        name="phone"
        :label="t('billing.sender.fields.phone')"
        :error="errors.phone"
      />
      <FormInput
        v-model="state.website"
        name="website"
        :label="t('billing.sender.fields.website')"
        :error="errors.website"
      />
      <FormInput
        v-model="state.iban"
        name="iban"
        :label="t('billing.sender.fields.iban')"
        :hint="t('billing.sender.ibanHint')"
        :error="errors.iban"
        autocomplete="off"
      />
      <FormInput
        v-model="state.uid"
        name="uid"
        :label="t('billing.sender.fields.uid')"
        :hint="t('billing.sender.uidHint')"
        :error="errors.uid"
      />
      <FormInput
        v-model="state.paymentTermsDays"
        type="number"
        name="paymentTermsDays"
        min="0"
        max="365"
        :label="t('billing.sender.paymentTermsDays')"
        :error="errors.paymentTermsDays"
        required
      />
      <FormInput
        v-model="state.numberFormat"
        name="numberFormat"
        :label="t('billing.sender.numberFormat')"
        :hint="t('billing.sender.numberFormatHint')"
        :error="errors.numberFormat"
        required
      />
      <PasswordConfirmField :step="step" />
      <div>
        <wa-button
          type="submit"
          variant="brand"
          :loading="step.working"
          :disabled="step.waiting"
        >
          {{ t('common.save') }}
        </wa-button>
      </div>
    </form>
  </wa-card>
</template>

<style scoped>
.sender-skeleton {
  block-size: 12rem;
}

.sender-pair {
  display: grid;
  grid-template-columns: minmax(0, 3fr) minmax(0, 1fr);
  gap: var(--wa-space-s);
}

.sender-pair-start {
  grid-template-columns: minmax(0, 1fr) minmax(0, 3fr);
}
</style>
