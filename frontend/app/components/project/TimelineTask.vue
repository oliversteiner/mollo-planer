<script setup lang="ts">
import type { ActionMenuItem } from '~/components/ActionMenu.vue'
import type { ProjectTimelineQuery } from '~/graphql/generated/graphql'
import { isEmptyDocument } from '~/utils/rich-text'

/**
 * One task in a todo of the timeline: icon, title, description, status, due date, comments, person and menu.
 * Click opens the details, double-click the edit dialog (the parent decides if it is allowed). The calm view
 * (docs/zen-modus.md §5) keeps title, status and person; labels, comments and the menu go, the due date comes back
 * on hover.
 */
type Task = NonNullable<ProjectTimelineQuery['project']>['milestones'][number]['todos'][number]['tasks'][number]

defineProps<{
  task: Task
  menu: ActionMenuItem[]
  /** Keep a done task in the calm view (the focus on a todo shows all of its tasks) */
  keepDone?: boolean
}>()

const emit = defineEmits<{
  open: []
  edit: []
}>()

const { t } = useI18n()
</script>

<template>
  <li
    :data-id="task.id"
    :data-zen="task.status === 'DONE' && !keepDone ? 'hide' : undefined"
    class="task"
    data-zen-row
    @click="emit('open')"
    @dblclick="emit('edit')"
  >
    <ItemIcon
      :name="task.icon"
      fallback="square-check"
      class="task-icon"
    />
    <div class="task-text">
      <button
        type="button"
        class="task-title plain-button"
        :data-done="task.status === 'DONE' ? '' : undefined"
        :aria-label="t('project.timeline.openTask', { title: task.title })"
        @click.stop="emit('open')"
        @dblclick.stop="emit('edit')"
      >
        {{ task.title }}
      </button>
      <RichTextView
        v-if="!isEmptyDocument(task.description)"
        :content="task.description"
        class="wa-body-s task-description"
        data-zen="hide"
      />
    </div>
    <div class="task-meta">
      <TaskLabels
        :labels="task.labels"
        data-zen="hide"
      />
      <StatusBadge :status="task.status" />
      <DueDate
        :date="task.dueDate"
        :done="task.status === 'DONE'"
        data-zen="hover"
      />
      <span
        v-if="task.commentCount"
        class="task-comments"
        data-zen="hide"
      >
        <wa-icon name="comments"></wa-icon>
        {{ task.commentCount }}
        <span class="wa-visually-hidden">{{ t('kanban.comments') }}</span>
      </span>
      <UserLine
        v-if="task.assignee"
        :name="task.assignee.name"
        :src="task.assignee.avatarUrl"
        size="xs"
      />
      <span
        v-else
        class="task-unassigned"
      >{{ t('project.timeline.unassigned') }}</span>
      <span
        v-if="menu.length"
        data-zen="hide"
        @click.stop
        @dblclick.stop
      >
        <ActionMenu
          :items="menu"
          :label="t('project.timeline.taskActions', { title: task.title })"
        />
      </span>
    </div>
  </li>
</template>

<style scoped>
.task {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: var(--wa-space-xs);
  padding: var(--wa-space-xs) var(--wa-space-s);
  cursor: pointer;
}

.task + .task {
  border-block-start: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
}

.task:hover {
  background-color: var(--wa-color-neutral-fill-quiet);
}

.task-ghost {
  opacity: 0.4;
}

.task-icon {
  margin-block-start: var(--wa-space-3xs);
  color: var(--wa-color-text-quiet);
}

.task-text {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: var(--wa-space-3xs);
  min-inline-size: 12rem;
}

.task-title {
  align-self: flex-start;
  padding: 0;
  border: none;
  background: none;
  color: inherit;
  font: inherit;
  font-size: var(--wa-font-size-s);
  font-weight: var(--wa-font-weight-semibold);
  text-align: start;
  cursor: pointer;
}

.task-title:focus-visible {
  text-decoration: underline;
  outline: none;
}

.task-title[data-done] {
  color: var(--wa-color-text-quiet);
  text-decoration: line-through;
}

.task-description {
  color: var(--wa-color-text-quiet);
}

.task-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-s);
}

.task-comments {
  display: inline-flex;
  align-items: center;
  gap: var(--wa-space-3xs);
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-xs);
}

.task-unassigned {
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-xs);
}
</style>
