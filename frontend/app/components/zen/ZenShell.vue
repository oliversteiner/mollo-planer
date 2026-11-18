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
      class="zen-mark"
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

/*
 * The mark (the leaf, opens the search): twice as large as before (the icon measured 14 px, now 2em of that) and at
 * least 44 x 44 px. In a window wide enough it sits at the left edge of the window, not of the reading column, and
 * stays in view while scrolling (fixed); the column keeps the project name and the saving state. Where there is not
 * room beside the column it stays in the column as before, so it never covers a field, the saving state or a message.
 * The room needed: the column (52rem) plus the mark and its margin on both sides.
 */
.zen-mark wa-icon {
  font-size: 2em;
}

.zen-mark::part(base) {
  min-inline-size: 2.75rem;
  min-block-size: 2.75rem;
}

@media (min-width: 64rem) {
  .zen-mark {
    position: fixed;
    inset-block-start: var(--wa-space-xs);
    inset-inline-start: var(--wa-space-m);
    z-index: 6;
  }
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
