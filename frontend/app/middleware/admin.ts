/**
 * Pages of the admin area (definePageMeta({ middleware: 'admin' })). Runs
 * after the global auth middleware, so the viewer is known. The backend
 * checks every admin action again.
 */
export default defineNuxtRouteMiddleware(() => {
  const { can } = useViewer()

  if (!can('USERS_MANAGE')) {
    return navigateTo('/')
  }
})
