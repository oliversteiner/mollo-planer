/**
 * Opens a page only for people with its right (utils/route-rights.ts). Runs
 * after auth.global.ts, so the viewer is known. The backend checks every change again.
 */
export default defineNuxtRouteMiddleware((to) => {
  const right = routeRight(to.path)
  if (right === null) {
    return
  }

  const { can } = useViewer()
  if (!can(right)) {
    return navigateTo('/')
  }
})
