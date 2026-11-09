<script setup lang="ts">
/**
 * Icon chosen for a milestone, todo or task (stored as a Font Awesome name,
 * for example "rocket"). A <wa-icon> of the library `default` (Duotone Light
 * from our server, see utils/wa-icons.ts): the colour of the text, the second
 * layer 40 % opaque. The two layers can be coloured apart with
 * --primary-color and --secondary-color on this element.
 */
const props = withDefaults(defineProps<{
  /** Icon name from the API, null for none. */
  name?: string | null
  /** Shown when there is no (valid or existing) name, for example per level. */
  fallback: string
  /** Accessible name; without it the icon is decorative. */
  label?: string
}>(), {
  name: null,
  label: undefined
})

/** The chosen icon was not found on the server (stale or made-up name). */
const missing = ref(false)
watch(() => props.name, () => {
  missing.value = false
})

const icon = computed(() =>
  props.name && iconNameFormat.test(props.name) && !missing.value ? props.name : props.fallback
)
</script>

<template>
  <wa-icon
    :name="icon"
    :label="label"
    canvas="auto"
    @wa-error="missing = true"
  ></wa-icon>
</template>
