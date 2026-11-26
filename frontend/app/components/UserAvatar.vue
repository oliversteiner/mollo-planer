<script setup lang="ts">
import { initials } from '~/utils/names'

/**
 * Avatar of a person: the picture, otherwise the initials, otherwise a
 * person icon. The name is the accessible name.
 *
 * Agent accounts (`isAgent`, variant C of docs/ux-agenten.md 21): from 2 rem the initials as usual with a small robot
 * mark at the bottom right; below 2 rem only the robot icon (initials and mark are unreadable there). Symbol and mark
 * are decorative; the accessible name is "<name>, Agent".
 */
const props = withDefaults(defineProps<{
  name?: string | null
  /** Picture address; null or empty: initials. */
  src?: string | null
  size?: '2xs' | 'xs' | 'sm' | 'md' | '3xl'
  isAgent?: boolean | null
}>(), {
  name: null,
  src: null,
  size: 'sm',
  isAgent: false
})

const { t } = useI18n()

/** Edge length per size. */
const sizes = { '2xs': '1.25rem', 'xs': '1.5rem', 'sm': '2rem', 'md': '2.5rem', '3xl': '4rem' } as const

/** Below 2 rem the mark would be a smudge: there the robot icon replaces the initials. */
const small = computed(() => props.size === '2xs' || props.size === 'xs')
const label = computed(() => (props.isAgent && props.name ? t('agents.avatarLabel', { name: props.name }) : props.name ?? undefined))
</script>

<template>
  <span
    v-if="isAgent"
    class="user-avatar-agent"
    :style="{ '--size': sizes[size] }"
  >
    <wa-avatar
      :image="small ? undefined : src || undefined"
      :initials="small ? undefined : initials(name) || undefined"
      :label="label"
      :style="{ '--size': sizes[size] }"
    >
      <wa-icon
        slot="icon"
        :name="small ? 'robot' : 'user'"
      ></wa-icon>
    </wa-avatar>
    <span
      v-if="!small"
      class="user-avatar-agent-mark"
      aria-hidden="true"
    >
      <wa-icon
        name="robot"
        aria-hidden="true"
      ></wa-icon>
    </span>
  </span>
  <wa-avatar
    v-else
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

<style scoped>
.user-avatar-agent {
  position: relative;
  display: inline-flex;
  flex: none;
}

/* Mark: 48 % of the avatar, brand colour with the symbol in the colour of the surface, a rim in the colour behind. */
.user-avatar-agent-mark {
  position: absolute;
  inset-inline-end: -8%;
  inset-block-end: -8%;
  display: grid;
  place-items: center;
  inline-size: calc(var(--size) * 0.48);
  block-size: calc(var(--size) * 0.48);
  border-radius: 50%;
  background-color: var(--wa-color-brand-fill-loud);
  color: var(--wa-color-brand-on-loud);
  box-shadow: 0 0 0 2px var(--wa-color-surface-default);
  font-size: calc(var(--size) * 0.3);
}
</style>
