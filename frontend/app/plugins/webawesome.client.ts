/**
 * Web Awesome Pro: registers the components (Web Components, Shadow DOM),
 * the icon libraries, the German texts of the components and keeps the dark
 * mode class in sync.
 * Only components that the app uses are imported; add new ones here.
 */
import { registerTranslation } from '@web.awesome.me/webawesome-pro/dist/webawesome.js'
import de from '@web.awesome.me/webawesome-pro/dist/translations/de.js'
import en from '@web.awesome.me/webawesome-pro/dist/translations/en.js'

import '@web.awesome.me/webawesome-pro/dist/components/avatar/avatar.js'
import '@web.awesome.me/webawesome-pro/dist/components/badge/badge.js'
import '@web.awesome.me/webawesome-pro/dist/components/button/button.js'
import '@web.awesome.me/webawesome-pro/dist/components/callout/callout.js'
import '@web.awesome.me/webawesome-pro/dist/components/card/card.js'
import '@web.awesome.me/webawesome-pro/dist/components/checkbox/checkbox.js'
import '@web.awesome.me/webawesome-pro/dist/components/combobox/combobox.js'
import '@web.awesome.me/webawesome-pro/dist/components/details/details.js'
import '@web.awesome.me/webawesome-pro/dist/components/dialog/dialog.js'
import '@web.awesome.me/webawesome-pro/dist/components/divider/divider.js'
import '@web.awesome.me/webawesome-pro/dist/components/drawer/drawer.js'
import '@web.awesome.me/webawesome-pro/dist/components/dropdown/dropdown.js'
import '@web.awesome.me/webawesome-pro/dist/components/dropdown-item/dropdown-item.js'
import '@web.awesome.me/webawesome-pro/dist/components/icon/icon.js'
import '@web.awesome.me/webawesome-pro/dist/components/input/input.js'
import '@web.awesome.me/webawesome-pro/dist/components/option/option.js'
import '@web.awesome.me/webawesome-pro/dist/components/page/page.js'
import '@web.awesome.me/webawesome-pro/dist/components/popover/popover.js'
import '@web.awesome.me/webawesome-pro/dist/components/progress-bar/progress-bar.js'
import '@web.awesome.me/webawesome-pro/dist/components/progress-ring/progress-ring.js'
import '@web.awesome.me/webawesome-pro/dist/components/select/select.js'
import '@web.awesome.me/webawesome-pro/dist/components/skeleton/skeleton.js'
import '@web.awesome.me/webawesome-pro/dist/components/spinner/spinner.js'
import '@web.awesome.me/webawesome-pro/dist/components/split-panel/split-panel.js'
import '@web.awesome.me/webawesome-pro/dist/components/switch/switch.js'
import '@web.awesome.me/webawesome-pro/dist/components/tag/tag.js'
import '@web.awesome.me/webawesome-pro/dist/components/textarea/textarea.js'
import '@web.awesome.me/webawesome-pro/dist/components/toast/toast.js'
import '@web.awesome.me/webawesome-pro/dist/components/toast-item/toast-item.js'
import '@web.awesome.me/webawesome-pro/dist/components/tooltip/tooltip.js'

export default defineNuxtPlugin({
  name: 'webawesome',
  setup() {
    // Icons of the app and of the components: Duotone Light from our server.
    registerDuotoneLightIcons(useRuntimeConfig().app.baseURL)

    // The components read the language from <html lang> (set in app.vue).
    registerTranslation(de, en)

    // Nuxt UI (colour mode module) sets "dark" on <html> while both
    // libraries run side by side; Web Awesome needs "wa-dark" / "wa-light".
    // The own colour mode switch replaces this when Nuxt UI is removed.
    const colorMode = useColorMode()
    watch(
      () => colorMode.value,
      (mode) => {
        const root = document.documentElement
        root.classList.toggle('wa-dark', mode === 'dark')
        root.classList.toggle('wa-light', mode !== 'dark')
      },
      { immediate: true }
    )
  }
})
