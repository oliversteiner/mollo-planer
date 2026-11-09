<script setup lang="ts">
/**
 * Labels of a task: neutral marks with a coloured dot (the hue of the
 * label in the theme's palette), in the order of their set. Labels that
 * are no longer in the project's set (after a change of the set) have a
 * dashed border.
 */
export interface TaskLabelItem {
  id: string
  name: string
  color: string
  position: number
}

const props = defineProps<{
  labels?: readonly TaskLabelItem[] | null
  /** Ids of the labels of the project's current set; without it all count as in the set. */
  setLabelIds?: readonly string[]
}>()

const { t } = useI18n()

const sorted = computed(() => [...(props.labels ?? [])].sort((a, b) => a.position - b.position || a.name.localeCompare(b.name)))

function outside(label: TaskLabelItem): boolean {
  return props.setLabelIds !== undefined && !props.setLabelIds.includes(label.id)
}
</script>

<template>
  <ul
    v-if="sorted.length"
    class="task-labels wa-list-plain"
    :aria-label="t('labels.ofTask')"
  >
    <li
      v-for="label in sorted"
      :key="label.id"
      class="task-label"
      :data-outside="outside(label) ? '' : undefined"
      :title="outside(label) ? t('labels.outsideSet') : undefined"
    >
      <span
        class="task-label-dot"
        :style="{ backgroundColor: `var(--wa-color-${label.color.toLowerCase()}-60)` }"
        aria-hidden="true"
      />
      {{ label.name }}
      <span
        v-if="outside(label)"
        class="wa-visually-hidden"
      >({{ t('labels.outsideSet') }})</span>
    </li>
  </ul>
</template>

<style scoped>
.task-labels {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wa-space-3xs);
  margin: 0;
  padding: 0;
}

.task-label {
  display: inline-flex;
  align-items: center;
  gap: var(--wa-space-2xs);
  padding: 0.125em 0.5em;
  border: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  border-radius: var(--wa-border-radius-pill);
  background-color: var(--wa-color-neutral-fill-quiet);
  color: var(--wa-color-text-normal);
  font-size: var(--wa-font-size-xs);
  line-height: 1.4;
  white-space: nowrap;
}

.task-label[data-outside] {
  border-style: dashed;
  color: var(--wa-color-text-quiet);
}

.task-label-dot {
  flex: none;
  inline-size: 0.5em;
  block-size: 0.5em;
  border-radius: 50%;
}
</style>
