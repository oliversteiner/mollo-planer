<script setup lang="ts">
/**
 * Settings of the billing (Auftrag 39 D, F): the sender of the invoices with IBAN, terms and number format, and the
 * letterhead with the layout of the PDFs.
 */
const { t } = useI18n()

useHead({ title: () => t('billing.settings.title') })
</script>

<template>
  <PageFrame
    :title="t('billing.settings.title')"
    :breadcrumbs="[{ label: t('nav.billing') }, { label: t('billing.settings.title') }]"
  >
    <div class="billing-settings">
      <BillingSenderCard />
      <BillingLetterheadCard />
    </div>
  </PageFrame>
</template>

<style scoped>
.billing-settings {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(22rem, 1fr));
  gap: var(--wa-space-l);
  align-items: start;
}
</style>
