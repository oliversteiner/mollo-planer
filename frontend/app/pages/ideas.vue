<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import type { IdeasQuery } from '~/graphql/generated/graphql'
import { createIdeaMutation, deleteIdeaMutation, ideasQuery, setIdeaStatusMutation, updateIdeaMutation } from '~/graphql/ideas'
import { formatDateTime } from '~/utils/dates'
import { countsText, newCounts, readPlan } from '~/utils/assistant'

/**
 * Ideas (docs/auftrag-m6.md §6): private notes, optionally with the draft of
 * the assistant. Own ideas only (those with ideas.viewAny see all). An idea
 * can be planned with the assistant, discarded, reopened and deleted (trash,
 * with undo). Adopted ideas link to the todos that came from them.
 */
type Idea = IdeasQuery['ideas']['data'][number]
type Filter = 'ALL' | 'OPEN' | 'ADOPTED' | 'DISCARDED'

const { t, localeProperties } = useI18n()
const toast = useToast()
const language = computed(() => localeProperties.value.language ?? 'de-CH')

useHead({ title: () => t('ideas.title') })

const filter = ref<Filter>('OPEN')
const { result, loading } = useQuery(ideasQuery, () => ({ status: filter.value === 'ALL' ? null : filter.value, first: 15 }), { fetchPolicy: 'cache-and-network' })
const ideas = computed(() => result.value?.ideas.data ?? [])

const silent = { context: { silentErrors: true }, refetchQueries: ['Ideas'] }
const { mutate: createIdea } = useMutation(createIdeaMutation, silent)
const { mutate: updateIdea } = useMutation(updateIdeaMutation, silent)
const { mutate: setStatus } = useMutation(setIdeaStatusMutation, silent)
const { mutate: deleteIdea } = useMutation(deleteIdeaMutation, silent)
const deletion = useDelete<string>(id => deleteIdea({ id }))
const { errors, pending, run } = useGraphqlForm()

// Create and edit share one dialog.
const dialogOpen = ref(false)
const editing = ref<Idea | null>(null)
const title = ref('')
const description = ref<Record<string, unknown> | null>(null)

function openDialog(idea: Idea | null = null) {
  editing.value = idea
  title.value = idea?.title ?? ''
  description.value = (idea?.description as Record<string, unknown> | null) ?? null
  errors.value = {}
  dialogOpen.value = true
}

async function save() {
  const input = { title: title.value.trim(), description: description.value }
  const target = editing.value
  const result = target
    ? await run(() => updateIdea({ id: target.id, input }))
    : await run(() => createIdea({ input }))

  if (result) {
    dialogOpen.value = false
    toast.add({ title: t(editing.value ? 'ideas.saved' : 'ideas.created'), color: 'success' })
  }
}

async function changeStatus(idea: Idea, status: 'OPEN' | 'DISCARDED') {
  await run(() => setStatus({ id: idea.id, status }))
}

function planWith(idea: Idea) {
  return navigateTo({ path: '/assistant', query: { idea: idea.id, ...(idea.project ? { project: idea.project.id } : {}) } })
}

function draftSummary(idea: Idea): string | null {
  const document = readPlan(idea.draft ?? null)

  const line = document ? countsText(newCounts(document), (kind, count) => t(`assistant.counts.${kind}`, { count }, count)) : null

  return line ? t('ideas.draftCounts', { list: line }) : null
}

function descriptionOf(idea: Idea): Record<string, unknown> | null {
  return (idea.description as Record<string, unknown> | null) ?? null
}

const filters: Filter[] = ['OPEN', 'ADOPTED', 'DISCARDED', 'ALL']
</script>

<template>
  <PageFrame
    :title="t('ideas.title')"
    :breadcrumbs="[{ label: t('nav.workspace'), to: '/' }, { label: t('ideas.title') }]"
    :description="t('ideas.intro')"
  >
    <template #actions>
      <wa-button
        variant="brand"
        @click="openDialog()"
      >
        <wa-icon
          slot="start"
          name="plus"
        ></wa-icon>
        {{ t('ideas.new') }}
      </wa-button>
    </template>

    <div class="ideas-page wa-stack wa-gap-m">
      <div
        class="wa-cluster wa-gap-xs"
        role="group"
        :aria-label="t('ideas.filter')"
      >
        <wa-button
          v-for="value in filters"
          :key="value"
          size="small"
          :appearance="filter === value ? 'filled' : 'outlined'"
          :variant="filter === value ? 'brand' : 'neutral'"
          :aria-pressed="filter === value"
          @click="filter = value"
        >
          {{ t(`ideas.status.${value}`) }}
        </wa-button>
      </div>

      <wa-skeleton
        v-if="loading && !result"
        effect="sheen"
        class="ideas-skeleton"
      ></wa-skeleton>
      <EmptyState
        v-else-if="!ideas.length"
        icon="lightbulb"
        :title="t('ideas.empty')"
        :description="t('ideas.emptyDescription')"
      />

      <wa-card
        v-for="idea in ideas"
        :key="idea.id"
        class="idea"
      >
        <div class="wa-stack wa-gap-s">
          <div class="wa-split wa-gap-s">
            <h2 class="wa-heading-s">
              {{ idea.title }}
            </h2>
            <wa-badge :variant="idea.status === 'ADOPTED' ? 'success' : idea.status === 'DISCARDED' ? 'neutral' : 'brand'">
              {{ t(`ideas.status.${idea.status}`) }}
            </wa-badge>
          </div>

          <p class="idea-meta">
            {{ formatDateTime(idea.createdAt, language) }}
            <template v-if="idea.project">
              · <NuxtLink :to="`/projects/${idea.project.id}`">
                {{ idea.project.name }}
              </NuxtLink>
            </template>
            <template v-if="!idea.viewerCanUpdate">
              · {{ idea.author.name }}
            </template>
            <template v-if="draftSummary(idea)">
              · {{ draftSummary(idea) }}
            </template>
          </p>

          <RichTextView :content="descriptionOf(idea)" />

          <p
            v-if="idea.todos.length"
            class="idea-meta"
          >
            {{ t('ideas.todos') }}
            <template
              v-for="(todo, index) in idea.todos"
              :key="todo.id"
            >
              <NuxtLink :to="`/projects/${todo.project.id}`">
                {{ todo.title }}
              </NuxtLink>{{ index < idea.todos.length - 1 ? ', ' : '' }}
            </template>
          </p>
        </div>

        <div
          v-if="idea.viewerCanUpdate"
          slot="footer"
          class="wa-cluster wa-gap-xs"
        >
          <wa-button
            v-if="idea.status === 'OPEN'"
            size="small"
            variant="brand"
            appearance="outlined"
            @click="planWith(idea)"
          >
            <wa-icon
              slot="start"
              name="wand-magic-sparkles"
            ></wa-icon>
            {{ t('ideas.plan') }}
          </wa-button>
          <wa-button
            size="small"
            appearance="plain"
            variant="neutral"
            @click="openDialog(idea)"
          >
            {{ t('ideas.edit') }}
          </wa-button>
          <wa-button
            v-if="idea.status === 'OPEN'"
            size="small"
            appearance="plain"
            variant="neutral"
            @click="changeStatus(idea, 'DISCARDED')"
          >
            {{ t('ideas.discard') }}
          </wa-button>
          <wa-button
            v-else-if="idea.status === 'DISCARDED'"
            size="small"
            appearance="plain"
            variant="neutral"
            @click="changeStatus(idea, 'OPEN')"
          >
            {{ t('ideas.reopen') }}
          </wa-button>
          <wa-button
            size="small"
            appearance="plain"
            variant="danger"
            @click="deletion.remove(idea.id)"
          >
            {{ t('ideas.delete') }}
          </wa-button>
        </div>
      </wa-card>
    </div>

    <AppDialog
      v-model:open="dialogOpen"
      :label="editing ? t('ideas.edit') : t('ideas.new')"
    >
      <form
        class="wa-stack wa-gap-m"
        @submit.prevent="save"
      >
        <FormInput
          v-model="title"
          :label="t('ideas.fieldTitle')"
          :error="errors.title"
          required
          autofocus
        />
        <div class="wa-stack wa-gap-2xs">
          <span class="wa-caption-m">{{ t('ideas.fieldDescription') }}</span>
          <RichTextEditor
            v-model="description"
            :placeholder="t('ideas.descriptionPlaceholder')"
          />
          <p
            v-if="errors.description"
            class="wa-caption-s"
            data-form-error
          >
            {{ errors.description }}
          </p>
        </div>
        <button
          type="submit"
          hidden
        />
      </form>
      <template #footer>
        <wa-button
          appearance="plain"
          variant="neutral"
          @click="dialogOpen = false"
        >
          {{ t('common.cancel') }}
        </wa-button>
        <wa-button
          variant="brand"
          :loading="pending"
          :disabled="title.trim().length < 2"
          @click="save"
        >
          {{ t('common.save') }}
        </wa-button>
      </template>
    </AppDialog>
  </PageFrame>
</template>

<style scoped>
.ideas-page {
  max-inline-size: 48rem;
}

.ideas-skeleton {
  block-size: 6rem;
}

.idea {
  inline-size: 100%;
}

.idea-meta {
  margin: 0;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}
</style>
