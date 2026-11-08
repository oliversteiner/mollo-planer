<script setup lang="ts">
const { t } = useI18n()

useHead({ title: () => t('dashboard.title') })
</script>

<template>
  <UDashboardPanel id="dashboard">
    <template #header>
      <UDashboardNavbar :title="t('dashboard.title')" />
    </template>

    <template #body>
      <p class="text-muted">
        {{ t('dashboard.comingSoon') }}
      </p>
    </template>
  </UDashboardPanel>
</template>
