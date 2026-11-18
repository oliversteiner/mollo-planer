import { paletteVariables } from '~/utils/steingarten'

/**
 * Puts the colours of "Steingarten" on <html> as `--zen-*` (inline style) while a zen view is on, and takes them off
 * in the normal view (docs/zen-modus.md §4.6). The cookies are read before the first render, so a reload in calm shows
 * the saved mood at once. zen.css maps the variables to the `--wa-*` tokens.
 */
export default defineNuxtPlugin({
  name: 'zen-colors',
  dependsOn: ['view-mode'],
  setup() {
    const { mode } = useViewMode()
    const { colours } = useZenMood()

    watchEffect(() => {
      const style = document.documentElement.style
      const variables = paletteVariables(colours.value)

      for (const [name, value] of Object.entries(variables)) {
        if (mode.value === 'full') {
          style.removeProperty(name)
        } else {
          style.setProperty(name, value)
        }
      }
    })
  }
})
