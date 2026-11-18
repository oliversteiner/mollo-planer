import { useQuery } from '@vue/apollo-composable'
import type { Right } from '~/graphql/generated/graphql'
import { projectHeaderQuery } from '~/graphql/projects'

/**
 * The project of the current route (/projects/:id/...) with the viewer's
 * rights. Buttons are shown only for what `can()` allows; the backend
 * checks every change again.
 */
export function useProject() {
  const route = useRoute()
  const projectId = computed(() => String(route.params.id))

  const { result, loading } = useQuery(projectHeaderQuery, () => ({ id: projectId.value }))
  const project = computed(() => result.value?.project ?? null)

  function can(right: Right): boolean {
    return project.value?.viewerRights.includes(right) ?? false
  }

  return { projectId, project, loading, loaded: computed(() => result.value !== undefined), can }
}
