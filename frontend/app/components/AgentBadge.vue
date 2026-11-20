<script setup lang="ts">
/** The mark of an agent account (Claude, an assistant): beside the name wherever a person is shown. */
const { t } = useI18n()
</script>

<template>
  <wa-badge
    :title="t('agent.hint')"
    variant="neutral"
    appearance="outlined"
  >
    {{ t('agent.badge') }}
  </wa-badge>
</template>
