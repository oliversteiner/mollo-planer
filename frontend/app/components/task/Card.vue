<script setup lang="ts">
import type { DropdownMenuItem } from '@nuxt/ui'

/** A task on the Kanban board. The menu offers moving without dragging. */
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
  moveItems: DropdownMenuItem[]
}>()

const emit = defineEmits<{ open: [] }>()

const { t } = useI18n()
</script>

<template>
  <article
    class="group flex cursor-grab flex-col gap-2 rounded-md border border-default bg-default p-3 shadow-xs hover:border-primary active:cursor-grabbing"
    :data-id="task.id"
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
        @click="emit('open')"
      >
        {{ task.title }}
      </button>
      <UDropdownMenu
        v-if="moveItems.length"
        :items="moveItems"
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
