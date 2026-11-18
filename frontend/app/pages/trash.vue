<script setup lang="ts">
import { useApolloClient, useMutation, useQuery } from '@vue/apollo-composable'
import type { TrashEntry } from '~/components/trash/List.vue'
import { purgeFromTrashMutation, trashQuery } from '~/graphql/trash'

/**
 * Deleted projects (docs/auftrag-m2.md §4.3): those the viewer may bring
 * back (project.delete in them, or projects.manageAny). Deleting one for
 * good asks with its name.
 */
const { t } = useI18n()
const toast = useToast()
const { resolveClient } = useApolloClient()

useHead({ title: () => t('trash.projectsTitle') })

const { result, loading } = useQuery(trashQuery, { projectId: null }, { fetchPolicy: 'cache-and-network' })
const items = computed(() => result.value?.trash ?? [])

const restore = useTrashRestore()
const { mutate: purge } = useMutation(purgeFromTrashMutation)

const purging = useConfirmedDelete<TrashEntry>(async (entry) => {
  await purge({ deletionId: entry.deletionId })
  evictProjectLists(resolveClient().cache)
}, {
  onDeleted: entry => toast.add({ title: t('trash.projectPurged', { name: entry.title }), color: 'success' })
})
</script>

<template>
  <PageFrame
    :title="t('trash.projectsTitle')"
    :breadcrumbs="[{ label: t('nav.workspace'), to: '/' }, { label: t('trash.projectsTitle') }]"
    :description="t('trash.projectsHint')"
  >
    <div class="trash-page">
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
    </div>

    <ConfirmModal
      :open="purging.isOpen"
      :title="t('trash.projectPurgeTitle', { name: purging.target?.title ?? '' })"
      :description="t('trash.projectPurgeDescription')"
      :confirm-label="t('trash.purge')"
      :action="purging.confirm"
      @close="purging.close"
    />
  </PageFrame>
</template>

<style scoped>
.trash-page {
  max-inline-size: 56rem;
}

.trash-skeleton {
  block-size: 8rem;
}
</style>
