/**
 * App-wide reaction to failed API requests. Validation errors are left to
 * the forms that sent them; everything else is handled here once.
 */
export default defineNuxtPlugin({
  name: 'api-errors',
  dependsOn: ['api'],
  setup(nuxtApp) {
    const toast = useToast()
    const route = useRoute()
    const t = (key: string) => (nuxtApp.$i18n as { t: (key: string) => string }).t(key)

    nuxtApp.$apiErrors.on((error) => {
      switch (error.kind) {
        case 'setup-required':
          void navigateTo('/setup-security')
          break
        case 'unauthenticated':
        case 'reauth-required':
          if (route.path !== '/login') {
            void navigateTo({ path: '/login', query: { redirect: route.fullPath } })
          }
          break
        case 'unauthorized':
          toast.add({ title: t('apiError.unauthorized'), color: 'error' })
          break
        case 'csrf':
          toast.add({ title: t('apiError.csrf'), color: 'warning' })
          break
        case 'throttled':
          toast.add({ title: t('apiError.throttled'), color: 'warning' })
          break
        case 'network':
          toast.add({ title: t('apiError.network'), color: 'error' })
          break
        case 'refusal':
          // The backend wrote this sentence for people (extensions.refusal): it says what is missing (Auftrag 39).
          toast.add({ title: error.message ?? t('apiError.unknown'), color: 'error' })
          break
        case 'unknown':
          // Never the wording of the server: what is not a message for people (a malformed request, an unknown variable,
          // null for a required field) is no text for the screen. It goes to the console for whoever looks (Auftrag 55 D).
          console.warn('[api]', error.message)
          toast.add({ title: t('apiError.unknown'), color: 'error' })
          break
      }
    })
  }
})
