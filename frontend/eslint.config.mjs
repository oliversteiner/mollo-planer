// @ts-check
import withNuxt from './.nuxt/eslint.config.mjs'
import betterTailwindcss from 'eslint-plugin-better-tailwindcss'
import { getDefaultAttributes } from 'eslint-plugin-better-tailwindcss/api/defaults'

export default withNuxt(
  {
    // Written by GraphQL Code Generator ("npm run codegen"), not by hand.
    ignores: ['app/graphql/generated/**']
  },
  betterTailwindcss.configs['correctness-error'],
  {
    settings: {
      'better-tailwindcss': {
        entryPoint: 'app/assets/css/main.css',
        attributes: [
          ...getDefaultAttributes(),
          ['^v-bind:ui$', [{ match: 'objectValues' }]]
        ]
      }
    }
  },
  {
    rules: {
      // RichText is TipTap JSON and is shown with TipTap only, never as HTML.
      'vue/no-v-html': 'error',
      // Web Awesome elements (wa-*) are custom elements and need a closing tag,
      // so for components both forms are allowed.
      'vue/html-self-closing': ['warn', {
        html: { void: 'never', normal: 'always', component: 'any' },
        svg: 'always',
        math: 'always'
      }]
    }
  }
)
