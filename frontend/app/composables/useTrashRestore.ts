import { useApolloClient } from '@vue/apollo-composable'
import { restoreFromTrashMutation } from '~/graphql/trash'

/**
 * Brings a deletion back from the trash: from the undo message (useDelete)
 * and from the trash pages. Loads the project again (useProjectMutation).
 * A refusal because a parent is in the trash comes as a validation message
 * at "deletionId" and is shown as a message, naming what has to come back
 * first. A project that comes back changes the lists of projects, so they
 * are read again (cache.evict, docs/auftrag-m2.md §0a.6).
 */
export function useTrashRestore() {
  const { t } = useI18n()
  const toast = useToast()
  const { resolveClient } = useApolloClient()
  const { mutate } = useProjectMutation(restoreFromTrashMutation)
  const { errors, run } = useGraphqlForm()

  return async function restore(deletionId: string): Promise<boolean> {
    const result = await run(() => mutate({ deletionId }))

    if (!result?.data) {
      if (errors.value.deletionId) {
        toast.add({ title: t('trash.restoreFailed'), description: errors.value.deletionId, color: 'error' })
      }

      return false
    }

    if (result.data.restoreFromTrash.type === 'PROJECT') {
      evictProjectLists(resolveClient().cache)
    }

    toast.add({ title: t('trash.restored'), color: 'success' })

    return true
  }
}

/** The lists of projects (sidebar, project list, archive) are read again. */
export function evictProjectLists(cache: { evict: (options: { id: string, fieldName: string }) => boolean, gc: () => string[] }): void {
  cache.evict({ id: 'ROOT_QUERY', fieldName: 'projects' })
  cache.evict({ id: 'ROOT_QUERY', fieldName: 'trash' })
  cache.gc()
}
