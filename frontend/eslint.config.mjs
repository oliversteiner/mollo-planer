// @ts-check
import withNuxt from './.nuxt/eslint.config.mjs'

export default withNuxt(
  {
    // Written by GraphQL Code Generator ("npm run codegen"), not by hand.
    ignores: ['app/graphql/generated/**']
  },
  {
    rules: {
      // wa-* elements use the native slot attribute (slot="icon").
      'vue/no-deprecated-slot-attribute': 'off',
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
