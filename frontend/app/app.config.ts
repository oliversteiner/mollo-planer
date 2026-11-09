import { uiIcons } from './ui-icons'

export default defineAppConfig({
  ui: {
    // Template colours for now; replace here and in assets/css/main.css.
    colors: {
      primary: 'green',
      neutral: 'slate'
    },
    icons: uiIcons,
    // The sidebar belongs to <wa-page> now; its own hamburger button does it.
    // Until the pages leave Nuxt UI (step 5) the navbar's sidebar toggle is hidden.
    dashboardNavbar: {
      slots: {
        toggle: 'hidden'
      }
    }
  }
})
