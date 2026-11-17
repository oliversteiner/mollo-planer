<script setup lang="ts">
import { useQuery } from '@vue/apollo-composable'
import type { TrashEntry } from '~/components/trash/List.vue'
import { emptyProjectTrashMutation, purgeFromTrashMutation, trashQuery } from '~/graphql/trash'

/**
 * The trash of the project (docs/auftrag-m2.md §4.2): what was deleted,
 * oldest first; restore with trash.restore, delete for good and empty the
 * trash with trash.purge. Without either right the page is closed.
 */
const { t } = useI18n()
const toast = useToast()
const { projectId, loaded, can } = useProject()

const allowed = computed(() => can('TRASH_RESTORE') || can('TRASH_PURGE'))

watchEffect(() => {
  if (loaded.value && !allowed.value) {
    void navigateTo(`/projects/${projectId.value}`, { replace: true })
  }
})

const { result, loading } = useQuery(trashQuery, () => ({ projectId: projectId.value }), () => ({
  enabled: allowed.value,
  fetchPolicy: 'cache-and-network'
}))
const items = computed(() => result.value?.trash ?? [])

const restore = useTrashRestore()
const { mutate: purge } = useProjectMutation(purgeFromTrashMutation)
const { mutate: emptyTrash } = useProjectMutation(emptyProjectTrashMutation)

const purging = useConfirmedDelete<TrashEntry>(entry => purge({ deletionId: entry.deletionId }), {
  onDeleted: () => toast.add({ title: t('trash.purged'), color: 'success' })
})

const emptying = ref(false)

async function confirmEmpty(): Promise<void> {
  await emptyTrash({ projectId: projectId.value })
  toast.add({ title: t('trash.emptied'), color: 'success' })
}
</script>

<template>
  <div class="wa-stack wa-gap-m trash-page">
    <Teleport
      v-if="can('TRASH_PURGE') && items.length"
      to="#page-actions"
      defer
    >
      <wa-button
        variant="danger"
        appearance="outlined"
        @click="emptying = true"
      >
        <wa-icon
          slot="start"
          name="trash-can-xmark"
        ></wa-icon>
        {{ t('trash.emptyTrash') }}
      </wa-button>
    </Teleport>

    <p class="trash-hint">
      {{ t('trash.hint') }}
    </p>

    <wa-skeleton
      v-if="loading && !result"
      effect="sheen"
      class="trash-skeleton"
    ></wa-skeleton>
    <EmptyState
      v-else-if="!items.length"
      icon="trash-can"
      :title="t('trash.empty')"
    />
    <TrashList
      v-else
      :items="items"
      @restore="entry => restore(entry.deletionId)"
      @purge="entry => purging.ask(entry)"
    />

    <ConfirmModal
      :open="purging.isOpen"
      :title="t('trash.purgeTitle')"
      :description="t('trash.purgeDescription', { title: purging.target?.title ?? '' })"
      :confirm-label="t('trash.purge')"
      :action="purging.confirm"
      @close="purging.close"
    />
    <ConfirmModal
      :open="emptying"
      :title="t('trash.emptyTitle')"
      :description="t('trash.emptyDescription', { count: items.length }, items.length)"
      :confirm-label="t('trash.emptyTrash')"
      :action="confirmEmpty"
      @close="emptying = false"
    />
  </div>
</template>

<style scoped>
.trash-page {
  max-inline-size: 56rem;
}

.trash-hint {
  margin: 0;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}

.trash-skeleton {
  block-size: 8rem;
}
</style>
