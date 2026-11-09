/**
 * Web Awesome Pro: registers the components (Web Components, Shadow DOM),
 * the icon libraries, the German texts of the components and keeps the dark
 * mode class on <html> in sync with the chosen appearance.
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
import '@web.awesome.me/webawesome-pro/dist/components/otp-input/otp-input.js'
import '@web.awesome.me/webawesome-pro/dist/components/page/page.js'
import '@web.awesome.me/webawesome-pro/dist/components/popover/popover.js'
import '@web.awesome.me/webawesome-pro/dist/components/progress-bar/progress-bar.js'
import '@web.awesome.me/webawesome-pro/dist/components/progress-ring/progress-ring.js'
import '@web.awesome.me/webawesome-pro/dist/components/select/select.js'
import '@web.awesome.me/webawesome-pro/dist/components/skeleton/skeleton.js'
import '@web.awesome.me/webawesome-pro/dist/components/spinner/spinner.js'
import '@web.awesome.me/webawesome-pro/dist/components/split-panel/split-panel.js'
import '@web.awesome.me/webawesome-pro/dist/components/step/step.js'
import '@web.awesome.me/webawesome-pro/dist/components/stepper/stepper.js'
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

    // Light or dark: wa-dark / wa-light on <html> (composables/useColorScheme.ts).
    const { dark, systemDark } = useColorScheme()
    watch(dark, (isDark) => {
      const root = document.documentElement
      root.classList.toggle('wa-dark', isDark)
      root.classList.toggle('wa-light', !isDark)
    }, { immediate: true })

    // "System" follows a change of the device setting while the app is open.
    if (typeof window.matchMedia === 'function') {
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (event) => {
        systemDark.value = event.matches
      })
    }
  }
})
