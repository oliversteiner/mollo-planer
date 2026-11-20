<script setup lang="ts">
import { useQuery } from '@vue/apollo-composable'
import { documentVersionQuery, documentVersionsQuery, restoreDocumentVersionMutation } from '~/graphql/documents'
import { formatDateTime } from '~/utils/dates'

/**
 * The history of a document (docs/dokumentenverwaltung.md §4.4): the saved versions, newest first, without their
 * content. Two versions can be chosen and compared; the content of those two is loaded then, not before. With the
 * level EDIT a version can be brought back: that makes a NEW version with the old content, nothing is overwritten.
 */
const { t, localeProperties } = useI18n()
const toast = useToast()
const route = useRoute()
const { projectId } = useProject()
const language = computed(() => localeProperties.value.language ?? 'de-CH')

const documentId = computed(() => String(route.params.docId))
const { result, loading } = useQuery(documentVersionsQuery, () => ({ id: documentId.value }), { fetchPolicy: 'cache-and-network' })
const doc = computed(() => result.value?.document ?? null)
const versions = computed(() => doc.value?.versions ?? [])
const canRestore = computed(() => doc.value?.viewerAccess === 'EDIT' || doc.value?.viewerAccess === 'MANAGE')

useHead({ title: () => (doc.value ? `${doc.value.title} · ${t('documents.versions.title')}` : t('documents.versions.title')) })

/** The chosen revisions, at most two: choosing a third lets the one chosen first go. */
const chosen = ref<number[]>([])

function toggle(revision: number, checked: boolean): void {
  chosen.value = checked
    ? [...chosen.value.filter(entry => entry !== revision), revision].slice(-2)
    : chosen.value.filter(entry => entry !== revision)
}

const olderRevision = computed(() => (chosen.value.length === 2 ? Math.min(...chosen.value) : null))
const newerRevision = computed(() => (chosen.value.length === 2 ? Math.max(...chosen.value) : null))

const { result: olderResult } = useQuery(documentVersionQuery, () => ({ documentId: documentId.value, revision: olderRevision.value! }), () => ({ enabled: olderRevision.value !== null }))
const { result: newerResult } = useQuery(documentVersionQuery, () => ({ documentId: documentId.value, revision: newerRevision.value! }), () => ({ enabled: newerRevision.value !== null }))

const older = computed(() => olderResult.value?.documentVersion ?? null)
const newer = computed(() => newerResult.value?.documentVersion ?? null)
const comparing = computed(() => olderRevision.value !== null && older.value?.revision === olderRevision.value && newer.value?.revision === newerRevision.value)

const restoring = ref<number | null>(null)
const { mutate: restoreVersion } = useProjectMutation(restoreDocumentVersionMutation)

async function confirmRestore(): Promise<void> {
  if (restoring.value === null) {
    return
  }
  await restoreVersion({ id: documentId.value, revision: restoring.value })
  toast.add({ title: t('documents.versions.restored'), color: 'success' })
  await navigateTo(`/projects/${projectId.value}/documents/${documentId.value}`)
}

function entry(version: (typeof versions.value)[number]): string {
  const at = formatDateTime(version.createdAt, language.value)

  return version.createdBy ? `${t('documents.versions.by', { name: version.createdBy.name })} · ${at}` : at
}
</script>

<template>
  <div class="wa-stack wa-gap-m history-page">
    <Teleport
      to="#page-actions"
      defer
    >
      <wa-button
        appearance="outlined"
        variant="neutral"
        :href="`/projects/${projectId}/documents/${documentId}`"
        @click.prevent="navigateTo(`/projects/${projectId}/documents/${documentId}`)"
      >
        <wa-icon
          slot="start"
          name="arrow-left"
        ></wa-icon>
        {{ doc?.title ?? t('documents.title') }}
      </wa-button>
    </Teleport>

    <wa-skeleton
      v-if="loading && !doc"
      effect="sheen"
      class="history-skeleton"
    ></wa-skeleton>
    <EmptyState
      v-else-if="!doc"
      icon="file-lines"
      :title="t('documents.notFound')"
    />
    <template v-else>
      <h2 class="wa-heading-l history-title">
        {{ t('documents.versions.title') }}
      </h2>
      <p
        class="history-hint"
        data-zen="hide"
      >
        {{ t('documents.versions.pick') }}
      </p>

      <EmptyState
        v-if="!versions.length"
        icon="clock-rotate-left"
        :title="t('documents.versions.none')"
      />
      <ul
        v-else
        class="history-list wa-list-plain"
      >
        <li
          v-for="version in versions"
          :key="version.revision"
          class="history-row"
          data-zen-row
        >
          <FormCheckbox
            :model-value="chosen.includes(version.revision)"
            :label="t('documents.versions.revision', { revision: version.revision })"
            @update:model-value="toggle(version.revision, $event)"
          />
          <span
            class="history-sub"
            data-zen="hide"
          >
            {{ entry(version) }}<template v-if="version.restoredFrom"> · {{ t('documents.versions.restoredFrom', { revision: version.restoredFrom }) }}</template>
          </span>
          <wa-button
            v-if="canRestore"
            class="history-restore"
            appearance="outlined"
            variant="neutral"
            size="s"
            data-zen="hover"
            @click="restoring = version.revision"
          >
            <wa-icon
              slot="start"
              name="clock-rotate-left"
            ></wa-icon>
            {{ t('documents.versions.restore') }}
          </wa-button>
        </li>
      </ul>

      <section
        v-if="chosen.length === 2"
        class="wa-stack wa-gap-s"
      >
        <h3 class="wa-heading-m">
          {{ t('documents.versions.compare') }}: {{ t('documents.versions.revision', { revision: olderRevision! }) }} → {{ t('documents.versions.revision', { revision: newerRevision! }) }}
        </h3>
        <DocumentDiff
          v-if="comparing && older && newer"
          :older="older.content"
          :newer="newer.content"
        />
        <wa-skeleton
          v-else
          effect="sheen"
          class="history-skeleton"
        ></wa-skeleton>
      </section>
    </template>

    <ConfirmModal
      :open="restoring !== null"
      :title="t('documents.versions.restoreTitle', { revision: restoring ?? 0 })"
      :description="t('documents.versions.restoreText')"
      :confirm-label="t('documents.versions.restore')"
      :action="confirmRestore"
      @close="restoring = null"
    />
  </div>
</template>

<style scoped>
.history-page {
  max-inline-size: 48rem;
}

.history-skeleton {
  block-size: 8rem;
}

.history-title {
  margin: 0;
}

.history-hint,
.history-sub {
  margin: 0;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}

.history-list {
  margin: 0;
  padding: 0;
  border: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  border-radius: var(--wa-border-radius-l);
}

.history-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-s);
  padding: var(--wa-space-xs) var(--wa-space-m);
}

.history-row + .history-row {
  border-block-start: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
}

.history-sub {
  flex: 1;
  min-inline-size: 0;
}
</style>
