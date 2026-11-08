<script setup lang="ts">
/**
 * Icon chosen for a milestone, todo or task (stored as a Font Awesome name,
 * for example "rocket"). The SVG is loaded on demand from /icons and drawn
 * as a CSS mask in the current text colour, so the duotone look stays:
 * the secondary layer is 40 % opaque in the SVG itself.
 */
const props = withDefaults(defineProps<{
  /** Icon name from the API, null for none. */
  name?: string | null
  /** Shown when there is no (valid) name, for example per level. */
  fallback: string
  /** Accessible name; without it the icon is decorative. */
  label?: string
}>(), {
  name: null,
  label: undefined
})

/** Same format as the server rule FontAwesomeIconRule. */
const validName = /^[a-z0-9]+(?:-[a-z0-9]+)*$/

const baseURL = useRuntimeConfig().app.baseURL

const style = computed(() => {
  const icon = props.name && validName.test(props.name) ? props.name : props.fallback
  const url = `url("${baseURL}icons/duotone-light/${icon}.svg")`

  return { maskImage: url, WebkitMaskImage: url }
})
</script>

<template>
  <span
    class="inline-block size-[1em] shrink-0 bg-current mask-contain mask-center mask-no-repeat"
    :style="style"
    :role="label ? 'img' : undefined"
    :aria-label="label"
    :aria-hidden="label ? undefined : 'true'"
  />
</template>
