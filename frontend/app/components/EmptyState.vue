<script setup lang="ts">
/**
 * Empty place: icon, title, text and optionally actions (default slot).
 * `bare` leaves out the dashed frame, for use inside another box.
 */
withDefaults(defineProps<{
  /** Font Awesome name, for example "flag-checkered". */
  icon?: string
  title?: string
  description?: string
  size?: 'sm' | 'md'
  bare?: boolean
}>(), {
  icon: undefined,
  title: undefined,
  description: undefined,
  size: 'md'
})
</script>

<template>
  <div
    class="empty-state"
    :data-size="size"
    :data-bare="bare ? '' : undefined"
  >
    <ItemIcon
      v-if="icon"
      :name="icon"
      :fallback="icon"
      class="empty-state-icon"
    />
    <p
      v-if="title"
      class="empty-state-title"
    >
      {{ title }}
    </p>
    <p
      v-if="description"
      class="empty-state-description"
    >
      {{ description }}
    </p>
    <div
      v-if="$slots.default"
      class="empty-state-actions"
    >
      <slot />
    </div>
  </div>
</template>

<style scoped>
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--wa-space-s);
  padding: var(--wa-space-xl);
  border: var(--wa-border-width-s) dashed var(--wa-color-surface-border);
  border-radius: var(--wa-border-radius-l);
  text-align: center;
  color: var(--wa-color-text-quiet);
}

.empty-state[data-bare] {
  border: none;
}

.empty-state[data-size='sm'] {
  padding: var(--wa-space-m);
  font-size: var(--wa-font-size-s);
}

.empty-state-icon {
  font-size: 2.5em;
  color: var(--wa-color-text-quiet);
}

.empty-state-title {
  margin: 0;
  font-weight: var(--wa-font-weight-semibold);
  color: var(--wa-color-text-normal);
}

.empty-state-description {
  margin: 0;
  max-inline-size: 40ch;
}

.empty-state-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: var(--wa-space-s);
}
</style>
