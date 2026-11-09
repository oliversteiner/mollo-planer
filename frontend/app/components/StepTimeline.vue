<script setup lang="ts" generic="T extends TimelineEntry">
/**
 * Vertical timeline: a <wa-stepper orientation="vertical"> with one
 * <wa-step> per entry. The marker holds an icon (slot `indicator`), the label
 * holds the title and the date, and the description slot holds the content
 * of the entry (for example todos and tasks).
 *
 * The stepper is a display, not a control: `clickable` stays off, because the
 * content of an entry has buttons and links of its own.
 */
export interface TimelineEntry {
  /** Unique name of the step; matches `active`. */
  value: string
  title: string
  /** Shown next to the title. */
  date?: string
  /** Reached: the marker shows a check mark (unless the indicator slot sets an icon). */
  completed?: boolean
  /** Extra class on the step, for example for the entrance animation. */
  class?: string
}

defineProps<{
  items: T[]
  /** Value of the current entry. */
  active?: string
  /** Accessible name of the list, when there are several on a page. */
  label?: string
}>()

defineSlots<{
  /** Content of the marker, for example an icon. */
  indicator?: (props: { item: T }) => unknown
  /** Replaces the title. */
  title?: (props: { item: T }) => unknown
  /** Content below the title. */
  description?: (props: { item: T }) => unknown
}>()
</script>

<template>
  <wa-stepper
    orientation="vertical"
    :active="active"
    :label="label"
    class="timeline"
  >
    <wa-step
      v-for="item in items"
      :key="item.value"
      :name="item.value"
      :completed="item.completed"
      :class="item.class"
    >
      <span class="timeline-heading">
        <span class="timeline-title"><slot
          name="title"
          :item="item"
        >{{ item.title }}</slot></span>
        <time
          v-if="item.date"
          class="timeline-date"
        >{{ item.date }}</time>
      </span>
      <span
        v-if="$slots.indicator"
        slot="icon"
        class="timeline-indicator"
      ><slot
        name="indicator"
        :item="item"
      /></span>
      <div
        v-if="$slots.description"
        slot="description"
        class="timeline-description"
      >
        <slot
          name="description"
          :item="item"
        />
      </div>
    </wa-step>
  </wa-stepper>
</template>

<style scoped>
.timeline {
  /* The marker is a little larger than the default, the content spreads wide. */
  --marker-size: 2em;
  --gap: var(--wa-space-l);
  inline-size: 100%;
  min-inline-size: 0;
}

.timeline-heading {
  display: inline-flex;
  flex-wrap: wrap;
  align-items: center;
  column-gap: var(--wa-space-s);
  row-gap: var(--wa-space-3xs);
}

.timeline-title {
  display: inline-flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-xs);
  font-weight: var(--wa-font-weight-semibold);
}

.timeline-date {
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
  font-weight: var(--wa-font-weight-normal);
}

.timeline-indicator {
  display: contents;
}

/* The content of a step uses the whole width of the column (todos, tasks). */
wa-step::part(content) {
  flex: 1;
  min-inline-size: 0;
}

wa-step::part(description) {
  inline-size: 100%;
}

.timeline-description {
  inline-size: 100%;
  min-inline-size: 0;
  font-weight: var(--wa-font-weight-normal);
}
</style>
