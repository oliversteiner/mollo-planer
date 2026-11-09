<script setup lang="ts">
import type { TimelineEntry } from '~/components/StepTimeline.vue'
import { formatDate } from '~/utils/dates'
import { planCounts, type ProjectPlan } from '~/utils/plan'

/**
 * Preview of a draft: 1. project name, 2. description, 3. timeline of
 * milestones with collapsible todos and their tasks. Nothing is saved
 * until "Jetzt eintragen".
 */
const props = defineProps<{
  plan: ProjectPlan
  /** Accounts by lower-case e-mail, for names and avatars */
  people: Record<string, { name: string, avatarUrl: string | null }>
  importing?: boolean
  error?: string | null
}>()

const emit = defineEmits<{
  import: [notifyAssignees: boolean]
  discard: []
}>()

const { t, localeProperties } = useI18n()

const notifyAssignees = ref(false)
const counts = computed(() => planCounts(props.plan))
const language = computed(() => localeProperties.value.language ?? 'de-CH')

type MilestoneItem = TimelineEntry & { milestone: ProjectPlan['milestones'][number], index: number }

const timeline = computed<MilestoneItem[]>(() => props.plan.milestones.map((milestone, index) => ({
  value: String(index),
  index,
  title: milestone.title,
  date: milestone.dueDate ? formatDate(milestone.dueDate, language.value) : t('assistant.preview.noDate'),
  class: 'plan-in',
  milestone
})))

/** Open todos, as "milestone-todo" keys. New drafts start collapsed. */
const openTodos = ref(new Set<string>())
const allTodoKeys = computed(() => props.plan.milestones.flatMap((milestone, m) => milestone.todos.map((_, todo) => `${m}-${todo}`)))
const allOpen = computed(() => allTodoKeys.value.length > 0 && allTodoKeys.value.every(key => openTodos.value.has(key)))

watch(() => props.plan, () => {
  openTodos.value = new Set()
})

function setOpen(key: string, open: boolean) {
  const next = new Set(openTodos.value)

  if (open) {
    next.add(key)
  } else {
    next.delete(key)
  }

  openTodos.value = next
}

function toggleAll() {
  openTodos.value = allOpen.value ? new Set() : new Set(allTodoKeys.value)
}

function person(email: string | null) {
  if (!email) {
    return null
  }

  return props.people[email.toLowerCase()] ?? { name: email, avatarUrl: null }
}
</script>

<template>
  <div class="wa-stack wa-gap-2xl">
    <!-- 1. Project name -->
    <section
      class="plan-in wa-stack wa-gap-s"
      style="--plan-step: 0"
    >
      <p class="preview-eyebrow">
        {{ t('assistant.preview.project') }}
      </p>
      <h2 class="preview-name">
        {{ plan.name }}
      </h2>
      <div class="wa-cluster wa-gap-xs">
        <wa-badge
          variant="neutral"
          appearance="filled"
        >
          <wa-icon
            slot="start"
            name="flag-checkered"
          ></wa-icon>
          {{ t('assistant.preview.milestoneCount', counts.milestones) }}
        </wa-badge>
        <wa-badge
          variant="neutral"
          appearance="filled"
        >
          <wa-icon
            slot="start"
            name="list-check"
          ></wa-icon>
          {{ t('assistant.preview.todoCount', counts.todos) }}
        </wa-badge>
        <wa-badge
          variant="neutral"
          appearance="filled"
        >
          <wa-icon
            slot="start"
            name="square-check"
          ></wa-icon>
          {{ t('assistant.preview.taskCount', { count: counts.tasks, assigned: counts.assigned }) }}
        </wa-badge>
        <wa-badge
          variant="neutral"
          appearance="filled"
        >
          <wa-icon
            slot="start"
            name="envelope"
          ></wa-icon>
          {{ t('assistant.preview.mailLanguage', { language: t(`locale.${plan.locale}`) }) }}
        </wa-badge>
      </div>
    </section>

    <!-- 2. Description and team -->
    <section
      class="plan-in wa-stack wa-gap-s"
      style="--plan-step: 1"
    >
      <h3 class="wa-heading-xs">
        {{ t('assistant.preview.description') }}
      </h3>
      <AssistantPlainText
        v-if="plan.description"
        :text="plan.description"
      />
      <p
        v-else
        class="preview-none"
      >
        {{ t('assistant.preview.noDescription') }}
      </p>

      <ul
        v-if="plan.members.length"
        class="preview-members wa-list-plain"
        :aria-label="t('assistant.preview.members')"
      >
        <li
          v-for="member in plan.members"
          :key="member.email"
        >
          <UserLine
            :name="person(member.email)?.name ?? member.email"
            :src="person(member.email)?.avatarUrl"
            :description="t(`role.${member.role}`)"
            size="sm"
          />
        </li>
      </ul>
    </section>

    <!-- 3. Timeline: milestones > todos (collapsible) > tasks -->
    <section class="wa-stack wa-gap-m">
      <div
        class="plan-in wa-split wa-gap-s"
        style="--plan-step: 2"
      >
        <h3 class="wa-heading-xs">
          {{ t('assistant.preview.timeline') }}
        </h3>
        <wa-button
          v-if="allTodoKeys.length"
          appearance="plain"
          variant="neutral"
          size="s"
          @click="toggleAll"
        >
          <wa-icon
            slot="start"
            :name="allOpen ? 'chevrons-up' : 'chevrons-down'"
          ></wa-icon>
          {{ allOpen ? t('assistant.preview.collapseAll') : t('assistant.preview.expandAll') }}
        </wa-button>
      </div>

      <StepTimeline
        :items="timeline"
        :label="t('assistant.preview.timeline')"
        class="plan-timeline"
      >
        <template #indicator>
          <ItemIcon
            name="flag-checkered"
            fallback="flag-checkered"
          />
        </template>

        <template #description="{ item }">
          <div class="wa-stack wa-gap-s preview-milestone">
            <AssistantPlainText :text="item.milestone.description" />

            <ul class="preview-todos wa-list-plain">
              <li
                v-for="(todo, todoIndex) in item.milestone.todos"
                :key="todoIndex"
                class="preview-todo"
              >
                <button
                  type="button"
                  class="preview-todo-toggle plain-button"
                  :aria-label="t('assistant.preview.toggleTodo', { title: todo.title })"
                  :aria-expanded="openTodos.has(`${item.index}-${todoIndex}`)"
                  @click="setOpen(`${item.index}-${todoIndex}`, !openTodos.has(`${item.index}-${todoIndex}`))"
                >
                  <wa-icon
                    :name="openTodos.has(`${item.index}-${todoIndex}`) ? 'chevron-down' : 'chevron-right'"
                    class="preview-quiet"
                  ></wa-icon>
                  <wa-icon
                    name="list-check"
                    class="preview-accent"
                  ></wa-icon>
                  <span class="preview-todo-title">{{ todo.title }}</span>
                  <DueDate :date="todo.dueDate" />
                  <wa-badge
                    variant="neutral"
                    appearance="filled"
                    :aria-label="t('assistant.preview.taskCountShort', todo.tasks.length)"
                  >
                    {{ todo.tasks.length }}
                  </wa-badge>
                </button>

                <div
                  v-if="openTodos.has(`${item.index}-${todoIndex}`)"
                  class="preview-todo-content wa-stack wa-gap-s"
                >
                  <AssistantPlainText :text="todo.description" />

                  <ul
                    v-if="todo.tasks.length"
                    class="preview-tasks wa-list-plain"
                  >
                    <li
                      v-for="(task, taskIndex) in todo.tasks"
                      :key="taskIndex"
                      class="preview-task"
                    >
                      <wa-icon
                        name="square-check"
                        class="preview-quiet preview-task-icon"
                      ></wa-icon>
                      <div class="preview-task-text wa-stack wa-gap-3xs">
                        <p class="preview-task-title">
                          {{ task.title }}
                        </p>
                        <AssistantPlainText :text="task.description" />
                      </div>
                      <div class="preview-task-meta">
                        <DueDate :date="task.dueDate" />
                        <UserLine
                          v-if="person(task.assigneeEmail)"
                          :name="person(task.assigneeEmail)!.name"
                          :src="person(task.assigneeEmail)!.avatarUrl"
                          size="xs"
                        />
                        <span
                          v-else
                          class="preview-none"
                        >{{ t('assistant.preview.unassigned') }}</span>
                      </div>
                    </li>
                  </ul>
                </div>
              </li>
            </ul>
          </div>
        </template>
      </StepTimeline>
    </section>

    <!-- Save -->
    <section
      style="--plan-step: 6"
      class="plan-in preview-save wa-stack wa-gap-s"
    >
      <AppCallout
        v-if="error"
        variant="danger"
        :title="t('assistant.preview.importFailed')"
        :description="error"
      />
      <div class="wa-split wa-gap-s">
        <FormCheckbox
          v-model="notifyAssignees"
          :label="t('assistant.preview.notify')"
          :hint="t('assistant.preview.notifyHint')"
        />
        <div class="wa-cluster wa-gap-s">
          <wa-button
            appearance="plain"
            variant="neutral"
            :disabled="importing"
            @click="emit('discard')"
          >
            <wa-icon
              slot="start"
              name="trash-can"
            ></wa-icon>
            {{ t('assistant.preview.discard') }}
          </wa-button>
          <wa-button
            variant="brand"
            :loading="importing"
            @click="emit('import', notifyAssignees)"
          >
            <wa-icon
              slot="start"
              name="floppy-disk-circle-arrow-right"
            ></wa-icon>
            {{ t('assistant.preview.import') }}
          </wa-button>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.preview-eyebrow {
  margin: 0;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-xs);
  font-weight: var(--wa-font-weight-semibold);
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.preview-name {
  margin: 0;
  font-size: var(--wa-font-size-2xl);
  font-weight: var(--wa-font-weight-semibold);
}

.preview-none {
  margin: 0;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}

.preview-members {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wa-space-s) var(--wa-space-xl);
  margin: 0;
  padding: var(--wa-space-xs) 0 0;
}

.preview-milestone {
  padding-block-end: var(--wa-space-l);
}

.preview-todos {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-xs);
  margin: 0;
  padding: 0;
}

.preview-todo {
  border: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  border-radius: var(--wa-border-radius-l);
}

.preview-todo-toggle {
  display: flex;
  align-items: center;
  gap: var(--wa-space-xs);
  inline-size: 100%;
  padding: var(--wa-space-xs) var(--wa-space-s);
  border: none;
  border-radius: var(--wa-border-radius-l);
  background: none;
  color: inherit;
  font: inherit;
  text-align: start;
  cursor: pointer;
}

.preview-todo-toggle:hover {
  background-color: var(--wa-color-neutral-fill-quiet);
}

.preview-todo-toggle:focus-visible {
  outline: var(--wa-focus-ring);
  outline-offset: var(--wa-focus-ring-offset);
}

.preview-todo-title {
  flex: 1;
  min-inline-size: 0;
  overflow: hidden;
  font-weight: var(--wa-font-weight-semibold);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.preview-quiet {
  color: var(--wa-color-text-quiet);
}

.preview-accent {
  color: var(--wa-color-brand-on-quiet);
}

.preview-todo-content {
  padding: var(--wa-space-s);
  border-block-start: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
}

.preview-tasks {
  margin: 0;
  padding: 0;
  border-radius: var(--wa-border-radius-m);
  background-color: var(--wa-color-surface-raised);
}

.preview-task {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: var(--wa-space-xs);
  padding: var(--wa-space-xs) var(--wa-space-s);
}

.preview-task + .preview-task {
  border-block-start: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
}

.preview-task-icon {
  margin-block-start: var(--wa-space-3xs);
}

.preview-task-text {
  flex: 1;
  min-inline-size: 12rem;
}

.preview-task-title {
  margin: 0;
  font-size: var(--wa-font-size-s);
  font-weight: var(--wa-font-weight-semibold);
}

.preview-task-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-s);
}

.preview-save {
  position: sticky;
  inset-block-end: 0;
  margin-inline: calc(var(--wa-space-l) * -1);
  padding: var(--wa-space-m) var(--wa-space-l);
  border-block-start: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  background-color: var(--wa-color-surface-default);
}
</style>
