<script setup lang="ts">
import { useQuery } from '@vue/apollo-composable'
import { zenProjectQuery } from '~/graphql/zen'

/**
 * The frame of the calm and focus views (docs/zen-modus.md §4.3): a button in the corner (it opens the command palette: the way back
 * to the normal view, and to everything else), the name of the project, and in focus a way back. It is always in the page and shown by zen.css only in
 * those views (`data-zen="only"`), so the layout does not switch with v-if.
 * Corner: top left, small, above the content; the page gives it room (padding-top of main in zen.css).
 */
const { t } = useI18n()
const route = useRoute()
const { show } = useCommandPalette()

const projectId = computed(() => (typeof route.params.id === 'string' ? route.params.id : null))
const { result } = useQuery(zenProjectQuery, () => ({ id: projectId.value ?? '' }), () => ({ enabled: projectId.value !== null && route.path.startsWith('/projects/') }))
const projectName = computed(() => (route.path.startsWith('/projects/') ? result.value?.project?.name ?? null : null))
</script>

<template>
  <div
    class="zen-shell"
    data-zen="only"
  >
    <wa-button
      appearance="plain"
      variant="neutral"
      size="small"
      :aria-label="t('zen.menu')"
      @click="show()"
    >
      <wa-icon name="leaf"></wa-icon>
    </wa-button>
    <span
      v-if="projectName"
      class="zen-project"
    >{{ projectName }}</span>
    <!-- Pages put their saving state here (Teleport to #zen-status). -->
    <span
      id="zen-status"
      class="zen-status"
      aria-live="polite"
    />
  </div>
</template>

<style scoped>
.zen-shell {
  position: sticky;
  inset-block-start: 0;
  z-index: 5;
  display: flex;
  align-items: center;
  gap: var(--wa-space-s);
  padding: var(--wa-space-xs) var(--wa-space-m);
  background-color: var(--wa-color-surface-lowered);
}

.zen-project {
  min-inline-size: 0;
  overflow: hidden;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.zen-status {
  margin-inline-start: auto;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}
</style>
