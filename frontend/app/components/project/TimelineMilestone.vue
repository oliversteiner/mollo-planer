<script setup lang="ts">
import { isEmptyDocument } from '~/utils/rich-text'

/**
 * The body of a milestone on the project page (docs/ux-projektseite.md §4.2): the progress is there closed and open;
 * open, the description and the content (todos, «ToDo anlegen») follow. The head is `ProjectTimelineMilestoneHead`: the
 * stepper keeps the title and the content in two slots, so the milestone comes in these two pieces.
 */
const props = defineProps<{
  id: string
  description?: unknown
  progress: { done: number, total: number, percent: number }
  /** Ideas do not count in the progress; with only ideas the line says how many there are (B12). */
  ideas: number
  open: boolean
}>()

const { t } = useI18n()

const onlyIdeas = computed(() => props.progress.total === 0 && props.ideas > 0)
const left = computed(() => props.progress.total - props.progress.done)
const text = computed(() => {
  const base = t('projects.progress', { done: props.progress.done, total: props.progress.total })

  return left.value > 0 ? `${base} · ${t('project.timeline.openTasks', { count: left.value })}` : base
})
</script>

<template>
  <div class="milestone-body wa-stack wa-gap-s">
    <div class="milestone-progress">
      <span
        v-if="onlyIdeas"
        class="milestone-progress-text"
      >{{ t('project.timeline.ideasOnly', { count: ideas }, ideas) }}</span>
      <template v-else>
        <wa-progress-bar
          :value="progress.percent"
          :label="text"
        ></wa-progress-bar>
        <span class="milestone-progress-text">{{ text }}</span>
      </template>
    </div>

    <div
      v-if="open"
      :id="`milestone-${id}-content`"
      class="wa-stack wa-gap-s"
    >
      <RichTextView
        v-if="!isEmptyDocument(description as never)"
        :content="description as never"
        class="wa-body-s"
      />
      <slot />
    </div>
  </div>
</template>

<style scoped>
.milestone-body {
  padding-block-end: var(--wa-space-l);
}

.milestone-progress {
  display: flex;
  align-items: center;
  gap: var(--wa-space-s);
  max-inline-size: 28rem;
}

.milestone-progress wa-progress-bar {
  flex: 1;
  --track-height: 0.5rem;
  /* The empty track stays visible against the page (B10): the default, neutral-fill-normal, is the colour of the page. */
  --track-color: var(--wa-color-neutral-border-normal);
}

.milestone-progress-text {
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-xs);
  white-space: nowrap;
}

@media (max-width: 40em) {
  .milestone-progress {
    flex-direction: column;
    align-items: stretch;
    gap: var(--wa-space-3xs);
  }

  .milestone-progress-text {
    white-space: normal;
  }
}
</style>
