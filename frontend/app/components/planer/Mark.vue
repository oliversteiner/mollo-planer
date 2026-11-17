<script setup lang="ts">
/**
 * What an import does to one place of the tree, in text and icon besides the
 * colour: new, changed (with the fields), moved.
 */
defineProps<{ mark: { kind: 'create' | 'update' | 'move', fields: string[] } }>()

const { t } = useI18n()

const ICON = { create: 'plus', update: 'pen', move: 'arrows-up-down' } as const
const VARIANT = { create: 'success', update: 'warning', move: 'brand' } as const
</script>

<template>
  <wa-badge
    :variant="VARIANT[mark.kind]"
    class="planer-mark"
  >
    <wa-icon
      slot="start"
      :name="ICON[mark.kind]"
    ></wa-icon>
    {{ t(`planer.mark.${mark.kind}`) }}<template v-if="mark.kind === 'update' && mark.fields.length">
      : {{ [...new Set(mark.fields)].map(field => t(`planer.field.${field}`, field)).join(', ') }}
    </template>
  </wa-badge>
</template>

<style scoped>
.planer-mark {
  margin-inline-start: var(--wa-space-xs);
}
</style>
