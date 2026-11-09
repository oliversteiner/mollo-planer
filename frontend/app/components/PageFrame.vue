<script setup lang="ts">
/**
 * Frame of a page: title bar (title, optional actions at the right) and the
 * content below. Replaces the dashboard panel and navbar of Nuxt UI; the
 * sidebar and the scrolling belong to <wa-page> (layouts/default.vue).
 */
defineProps<{
  title: string
  /** The page fills the screen height and its content does not scroll (for example a split view). */
  fill?: boolean
}>()
</script>

<template>
  <div
    class="page-frame"
    :data-fill="fill ? '' : undefined"
  >
    <header class="page-header">
      <h1 class="page-title">
        {{ title }}
      </h1>
      <div
        v-if="$slots.actions"
        class="page-actions"
      >
        <slot name="actions" />
      </div>
    </header>
    <div class="page-body">
      <slot />
    </div>
  </div>
</template>

<style scoped>
.page-frame[data-fill] {
  display: flex;
  flex-direction: column;
  block-size: 100dvh;
}

.page-frame[data-fill] .page-body {
  flex: 1;
  min-block-size: 0;
  padding: 0;
  overflow: hidden;
}

.page-header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--wa-space-s);
  padding: var(--wa-space-m) var(--wa-space-l);
  border-block-end: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
}

.page-title {
  margin: 0;
  font-size: var(--wa-font-size-l);
  font-weight: var(--wa-font-weight-semibold);
}

.page-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-s);
}

.page-body {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-l);
  padding: var(--wa-space-l);
}
</style>
