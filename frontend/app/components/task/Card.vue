<script setup lang="ts">
import type { ActionMenuItem } from '~/components/ActionMenu.vue'
import type { TaskLabelItem } from '~/components/TaskLabels.vue'

/**
 * A task on the Kanban board. Click on the title: details; double-click
 * on the card: edit. The menu offers the same without a mouse.
 */
defineProps<{
  task: {
    id: string
    title: string
    icon?: string | null
    status: 'OPEN' | 'IN_PROGRESS' | 'REVIEW' | 'DONE'
    dueDate?: string | null
    commentCount: number
    assignee?: { id: string, name: string, avatarUrl?: string | null } | null
    todo: { title: string }
    labels?: readonly TaskLabelItem[]
  }
  menuItems: ActionMenuItem[]
  /** May the viewer edit it (double-click)? */
  editable?: boolean
}>()

const emit = defineEmits<{ open: [], edit: [] }>()

const { t } = useI18n()

/**
 * A double-click starts with two clicks. The details open only when no
 * second click follows, so a double-click shows the edit dialog alone.
 * Keyboard activation (detail 0) opens the details at once.
 */
const DOUBLE_CLICK_MS = 250
let clickTimer: ReturnType<typeof setTimeout> | undefined

function onTitleClick(event: MouseEvent): void {
  clearTimeout(clickTimer)

  if (event.detail === 0) {
    emit('open')
  } else if (event.detail === 1) {
    clickTimer = setTimeout(() => emit('open'), DOUBLE_CLICK_MS)
  }
}

function onDoubleClick(): void {
  clearTimeout(clickTimer)
  emit('edit')
}

onBeforeUnmount(() => clearTimeout(clickTimer))
</script>

<template>
  <article
    class="task-card"
    :data-id="task.id"
    :data-status="task.status"
    :title="editable ? t('kanban.doubleClickToEdit') : undefined"
    @dblclick="onDoubleClick"
  >
    <div class="task-card-head">
      <ItemIcon
        :name="task.icon"
        fallback="square-check"
        class="task-card-icon"
      />
      <button
        type="button"
        class="task-card-title plain-button"
        @click="onTitleClick"
      >
        {{ task.title }}
      </button>
      <ActionMenu
        v-if="menuItems.length"
        :items="menuItems"
        :label="t('kanban.moveMenu', { title: task.title })"
        size="s"
      />
    </div>
    <p class="task-card-todo">
      {{ task.todo.title }}
    </p>
    <TaskLabels
      v-if="task.labels?.length"
      :labels="task.labels"
    />
    <div class="task-card-foot">
      <DueDate
        :date="task.dueDate"
        :done="task.status === 'DONE'"
      />
      <span
        v-if="task.commentCount"
        class="task-card-comments"
      >
        <wa-icon name="comments"></wa-icon>
        {{ task.commentCount }}
        <span class="wa-visually-hidden">{{ t('kanban.comments') }}</span>
      </span>
      <UserAvatar
        v-if="task.assignee"
        :name="task.assignee.name"
        :src="task.assignee.avatarUrl"
        size="2xs"
        class="task-card-assignee"
      />
    </div>
  </article>
</template>

<style scoped>
/* The left edge shows the status: grey open, blue in progress, orange review, green done. */
.task-card {
  --status-color: var(--wa-color-gray-60);

  display: flex;
  flex-direction: column;
  gap: var(--wa-space-xs);
  padding: var(--wa-space-s);
  border: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  border-inline-start: var(--wa-border-width-l) solid var(--status-color);
  border-radius: var(--wa-border-radius-m);
  background-color: var(--wa-color-surface-default);
  box-shadow: var(--wa-shadow-s);
  cursor: grab;
}

.task-card[data-status='IN_PROGRESS'] {
  --status-color: var(--wa-color-blue-50);
}

.task-card[data-status='REVIEW'] {
  --status-color: var(--wa-color-orange-60);
}

.task-card[data-status='DONE'] {
  --status-color: var(--wa-color-green-60);
}

.task-card:hover {
  border-color: var(--wa-color-brand-border-loud);
  border-inline-start-color: var(--status-color);
}

.task-card:active {
  cursor: grabbing;
}

.task-card-head {
  display: flex;
  align-items: flex-start;
  gap: var(--wa-space-xs);
}

.task-card-icon {
  margin-block-start: var(--wa-space-3xs);
  font-size: var(--wa-font-size-l);
  color: var(--wa-color-brand-on-quiet);
}

.task-card-title {
  flex: 1;
  min-inline-size: 0;
  padding: 0;
  border: none;
  background: none;
  color: inherit;
  font: inherit;
  font-weight: var(--wa-font-weight-semibold);
  text-align: start;
  cursor: inherit;
}

.task-card-title:hover {
  color: var(--wa-color-brand-on-quiet);
}

.task-card-title:focus-visible {
  outline: var(--wa-focus-ring);
  outline-offset: var(--wa-focus-ring-offset);
}

.task-card-todo {
  overflow: hidden;
  margin: 0;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-xs);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.task-card-foot {
  display: flex;
  align-items: center;
  gap: var(--wa-space-xs);
}

.task-card-comments {
  display: inline-flex;
  align-items: center;
  gap: var(--wa-space-3xs);
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-xs);
}

.task-card-assignee {
  margin-inline-start: auto;
}
</style>
