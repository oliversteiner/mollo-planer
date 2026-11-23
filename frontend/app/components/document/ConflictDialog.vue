<script setup lang="ts">
/**
 * Somebody else saved the document while it was being edited (DOCUMENT_CONFLICT, docs/dokumentenverwaltung.md
 * §6). Nothing was saved. The own changes are shown nowhere else, so this is a dialog and not only a message:
 * reload (the own changes are dropped from the screen, the draft in this browser stays) or save the own text as a
 * new document.
 */
defineProps<{ open: boolean, pending?: boolean }>()
const emit = defineEmits<{ reload: [], saveAsNew: [] }>()

const { t } = useI18n()
</script>

<template>
  <AppDialog
    :open="open"
    :label="t('documents.conflict.title')"
    :description="t('documents.conflict.text')"
    @update:open="value => !value && emit('reload')"
  >
    <template #footer>
      <wa-button
        appearance="outlined"
        variant="neutral"
        :disabled="pending"
        @click="emit('saveAsNew')"
      >
        {{ t('documents.conflict.saveAsNew') }}
      </wa-button>
      <wa-button
        variant="brand"
        :disabled="pending"
        @click="emit('reload')"
      >
        {{ t('documents.conflict.reload') }}
      </wa-button>
    </template>
  </AppDialog>
</template>
