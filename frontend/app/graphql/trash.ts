import { graphql } from '~/graphql/generated'

/** The trash of a project, or without projectId the deleted projects (docs/auftrag-m2.md §3). */
export const trashQuery = graphql(`
  query Trash($projectId: ID) {
    trash(projectId: $projectId) {
      deletionId
      type
      id
      title
      deletedAt
      deletedBy { id name avatarUrl }
      project { id name icon }
      childCount
      childCounts { type count }
      viewerCanRestore
      viewerCanPurge
    }
  }
`)

export const restoreFromTrashMutation = graphql(`
  mutation RestoreFromTrash($deletionId: ID!) {
    restoreFromTrash(deletionId: $deletionId) { deletionId type id title }
  }
`)

export const purgeFromTrashMutation = graphql(`
  mutation PurgeFromTrash($deletionId: ID!) {
    purgeFromTrash(deletionId: $deletionId)
  }
`)

export const emptyProjectTrashMutation = graphql(`
  mutation EmptyProjectTrash($projectId: ID!) {
    emptyProjectTrash(projectId: $projectId)
  }
`)
