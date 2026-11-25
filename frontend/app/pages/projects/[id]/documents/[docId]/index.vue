<script setup lang="ts">
import { useQuery } from '@vue/apollo-composable'
import type { ActionMenuItem } from '~/components/ActionMenu.vue'
import { createDocumentMutation, deleteDocumentMutation, documentQuery, updateDocumentMutation } from '~/graphql/documents'
import type { DocumentKind } from '~/graphql/generated/graphql'
import { classifyApolloError, isDocumentConflict } from '~/utils/api'
import { formatDateTime } from '~/utils/dates'

const KINDS: DocumentKind[] = ['PROCEDURE', 'MINUTES', 'ANALYSIS', 'CONCEPT', 'OTHER']

type Content = Record<string, unknown>

/**
 * One document: read it, or (with the level EDIT) edit it on the same page. The buttons follow Document.viewerAccess,
 * which the server works out (the frontend never computes a level). Saving sends the revision that was loaded: when
 * somebody else saved in the meantime the server refuses (DOCUMENT_CONFLICT) and the dialog offers to reload or to
 * save one's own text as a new document. While typing a draft is kept in this browser; leaving with unsaved
 * changes is asked about.
 */
const { t, localeProperties } = useI18n()
const toast = useToast()
const route = useRoute()
const { projectId, loaded } = useProject()
const { focusing, enterFocus, leaveFocus } = useViewMode()
const language = computed(() => localeProperties.value.language ?? 'de-CH')

const documentId = computed(() => String(route.params.docId))
const { result, loading, refetch } = useQuery(documentQuery, () => ({ id: documentId.value }), { fetchPolicy: 'cache-and-network' })
const doc = computed(() => result.value?.document ?? null)

const canEdit = computed(() => doc.value?.viewerAccess === 'EDIT' || doc.value?.viewerAccess === 'MANAGE')
const canManage = computed(() => doc.value?.viewerAccess === 'MANAGE')

useHead({ title: () => doc.value?.title ?? t('documents.title') })

const { mutate: updateDocument } = useProjectMutation(updateDocumentMutation)
const { mutate: createDocument } = useProjectMutation(createDocumentMutation)
const { mutate: deleteDocument } = useProjectMutation(deleteDocumentMutation)

// ─── Editing ────────────────────────────────────────────────────

const editing = ref(false)
const title = ref('')
const kind = ref<DocumentKind>('OTHER')
const content = ref<Content | null>(null)
/** Set by what the person types, not by the editor loading its content (it adds attributes of its own). */
const dirty = ref(false)
const saving = ref(false)
const saveError = ref('')
const conflict = ref(false)

interface DraftValue { title: string, kind: DocumentKind, contentJson: Content | null }

const draft = useLocalDraft<DraftValue>(() => `mollo-planer:document-draft:${documentId.value}`)
const offeredDraft = ref<ReturnType<typeof draft.read>>(null)

function startEditing(): void {
  if (!doc.value) {
    return
  }
  title.value = doc.value.title
  kind.value = doc.value.kind
  content.value = doc.value.contentJson as Content
  dirty.value = false
  saveError.value = ''
  editing.value = true

  const saved = draft.read()

  if (saved && (saved.value.title !== doc.value.title || saved.value.kind !== doc.value.kind || JSON.stringify(saved.value.contentJson) !== JSON.stringify(doc.value.contentJson))) {
    offeredDraft.value = saved
  }
}

function restoreDraft(): void {
  const saved = offeredDraft.value

  if (saved) {
    title.value = saved.value.title
    kind.value = saved.value.kind
    content.value = saved.value.contentJson
    dirty.value = true
  }
  offeredDraft.value = null
}

function discardDraft(): void {
  draft.clear()
  offeredDraft.value = null
}

function stopEditing(): void {
  editing.value = false
  dirty.value = false
}

function typed(): void {
  dirty.value = true
}

function onContent(value: Content | null): void {
  content.value = value
  typed()
}

function onKind(value: string | null): void {
  kind.value = (value ?? 'OTHER') as DocumentKind
  typed()
}

/** The draft follows the typing after a short pause. */
let draftTimer: ReturnType<typeof setTimeout> | undefined

watch([title, kind, content], () => {
  if (!editing.value || !dirty.value || !doc.value) {
    return
  }
  clearTimeout(draftTimer)
  draftTimer = setTimeout(writeDraft, 500)
}, { deep: true })

function writeDraft(): void {
  if (doc.value) {
    draft.write(doc.value.revision, { title: title.value, kind: kind.value, contentJson: content.value })
  }
}

onBeforeUnmount(() => clearTimeout(draftTimer))

async function save(): Promise<void> {
  if (!doc.value || saving.value || !editing.value || !dirty.value) {
    return
  }
  saving.value = true
  saveError.value = ''

  try {
    await updateDocument(
      { id: doc.value.id, revision: doc.value.revision, input: { title: title.value.trim(), kind: kind.value, contentJson: content.value } },
      { context: { silentErrors: true } }
    )
    clearTimeout(draftTimer)
    draft.clear()
    dirty.value = false
    toast.add({ title: t('documents.saved'), color: 'success' })
  } catch (error) {
    if (isDocumentConflict(error)) {
      clearTimeout(draftTimer)
      writeDraft()
      conflict.value = true
    } else {
      const failure = classifyApolloError(error)
      // Why the server refused (for example an element the profile does not allow) is shown, never swallowed.
      saveError.value = Object.values(failure.fields).flat().join(' ') || failure.message || t('documents.saveFailed')
    }
  } finally {
    saving.value = false
  }
}

/** Loads the newer version; the draft stays in this browser and is offered again. */
async function reloadAfterConflict(): Promise<void> {
  conflict.value = false
  await refetch()
  startEditing()
}

async function saveAsNew(): Promise<void> {
  if (!doc.value) {
    return
  }
  saving.value = true

  try {
    const created = await createDocument({
      input: { projectId: projectId.value, title: title.value.trim(), kind: kind.value, contentJson: content.value ?? { type: 'doc', content: [{ type: 'paragraph' }] }, projectAccess: doc.value.projectAccess }
    }, { context: { silentErrors: true } })
    const id = created?.data?.createDocument.id

    if (id) {
      conflict.value = false
      draft.clear()
      dirty.value = false
      toast.add({ title: t('documents.conflict.savedAsNew'), color: 'success' })
      await navigateTo(`/projects/${projectId.value}/documents/${id}`)
    }
  } catch (error) {
    const failure = classifyApolloError(error)
    toast.add({ title: Object.values(failure.fields).flat().join(' ') || failure.message || t('documents.saveFailed'), color: 'error' })
  } finally {
    saving.value = false
  }
}

function onKeydown(event: KeyboardEvent): void {
  if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's' && editing.value) {
    event.preventDefault()
    void save()
  }
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))

const unsaved = useUnsavedChanges(() => editing.value && dirty.value)

// A new document opens in edit mode (?edit=1).
watch(doc, (value) => {
  if (value && !editing.value && route.query.edit === '1' && canEdit.value) {
    startEditing()
    void navigateTo({ query: { ...route.query, edit: undefined } }, { replace: true })
  }
}, { immediate: true })

// ─── Sharing, owner, delete ─────────────────────────────────────

const sharing = ref(false)
const transferring = ref(false)

const deletion = useDelete<NonNullable<typeof doc.value>>(target => deleteDocument({ id: target.id }), {
  onDeleted: () => {
    dirty.value = false
    void navigateTo(`/projects/${projectId.value}/documents`)
  }
})

const moreItems = computed<ActionMenuItem[][]>(() => {
  const current = doc.value

  if (!current) {
    return []
  }
  const items: ActionMenuItem[][] = [[
    { label: t('documents.history'), icon: 'clock-rotate-left', onSelect: () => { void navigateTo(`/projects/${projectId.value}/documents/${current.id}/history`) } }
  ]]

  if (canManage.value) {
    items.push([
      { label: t('documents.share'), icon: 'share-nodes', onSelect: () => { sharing.value = true } },
      { label: t('documents.transfer'), icon: 'user-gear', onSelect: () => { transferring.value = true } }
    ], [
      { label: t('common.delete'), icon: 'trash', color: 'error', onSelect: () => { void deletion.remove(current) } }
    ])
  }

  return items
})

const exporter = useDocumentExport(() => doc.value)

/** Leaving the focus view asks about unsaved changes like leaving the page (it is the same route, so the router does not). */
async function leaveFocusGuarded(): Promise<void> {
  if (await unsaved.guard()) {
    await leaveFocus()
  }
}

function onEscape(event: KeyboardEvent): void {
  if (event.key === 'Escape' && focusing.value && !document.querySelector('wa-dialog[open]')) {
    void leaveFocusGuarded()
  }
}

onMounted(() => window.addEventListener('keydown', onEscape))
onBeforeUnmount(() => window.removeEventListener('keydown', onEscape))

/** The saving state for the zen frame (Teleport to #zen-status). */
const statusText = computed(() => {
  if (!editing.value) {
    return ''
  }

  return saving.value ? t('documents.saving') : dirty.value ? t('documents.unsavedHint') : t('documents.allSaved')
})

// The buttons are in the page actions, which the calm view hides: everything stays reachable through the palette.
useCommands(() => {
  const current = doc.value
  const go = (path: string) => async (): Promise<void> => {
    await navigateTo(path)
  }

  return [
    { id: 'document-edit', label: t('documents.edit'), icon: 'pen', group: 'action' as const, visible: () => canEdit.value && !editing.value && doc.value !== null, run: startEditing },
    { id: 'document-save', label: t('documents.save'), icon: 'floppy-disk', group: 'action' as const, visible: () => editing.value && dirty.value, run: () => { void save() } },
    { id: 'document-focus', label: `${t('documents.focus')}: ${current?.title ?? ''}`, icon: 'bullseye', group: 'action' as const, visible: () => doc.value !== null && !focusing.value, run: () => { void enterFocus({ kind: 'document', id: documentId.value }) } },
    { id: 'document-history', label: t('documents.history'), icon: 'clock-rotate-left', group: 'action' as const, visible: () => doc.value !== null, run: go(`/projects/${projectId.value}/documents/${documentId.value}/history`) },
    { id: 'document-export-markdown', label: `${t('documents.export')}: ${t('documents.exportMarkdown')}`, icon: 'file-lines', group: 'action' as const, visible: () => doc.value !== null, run: exporter.markdown },
    { id: 'document-export-pdf', label: `${t('documents.export')}: ${t('documents.exportPdf')}`, icon: 'file-pdf', group: 'action' as const, visible: () => doc.value !== null && !exporter.making.value, run: () => { void exporter.pdf() } },
    { id: 'document-export-pdf-letterhead', label: `${t('documents.export')}: ${t('documents.exportPdfLetterhead')}`, icon: 'file-pdf', group: 'action' as const, visible: () => doc.value !== null && !exporter.making.value && exporter.letterheadAvailable.value, run: () => { void exporter.pdf(true) } },
    { id: 'document-share', label: t('documents.share'), icon: 'share-nodes', group: 'action' as const, visible: () => canManage.value, run: () => { sharing.value = true } },
    { id: 'document-transfer', label: t('documents.transfer'), icon: 'user-gear', group: 'action' as const, visible: () => canManage.value, run: () => { transferring.value = true } }
  ]
})

function changed(): string {
  const current = doc.value

  if (!current) {
    return ''
  }
  const date = formatDateTime(current.updatedAt, language.value)

  return current.updatedBy ? t('documents.updated', { date, name: current.updatedBy.name }) : t('documents.updatedAt', { date })
}

const kindItems = computed(() => KINDS.map(item => ({ label: t(`documents.kinds.${item}`), value: item })))
</script>

<template>
  <div class="wa-stack wa-gap-m document-page">
    <wa-skeleton
      v-if="(loading && !doc) || !loaded"
      effect="sheen"
      class="document-skeleton"
    ></wa-skeleton>
    <EmptyState
      v-else-if="!doc"
      icon="file-lines"
      :title="t('documents.notFound')"
    >
      <wa-button
        appearance="outlined"
        variant="neutral"
        :href="`/projects/${projectId}/documents`"
        @click.prevent="navigateTo(`/projects/${projectId}/documents`)"
      >
        {{ t('documents.backToList') }}
      </wa-button>
    </EmptyState>

    <template v-else>
      <Teleport
        to="#page-actions"
        defer
      >
        <template v-if="editing">
          <span
            v-if="dirty"
            class="document-unsaved"
            role="status"
          >{{ saving ? t('documents.saving') : t('documents.unsavedHint') }}</span>
          <wa-button
            appearance="outlined"
            variant="neutral"
            @click="stopEditing"
          >
            {{ t('documents.cancelEdit') }}
          </wa-button>
          <wa-button
            variant="brand"
            :loading="saving"
            :disabled="!dirty"
            @click="save"
          >
            <wa-icon
              slot="start"
              name="floppy-disk"
            ></wa-icon>
            {{ t('documents.save') }}
          </wa-button>
        </template>
        <template v-else>
          <wa-button
            v-if="canEdit"
            variant="brand"
            @click="startEditing"
          >
            <wa-icon
              slot="start"
              name="pen"
            ></wa-icon>
            {{ t('documents.edit') }}
          </wa-button>
          <wa-button
            appearance="outlined"
            variant="neutral"
            @click="enterFocus({ kind: 'document', id: doc.id })"
          >
            <wa-icon
              slot="start"
              name="bullseye"
            ></wa-icon>
            {{ t('documents.focus') }}
          </wa-button>
          <DocumentExportMenu :document="doc" />
        </template>
        <ActionMenu
          :items="moreItems"
          :label="t('common.actions')"
          size="m"
        />
      </Teleport>

      <Teleport
        v-if="statusText"
        to="#zen-status"
        defer
      >
        {{ statusText }}
      </Teleport>
      <div
        v-if="focusing"
        data-zen="only"
      >
        <wa-button
          appearance="plain"
          variant="neutral"
          size="s"
          @click="leaveFocusGuarded"
        >
          <wa-icon
            slot="start"
            name="arrow-left"
          ></wa-icon>
          {{ t('documents.focusBack') }}
        </wa-button>
      </div>

      <header class="wa-stack wa-gap-2xs">
        <template v-if="editing">
          <FormInput
            v-model="title"
            :label="t('documents.fieldTitle')"
            maxlength="200"
            required
            @update:model-value="typed"
          />
          <FormSelect
            :model-value="kind"
            :items="kindItems"
            :label="t('documents.fieldKind')"
            class="document-kind"
            @update:model-value="onKind"
          />
        </template>
        <template v-else>
          <h2 class="wa-heading-m document-title">
            {{ doc.title }}
          </h2>
          <p
            class="document-meta"
            data-zen="hide"
          >
            <wa-badge
              variant="neutral"
              appearance="outlined"
            >
              {{ t(`documents.kinds.${doc.kind}`) }}
            </wa-badge>
            <wa-badge
              :variant="doc.projectAccess === 'NONE' ? 'warning' : 'neutral'"
              appearance="outlined"
            >
              {{ t(`documents.access.${doc.projectAccess}`) }}
            </wa-badge>
            <span>{{ doc.owner ? t('documents.owner', { name: doc.owner.name }) : t('documents.noOwner') }}</span>
            <span>{{ changed() }}</span>
          </p>
        </template>
      </header>

      <AppCallout
        v-if="saveError"
        variant="danger"
        :description="saveError"
      />

      <RichTextEditor
        v-if="editing"
        :model-value="content"
        profile="document"
        :placeholder="t('documents.emptyContent')"
        @update:model-value="onContent"
      />
      <template v-else>
        <RichTextView
          :content="doc.contentJson"
          profile="document"
        />
        <p
          v-if="!doc.content.trim()"
          class="document-meta"
        >
          {{ t('documents.emptyContent') }}
        </p>
      </template>

      <DocumentShareDialog
        :open="sharing"
        :project-id="projectId"
        :document="doc"
        @close="sharing = false"
      />
      <DocumentTransferDialog
        :open="transferring"
        :project-id="projectId"
        :document-id="doc.id"
        :owner-id="doc.owner?.id ?? null"
        @close="transferring = false"
      />
    </template>

    <DocumentConflictDialog
      :open="conflict"
      :pending="saving"
      @reload="reloadAfterConflict"
      @save-as-new="saveAsNew"
    />
    <ConfirmModal
      :open="offeredDraft !== null"
      :title="t('documents.draft.title')"
      :description="t('documents.draft.text', { date: offeredDraft ? formatDateTime(offeredDraft.savedAt, language) : '' })"
      :confirm-label="t('documents.draft.restore')"
      :action="async () => restoreDraft()"
      @close="discardDraft"
    />
    <ConfirmModal
      :open="unsaved.open.value"
      :title="t('documents.leave.title')"
      :description="t('documents.leave.text')"
      :confirm-label="t('documents.leave.confirm')"
      :action="unsaved.confirm"
      @close="unsaved.stay"
    />
  </div>
</template>

<style scoped>
.document-page {
  max-inline-size: 48rem;
}

.document-skeleton {
  block-size: 10rem;
}

.document-title {
  margin: 0;
}

.document-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-xs);
  margin: 0;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}

.document-kind {
  inline-size: 14rem;
}

.document-unsaved {
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}
</style>
