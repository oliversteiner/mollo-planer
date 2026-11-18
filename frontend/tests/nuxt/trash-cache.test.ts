import { InMemoryCache } from '@apollo/client/core'
import { describe, expect, it } from 'vitest'
import { trashQuery } from '~/graphql/trash'
import { trashTypePolicies } from '~/utils/trash'

describe('trash entries in the Apollo cache', () => {
  function entry(deletionId: string, type: 'TASK' | 'COMMENT', id: string, title: string) {
    return {
      __typename: 'TrashItem' as const,
      deletionId,
      type,
      id,
      title,
      deletedAt: '2026-11-17 17:13:14',
      deletedBy: null,
      project: null,
      childCount: 0,
      childCounts: [],
      viewerCanRestore: true,
      viewerCanPurge: true
    }
  }

  it('keeps a task and a comment with the same id apart', () => {
    const cache = new InMemoryCache({ typePolicies: trashTypePolicies })
    const trash = [entry('d-1', 'TASK', '1', 'Farben festlegen'), entry('d-2', 'COMMENT', '1', 'Bitte die Icons')]

    cache.writeQuery({ query: trashQuery, variables: { projectId: '1' }, data: { trash } })

    expect(cache.readQuery({ query: trashQuery, variables: { projectId: '1' } })?.trash.map(item => item.title)).toEqual(['Farben festlegen', 'Bitte die Icons'])
  })
})
