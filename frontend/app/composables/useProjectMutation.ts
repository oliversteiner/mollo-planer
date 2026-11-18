import type { OperationVariables, TypedDocumentNode } from '@apollo/client/core'
import { useApolloClient, useMutation, type UseMutationOptions } from '@vue/apollo-composable'
import { projectHeaderQuery } from '~/graphql/projects'

/**
 * The one way to change something inside a project (RF4,
 * docs/refactoring-m1-m3.md). After the change, what is shown of the project
 * is loaded again: its content and its header with planerRevision, which the
 * polling (S3f) compares with.
 */
export function useProjectMutation<TResult, TVariables extends OperationVariables>(
  document: TypedDocumentNode<TResult, TVariables>,
  options: Omit<UseMutationOptions<TResult, TVariables>, 'refetchQueries' | 'awaitRefetchQueries'> = {}
) {
  const mutation = useMutation(document, { ...options, refetchQueries: 'active', awaitRefetchQueries: true })
  const revision = useProjectRevision()

  /** The own change moves the known revision on, so the polling does not report it. */
  const mutate: typeof mutation.mutate = async (...args) => {
    const before = revision.beforeOwnChange()

    try {
      const result = await mutation.mutate(...args)
      revision.afterOwnChange(before)

      return result
    } finally {
      revision.ownChangeFinished()
    }
  }

  return { ...mutation, mutate }
}

/**
 * Loads the project again without a mutation: the content on screen and the
 * header of the project (with planerRevision).
 */
export function useProjectRefresh() {
  const { resolveClient } = useApolloClient()

  return async function refreshProject(projectId: string): Promise<void> {
    const client = resolveClient()

    await Promise.all([
      client.refetchQueries({ include: 'active' }),
      client.query({ query: projectHeaderQuery, variables: { id: projectId }, fetchPolicy: 'network-only' })
    ])
  }
}
