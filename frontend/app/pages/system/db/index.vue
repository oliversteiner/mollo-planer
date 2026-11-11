<script setup lang="ts">
import { DB_TABLE_NAMES } from '~/utils/db-schema'

definePageMeta({ middleware: 'admin' })

// Opening /system/db shows the first table.
await navigateTo({ path: `/system/db/${DB_TABLE_NAMES[0]}`, replace: true })
</script>

<template>
  <div />
</template>
