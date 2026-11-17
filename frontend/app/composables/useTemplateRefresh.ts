import type { ApolloCache } from '@apollo/client/core'
import { templatesQuery } from '~/graphql/labels'

/**
 * What every change of a template does afterwards: read the list anew and
 * forget the cached templates, so the choice of a template elsewhere (new
 * project, labels of a project) shows the change.
 */
export const templateRefresh = {
  refetchQueries: [{ query: templatesQuery }],
  awaitRefetchQueries: true,
  update(cache: ApolloCache<unknown>): void {
    cache.evict({ id: 'ROOT_QUERY', fieldName: 'templates' })
    cache.evict({ id: 'ROOT_QUERY', fieldName: 'template' })
    cache.gc()
  }
}
