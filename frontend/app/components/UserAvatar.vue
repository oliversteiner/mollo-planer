<script setup lang="ts">
import { initials } from '~/utils/names'

/**
 * Avatar of a person: the picture, otherwise the initials, otherwise a
 * person icon. The name is the accessible name.
 */
withDefaults(defineProps<{
  name?: string | null
  /** Picture address; null or empty: initials. */
  src?: string | null
  size?: '2xs' | 'xs' | 'sm' | 'md' | '3xl'
}>(), {
  name: null,
  src: null,
  size: 'sm'
})

/** Edge length per size. */
const sizes = { '2xs': '1.25rem', 'xs': '1.5rem', 'sm': '2rem', 'md': '2.5rem', '3xl': '4rem' } as const
</script>

<template>
  <wa-avatar
    :image="src || undefined"
    :initials="initials(name) || undefined"
    :label="name ?? undefined"
    :style="{ '--size': sizes[size] }"
  >
    <wa-icon
      slot="icon"
      name="user"
    ></wa-icon>
  </wa-avatar>
</template>
