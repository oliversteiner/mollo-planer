<script setup lang="ts">
import type { DropdownMenuItem } from '@nuxt/ui'

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
  }
  menuItems: DropdownMenuItem[]
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
    class="group flex cursor-grab flex-col gap-2 rounded-md border border-default bg-default p-3 shadow-xs hover:border-primary active:cursor-grabbing"
    :data-id="task.id"
    :title="editable ? t('kanban.doubleClickToEdit') : undefined"
    @dblclick="onDoubleClick"
  >
    <div class="flex items-start gap-2">
      <ItemIcon
        :name="task.icon"
        fallback="square-check"
        class="mt-0.5 text-lg text-primary"
      />
      <button
        type="button"
        class="min-w-0 flex-1 text-start font-medium text-highlighted hover:text-primary focus-visible:outline-2 focus-visible:outline-primary"
        @click="onTitleClick"
      >
        {{ task.title }}
      </button>
      <UDropdownMenu
        v-if="menuItems.length"
        :items="menuItems"
      >
        <UButton
          icon="i-fadl-ellipsis"
          color="neutral"
          variant="ghost"
          size="xs"
          :aria-label="t('kanban.moveMenu', { title: task.title })"
        />
      </UDropdownMenu>
    </div>
    <p class="truncate text-xs text-muted">
      {{ task.todo.title }}
    </p>
    <div class="flex items-center gap-2">
      <DueDate
        :date="task.dueDate"
        :done="task.status === 'DONE'"
      />
      <span
        v-if="task.commentCount"
        class="inline-flex items-center gap-1 text-xs text-muted"
      >
        <UIcon
          name="i-fadl-comments"
          class="size-4"
        />
        {{ task.commentCount }}
        <span class="sr-only">{{ t('kanban.comments') }}</span>
      </span>
      <UAvatar
        v-if="task.assignee"
        :src="task.assignee.avatarUrl ?? undefined"
        :alt="task.assignee.name"
        size="2xs"
        class="ms-auto"
      />
    </div>
  </article>
</template>
