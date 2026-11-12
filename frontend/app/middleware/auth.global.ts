/**
 * Every page except the public ones (definePageMeta({ public: true }))
 * needs a logged-in, strongly authenticated session. The check asks the
 * API once; afterwards the viewer is known.
 */
export default defineNuxtRouteMiddleware(async (to) => {
  if (to.meta.public) {
    return
  }

  const { viewer, load } = useViewer()

  if (viewer.value) {
    return
  }

  const problem = await load()

  if (problem === null) {
    return
  }

  if (problem === 'setup-required') {
    return to.path === '/setup-security' ? undefined : navigateTo('/setup-security')
  }

  const pendingPath = pendingAccountPath(problem)

  if (pendingPath) {
    return navigateTo(pendingPath)
  }

  if (problem === 'network') {
    return abortNavigation(createError({ statusCode: 503, statusMessage: 'Backend not reachable' }))
  }

  return navigateTo({ path: '/login', query: { redirect: to.fullPath } })
})
