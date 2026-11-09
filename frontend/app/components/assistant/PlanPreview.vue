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
  <div class="space-y-8">
    <!-- 1. Project name -->
    <section
      class="plan-in space-y-3"
      style="--plan-step: 0"
    >
      <p class="text-xs font-medium uppercase tracking-wide text-muted">
        {{ t('assistant.preview.project') }}
      </p>
      <h2 class="text-2xl font-semibold text-highlighted">
        {{ plan.name }}
      </h2>
      <div class="flex flex-wrap gap-2">
        <UBadge
          variant="subtle"
          color="neutral"
          icon="i-fadl-flag-checkered"
          :label="t('assistant.preview.milestoneCount', counts.milestones)"
        />
        <UBadge
          variant="subtle"
          color="neutral"
          icon="i-fadl-list-check"
          :label="t('assistant.preview.todoCount', counts.todos)"
        />
        <UBadge
          variant="subtle"
          color="neutral"
          icon="i-fadl-square-check"
          :label="t('assistant.preview.taskCount', { count: counts.tasks, assigned: counts.assigned })"
        />
        <UBadge
          variant="subtle"
          color="neutral"
          icon="i-fadl-envelope"
          :label="t('assistant.preview.mailLanguage', { language: t(`locale.${plan.locale}`) })"
        />
      </div>
    </section>

    <!-- 2. Description and team -->
    <section
      class="plan-in space-y-3"
      style="--plan-step: 1"
    >
      <h3 class="font-semibold text-highlighted">
        {{ t('assistant.preview.description') }}
      </h3>
      <AssistantPlainText
        v-if="plan.description"
        :text="plan.description"
      />
      <p
        v-else
        class="text-sm text-dimmed"
      >
        {{ t('assistant.preview.noDescription') }}
      </p>

      <ul
        v-if="plan.members.length"
        class="flex flex-wrap gap-x-6 gap-y-3 pt-2"
        :aria-label="t('assistant.preview.members')"
      >
        <li
          v-for="member in plan.members"
          :key="member.email"
        >
          <UUser
            :name="person(member.email)?.name"
            :description="t(`role.${member.role}`)"
            :avatar="{ src: person(member.email)?.avatarUrl ?? undefined, alt: person(member.email)?.name, icon: 'i-fadl-user' }"
            size="sm"
          />
        </li>
      </ul>
    </section>

    <!-- 3. Timeline: milestones > todos (collapsible) > tasks -->
    <section class="space-y-4">
      <div
        class="plan-in flex items-center justify-between gap-2"
        style="--plan-step: 2"
      >
        <h3 class="font-semibold text-highlighted">
          {{ t('assistant.preview.timeline') }}
        </h3>
        <UButton
          v-if="allTodoKeys.length"
          variant="ghost"
          color="neutral"
          size="sm"
          :icon="allOpen ? 'i-fadl-chevrons-up' : 'i-fadl-chevrons-down'"
          :label="allOpen ? t('assistant.preview.collapseAll') : t('assistant.preview.expandAll')"
          @click="toggleAll"
        />
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
          <div class="space-y-3 pb-6">
            <AssistantPlainText :text="item.milestone.description" />

            <ul class="space-y-2">
              <li
                v-for="(todo, todoIndex) in item.milestone.todos"
                :key="todoIndex"
                class="rounded-lg border border-default"
              >
                <UCollapsible
                  :open="openTodos.has(`${item.index}-${todoIndex}`)"
                  @update:open="setOpen(`${item.index}-${todoIndex}`, $event)"
                >
                  <UButton
                    variant="ghost"
                    color="neutral"
                    block
                    class="justify-start gap-2 text-start"
                    :aria-label="t('assistant.preview.toggleTodo', { title: todo.title })"
                  >
                    <UIcon
                      :name="openTodos.has(`${item.index}-${todoIndex}`) ? 'i-fadl-chevron-down' : 'i-fadl-chevron-right'"
                      class="size-4 shrink-0 text-muted"
                    />
                    <UIcon
                      name="i-fadl-list-check"
                      class="size-4 shrink-0 text-primary"
                    />
                    <span class="min-w-0 flex-1 truncate font-medium text-highlighted">{{ todo.title }}</span>
                    <DueDate :date="todo.dueDate" />
                    <UBadge
                      variant="soft"
                      color="neutral"
                      size="sm"
                      :label="String(todo.tasks.length)"
                      :aria-label="t('assistant.preview.taskCountShort', todo.tasks.length)"
                    />
                  </UButton>

                  <template #content>
                    <div class="space-y-3 border-t border-default px-3 py-3">
                      <AssistantPlainText :text="todo.description" />

                      <ul
                        v-if="todo.tasks.length"
                        class="divide-y divide-default rounded-md bg-elevated/50"
                      >
                        <li
                          v-for="(task, taskIndex) in todo.tasks"
                          :key="taskIndex"
                          class="flex flex-col gap-2 px-3 py-2 sm:flex-row sm:items-start"
                        >
                          <UIcon
                            name="i-fadl-square-check"
                            class="mt-0.5 hidden size-4 shrink-0 text-muted sm:block"
                          />
                          <div class="min-w-0 flex-1 space-y-1">
                            <p class="text-sm font-medium text-highlighted">
                              {{ task.title }}
                            </p>
                            <AssistantPlainText :text="task.description" />
                          </div>
                          <div class="flex shrink-0 flex-wrap items-center gap-3">
                            <DueDate :date="task.dueDate" />
                            <UUser
                              v-if="person(task.assigneeEmail)"
                              :name="person(task.assigneeEmail)!.name"
                              :avatar="{ src: person(task.assigneeEmail)!.avatarUrl ?? undefined, alt: person(task.assigneeEmail)!.name, icon: 'i-fadl-user' }"
                              size="xs"
                            />
                            <span
                              v-else
                              class="text-xs text-dimmed"
                            >{{ t('assistant.preview.unassigned') }}</span>
                          </div>
                        </li>
                      </ul>
                    </div>
                  </template>
                </UCollapsible>
              </li>
            </ul>
          </div>
        </template>
      </StepTimeline>
    </section>

    <!-- Save -->
    <section
      style="--plan-step: 6"
      class="plan-in sticky bottom-0 -mx-4 space-y-3 border-t border-default bg-default/95 px-4 py-4 backdrop-blur sm:-mx-6 sm:px-6"
    >
      <UAlert
        v-if="error"
        color="error"
        variant="subtle"
        icon="i-fadl-circle-exclamation"
        :title="t('assistant.preview.importFailed')"
        :description="error"
      />
      <div class="flex flex-wrap items-center justify-between gap-3">
        <UCheckbox
          v-model="notifyAssignees"
          :label="t('assistant.preview.notify')"
          :description="t('assistant.preview.notifyHint')"
        />
        <div class="flex gap-2">
          <UButton
            variant="ghost"
            color="neutral"
            icon="i-fadl-trash-can"
            :label="t('assistant.preview.discard')"
            :disabled="importing"
            @click="emit('discard')"
          />
          <UButton
            icon="i-fadl-floppy-disk-circle-arrow-right"
            :label="t('assistant.preview.import')"
            :loading="importing"
            @click="emit('import', notifyAssignees)"
          />
        </div>
      </div>
    </section>
  </div>
</template>
