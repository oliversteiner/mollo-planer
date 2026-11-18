<script setup lang="ts">
import { useQuery } from '@vue/apollo-composable'
import { projectTimelineQuery } from '~/graphql/project'
import { isEmptyDocument } from '~/utils/rich-text'

/**
 * A todo alone (docs/zen-modus.md §4.5, view "focus"): title, status, description and its tasks. It reads the same
 * query as the timeline (from the cache), so there is no data path of its own. A task opens its details (the page
 * keeps `?task=`).
 */
const props = defineProps<{
  projectId: string
  todoId: string
}>()

const emit = defineEmits<{ openTask: [taskId: string] }>()

const { t } = useI18n()
const { leaveFocus } = useViewMode()

const { result } = useQuery(projectTimelineQuery, () => ({ id: props.projectId }), { fetchPolicy: 'cache-first' })
const found = computed(() => {
  for (const milestone of result.value?.project?.milestones ?? []) {
    const todo = milestone.todos.find(item => item.id === props.todoId)

    if (todo) {
      return { milestone, todo }
    }
  }

  return null
})
</script>

<template>
  <article
    v-if="found"
    class="todo-focus wa-stack wa-gap-l"
  >
    <header class="wa-stack wa-gap-2xs">
      <p class="todo-focus-where">
        {{ found.milestone.title }}
      </p>
      <h2 class="wa-heading-l">
        {{ found.todo.title }}
      </h2>
      <div class="wa-cluster wa-gap-s">
        <StatusBadge :status="found.todo.status" />
        <DueDate
          :date="found.todo.dueDate"
          :done="found.todo.status === 'DONE'"
        />
      </div>
    </header>

    <RichTextView
      v-if="!isEmptyDocument(found.todo.description)"
      :content="found.todo.description"
      class="wa-body-s"
    />

    <ul class="todo-focus-tasks wa-list-plain">
      <ProjectTimelineTask
        v-for="task in found.todo.tasks"
        :key="task.id"
        :task="task"
        :menu="[]"
        keep-done
        @open="emit('openTask', task.id)"
        @edit="emit('openTask', task.id)"
      />
    </ul>
    <p
      v-if="!found.todo.tasks.length"
      class="todo-focus-where"
    >
      {{ t('project.timeline.noTasks') }}
    </p>

    <div>
      <wa-button
        appearance="plain"
        variant="neutral"
        @click="leaveFocus()"
      >
        <wa-icon
          slot="start"
          name="arrow-left"
        ></wa-icon>
        {{ t('zen.leaveFocus') }}
      </wa-button>
    </div>
  </article>
</template>

<style scoped>
.todo-focus {
  max-inline-size: 44rem;
  margin-inline: auto;
}

.todo-focus-where {
  margin: 0;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}

.todo-focus-tasks {
  margin: 0;
  padding: 0;
  border: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  border-radius: var(--wa-border-radius-l);
}
</style>
