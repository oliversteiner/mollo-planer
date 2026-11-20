<script setup lang="ts">
import { useQuery } from '@vue/apollo-composable'
import { documentsQuery } from '~/graphql/documents'
import type { DocumentKind } from '~/graphql/generated/graphql'
import { withoutNulls } from '~/utils/api'
import { formatDateTime } from '~/utils/dates'

const KINDS: DocumentKind[] = ['PROCEDURE', 'MINUTES', 'ANALYSIS', 'CONCEPT', 'OTHER']

/**
 * The documents of the project (docs/dokumentenverwaltung.md §4.4): the list of what the viewer may see, with kind,
 * owner, who may do what and the last change. Whoever may see the project's documents sees the list; creating one
 * needs document.create. The content is not loaded here.
 */
const { t, localeProperties } = useI18n()
const { projectId, can } = useProject()
const language = computed(() => localeProperties.value.language ?? 'de-CH')

/** "all" stands for "no filter" (a select cannot hold null). */
const kindFilter = ref('all')
const search = ref('')

/** The search goes to the server after a short pause, not with every key. */
const searchTerm = ref('')
let pause: ReturnType<typeof setTimeout> | undefined

watch(search, (value) => {
  clearTimeout(pause)
  pause = setTimeout(() => {
    searchTerm.value = value.trim()
  }, 300)
})
onBeforeUnmount(() => clearTimeout(pause))

const { result, loading } = useQuery(documentsQuery, () => ({
  projectId: projectId.value,
  ...withoutNulls({
    kind: kindFilter.value === 'all' ? null : kindFilter.value as DocumentKind,
    search: searchTerm.value || null
  })
}), { fetchPolicy: 'cache-and-network' })

const documents = computed(() => result.value?.documents ?? [])
const filtered = computed(() => kindFilter.value !== 'all' || searchTerm.value !== '')

const kindItems = computed(() => [
  { label: t('documents.allKinds'), value: 'all' },
  ...KINDS.map(kind => ({ label: t(`documents.kinds.${kind}`), value: kind }))
])

const creating = ref(false)

// The button is in the page actions, which the calm view hides.
useCommands(() => [{
  id: 'documents-create',
  label: t('documents.create'),
  icon: 'plus',
  group: 'action',
  visible: () => can('DOCUMENT_CREATE'),
  run: () => {
    creating.value = true
  }
}])

function changed(document: (typeof documents.value)[number]): string {
  const date = formatDateTime(document.updatedAt, language.value)

  return document.updatedBy ? t('documents.updated', { date, name: document.updatedBy.name }) : t('documents.updatedAt', { date })
}
</script>

<template>
  <div class="wa-stack wa-gap-m">
    <div
      class="wa-cluster wa-gap-s wa-align-items-end"
      data-zen="hide"
    >
      <FormSelect
        v-model="kindFilter"
        :items="kindItems"
        :label="t('documents.kind')"
        class="documents-filter"
      />
      <FormInput
        v-model="search"
        type="search"
        name="documents-search"
        autocomplete="off"
        :label="t('documents.search')"
        class="documents-search"
      >
        <template #start>
          <wa-icon
            slot="start"
            name="magnifying-glass"
          ></wa-icon>
        </template>
      </FormInput>
    </div>

    <Teleport
      v-if="can('DOCUMENT_CREATE')"
      to="#page-actions"
      defer
    >
      <wa-button
        variant="brand"
        @click="creating = true"
      >
        <wa-icon
          slot="start"
          name="plus"
        ></wa-icon>
        {{ t('documents.create') }}
      </wa-button>
    </Teleport>

    <wa-skeleton
      v-if="loading && !result"
      effect="sheen"
      class="documents-skeleton"
    ></wa-skeleton>
    <EmptyState
      v-else-if="!documents.length"
      icon="file-lines"
      :title="filtered ? t('documents.noMatch') : t('documents.none')"
      :description="filtered ? undefined : t('documents.noneHint')"
    >
      <wa-button
        v-if="!filtered && can('DOCUMENT_CREATE')"
        variant="brand"
        @click="creating = true"
      >
        <wa-icon
          slot="start"
          name="plus"
        ></wa-icon>
        {{ t('documents.create') }}
      </wa-button>
    </EmptyState>
    <ul
      v-else
      class="documents-list wa-list-plain"
    >
      <li
        v-for="document in documents"
        :key="document.id"
        class="documents-row"
        data-zen-row
      >
        <wa-icon
          name="file-lines"
          class="documents-icon"
        ></wa-icon>
        <NuxtLink
          :to="`/projects/${projectId}/documents/${document.id}`"
          class="documents-text"
        >
          <span class="documents-title">{{ document.title }}</span>
          <span
            class="documents-sub"
            data-zen="hide"
          >
            {{ document.owner ? t('documents.owner', { name: document.owner.name }) : t('documents.noOwner') }} · {{ changed(document) }}
          </span>
        </NuxtLink>
        <wa-badge
          variant="neutral"
          appearance="outlined"
          data-zen="hide"
        >
          {{ t(`documents.kinds.${document.kind}`) }}
        </wa-badge>
        <wa-badge
          :variant="document.projectAccess === 'NONE' ? 'warning' : 'neutral'"
          appearance="outlined"
          data-zen="hide"
        >
          {{ t(`documents.access.${document.projectAccess}`) }}
        </wa-badge>
      </li>
    </ul>

    <DocumentCreateDialog
      :open="creating"
      :project-id="projectId"
      @close="creating = false"
    />
  </div>
</template>

<style scoped>
.documents-filter {
  inline-size: 12rem;
}

.documents-search {
  inline-size: 16rem;
}

.documents-skeleton {
  block-size: 10rem;
}

.documents-list {
  margin: 0;
  padding: 0;
  border: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  border-radius: var(--wa-border-radius-l);
}

.documents-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-s);
  padding: var(--wa-space-xs) var(--wa-space-m);
}

.documents-row + .documents-row {
  border-block-start: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
}

.documents-icon {
  font-size: var(--wa-font-size-l);
  color: var(--wa-color-brand-on-quiet);
}

.documents-text {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-inline-size: 0;
  color: inherit;
  text-decoration: none;
}

.documents-text:hover .documents-title {
  color: var(--wa-color-brand-on-quiet);
}

.documents-title {
  overflow: hidden;
  font-weight: var(--wa-font-weight-semibold);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.documents-sub {
  overflow: hidden;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
