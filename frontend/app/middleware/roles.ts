/**
 * The settings of roles and functions (definePageMeta({ middleware: 'roles' })).
 * Runs after the global auth middleware, so the viewer is known. The backend
 * checks every change again.
 */
export default defineNuxtRouteMiddleware(() => {
  const { can } = useViewer()

  if (!can('ROLES_MANAGE')) {
    return navigateTo('/')
  }
})
