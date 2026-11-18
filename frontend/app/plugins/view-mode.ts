/**
 * Puts the view on <html> as `data-view="full|calm|focus"` (docs/zen-modus.md §4.1). The app is a pure SPA, so the
 * cookie is read before the first render: reloading in "calm" shows the calm frame at once, nothing flashes.
 */
export default defineNuxtPlugin({
  name: 'view-mode',
  setup() {
    const { mode } = useViewMode()

    watchEffect(() => {
      document.documentElement.dataset.view = mode.value
    })
  }
})
