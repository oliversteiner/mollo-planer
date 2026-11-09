<script setup lang="ts">
/**
 * Message box (wa-callout) with the icon of its meaning, an optional bold
 * title and a text; the default slot takes more content. Texts are shown as
 * text, never as HTML.
 */
withDefaults(defineProps<{
  variant?: 'success' | 'danger' | 'warning' | 'brand' | 'neutral'
  title?: string
  description?: string
}>(), {
  variant: 'brand',
  title: undefined,
  description: undefined
})

const icons = {
  success: 'circle-check',
  danger: 'circle-exclamation',
  warning: 'triangle-exclamation',
  brand: 'circle-info',
  neutral: 'circle-info'
} as const
</script>

<template>
  <wa-callout :variant="variant">
    <wa-icon
      slot="icon"
      :name="icons[variant]"
    ></wa-icon>
    <strong v-if="title">{{ title }}</strong>
    <div v-if="description">
      {{ description }}
    </div>
    <slot />
  </wa-callout>
</template>
