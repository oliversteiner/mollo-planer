<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import type { DropdownMenuItem } from '@nuxt/ui'
import type { ItemFormTarget } from '~/components/item/FormModal.vue'
import { deleteMilestoneMutation, deleteTodoMutation, projectContentQueries, projectOverviewQuery } from '~/graphql/project'
import { isEmptyDocument } from '~/utils/rich-text'

const { t } = useI18n()
const { projectId, project, can } = useProject()

const { result, loading } = useQuery(projectOverviewQuery, () => ({ id: projectId.value }), { fetchPolicy: 'cache-and-network' })

/** Milestones by due date (without date last), then in creation order. */
const milestones = computed(() => [...(result.value?.project?.milestones ?? [])]
  .sort((a, b) => (a.dueDate ?? '9999').localeCompare(b.dueDate ?? '9999') || Number(a.id) - Number(b.id)))

type Item = NonNullable<ItemFormTarget['item']>

const editing = ref<ItemFormTarget | null>(null)
const deleting = ref<{ kind: 'milestone' | 'todo', item: Item } | null>(null)

const options = { refetchQueries: projectContentQueries, awaitRefetchQueries: true }
const { mutate: deleteMilestone } = useMutation(deleteMilestoneMutation, options)
const { mutate: deleteTodo } = useMutation(deleteTodoMutation, options)

async function confirmDelete(): Promise<void> {
  const target = deleting.value

  if (target) {
    await (target.kind === 'milestone' ? deleteMilestone({ id: target.item.id }) : deleteTodo({ id: target.item.id }))
  }
}

function startEditing(target: ItemFormTarget): void {
  editing.value = target
}

function startDeleting(target: { kind: 'milestone' | 'todo', item: Item }): void {
  deleting.value = target
}

function actions(kind: 'milestone' | 'todo', item: Item): DropdownMenuItem[] {
  const prefix = kind === 'milestone' ? 'MILESTONE' : 'TODO'
  const items: DropdownMenuItem[] = []

  if (can(`${prefix}_UPDATE`)) {
    items.push({ label: t('common.edit'), icon: 'i-fadl-pen', onSelect: () => startEditing({ kind, item }) })
  }
  if (can(`${prefix}_DELETE`)) {
    items.push({ label: t('common.delete'), icon: 'i-fadl-trash', color: 'error', onSelect: () => startDeleting({ kind, item }) })
  }

  return items
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <p
        v-if="project?.description"
        class="max-w-prose text-muted"
      >
        {{ project.description }}
      </p>
      <UButton
        v-if="can('MILESTONE_CREATE')"
        icon="i-fadl-plus"
        :label="t('item.milestone.create')"
        class="ms-auto"
        @click="editing = { kind: 'milestone', parentId: projectId }"
      />
    </div>

    <USkeleton
      v-if="loading && !result"
      class="h-40"
    />
    <UEmpty
      v-else-if="!milestones.length"
      icon="i-fadl-flag-checkered"
      :title="t('project.noMilestones')"
      :description="can('MILESTONE_CREATE') ? t('project.noMilestonesHint') : undefined"
    />

    <UCard
      v-for="milestone in milestones"
      :key="milestone.id"
    >
      <template #header>
        <div class="flex flex-wrap items-center gap-3">
          <ItemIcon
            :name="milestone.icon"
            fallback="flag-checkered"
            class="text-xl text-primary"
          />
          <h2 class="min-w-0 flex-1 truncate font-semibold text-highlighted">
            {{ milestone.title }}
          </h2>
          <StatusBadge :status="milestone.status" />
          <DueDate
            :date="milestone.dueDate"
            :done="milestone.status === 'DONE'"
          />
          <UDropdownMenu
            v-if="actions('milestone', milestone).length"
            :items="actions('milestone', milestone)"
          >
            <UButton
              icon="i-fadl-ellipsis"
              color="neutral"
              variant="ghost"
              :aria-label="t('common.actions')"
            />
          </UDropdownMenu>
        </div>
        <div class="mt-3 flex items-center gap-3">
          <UProgress
            :model-value="milestone.progress.percent"
            size="sm"
            class="flex-1"
          />
          <span class="text-sm text-muted">{{ t('projects.progress', { done: milestone.progress.done, total: milestone.progress.total }) }}</span>
        </div>
      </template>

      <RichTextView
        :content="milestone.description"
        class="mb-4 text-sm"
      />

      <ul
        v-if="milestone.todos.length"
        class="divide-y divide-default"
      >
        <li
          v-for="todo in milestone.todos"
          :key="todo.id"
          class="flex flex-wrap items-center gap-3 py-2"
        >
          <ItemIcon
            :name="todo.icon"
            fallback="list-check"
            class="text-lg text-primary"
          />
          <span class="min-w-0 flex-1 truncate">{{ todo.title }}</span>
          <UTooltip
            v-if="!isEmptyDocument(todo.description)"
            :text="t('project.hasDescription')"
          >
            <UIcon
              name="i-fadl-align-left"
              class="size-4 text-muted"
              :aria-label="t('project.hasDescription')"
            />
          </UTooltip>
          <span class="text-sm text-muted">{{ t('project.taskCount', { done: todo.progress.done, total: todo.progress.total }) }}</span>
          <StatusBadge :status="todo.status" />
          <DueDate
            :date="todo.dueDate"
            :done="todo.status === 'DONE'"
          />
          <UDropdownMenu
            v-if="actions('todo', todo).length"
            :items="actions('todo', todo)"
          >
            <UButton
              icon="i-fadl-ellipsis"
              color="neutral"
              variant="ghost"
              size="sm"
              :aria-label="t('common.actions')"
            />
          </UDropdownMenu>
        </li>
      </ul>
      <p
        v-else
        class="text-sm text-muted"
      >
        {{ t('project.noTodos') }}
      </p>

      <template
        v-if="can('TODO_CREATE')"
        #footer
      >
        <UButton
          icon="i-fadl-plus"
          :label="t('item.todo.create')"
          color="neutral"
          variant="ghost"
          size="sm"
          @click="editing = { kind: 'todo', parentId: milestone.id }"
        />
      </template>
    </UCard>

    <ItemFormModal
      :target="editing"
      @close="editing = null"
    />
    <ConfirmModal
      :open="deleting !== null"
      :title="t(`item.${deleting?.kind ?? 'todo'}.deleteTitle`)"
      :description="t(`item.${deleting?.kind ?? 'todo'}.deleteDescription`, { title: deleting?.item.title ?? '' })"
      :confirm-label="t('common.delete')"
      :action="confirmDelete"
      @close="deleting = null"
    />
  </div>
</template>
