<script setup lang="ts">
import type { InvoiceStatus } from '~/graphql/generated/graphql'

/** Status of an invoice (Auftrag 39); an issued invoice past its due date shows «überfällig» (derived, no status). */
const props = defineProps<{ status: InvoiceStatus, overdue?: boolean }>()

const { t } = useI18n()

const look = computed(() => {
  if (props.status === 'ISSUED' && props.overdue) {
    return { variant: 'danger', appearance: 'filled', label: t('billing.status.OVERDUE') }
  }

  return {
    DRAFT: { variant: 'neutral', appearance: 'outlined', label: t('billing.status.DRAFT') },
    ISSUED: { variant: 'brand', appearance: 'outlined', label: t('billing.status.ISSUED') },
    PAID: { variant: 'success', appearance: 'filled', label: t('billing.status.PAID') },
    CANCELLED: { variant: 'neutral', appearance: 'filled', label: t('billing.status.CANCELLED') }
  }[props.status]
})
</script>

<template>
  <wa-badge
    :variant="look.variant"
    :appearance="look.appearance"
  >
    {{ look.label }}
  </wa-badge>
</template>
