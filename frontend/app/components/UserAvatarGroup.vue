<script setup lang="ts">
/** Row of overlapping avatars; more than `max` people end in a "+n". */
const props = withDefaults(defineProps<{
  people: ReadonlyArray<{ id: string, name: string, avatarUrl?: string | null }>
  max?: number
  size?: '2xs' | 'xs' | 'sm'
}>(), {
  max: 5,
  size: '2xs'
})

const shown = computed(() => props.people.slice(0, props.max))
const rest = computed(() => Math.max(0, props.people.length - props.max))
</script>

<template>
  <span class="avatar-group">
    <UserAvatar
      v-for="person in shown"
      :key="person.id"
      :name="person.name"
      :src="person.avatarUrl"
      :size="size"
    />
    <span
      v-if="rest"
      class="avatar-group-rest"
    >+{{ rest }}</span>
  </span>
</template>

<style scoped>
.avatar-group {
  display: inline-flex;
  align-items: center;
}

.avatar-group > :not(:first-child) {
  margin-inline-start: calc(var(--wa-space-2xs) * -1);
}

.avatar-group > * {
  box-shadow: 0 0 0 2px var(--wa-color-surface-default);
  border-radius: 50%;
}

.avatar-group-rest {
  padding-inline: var(--wa-space-2xs);
  background-color: var(--wa-color-neutral-fill-normal);
  font-size: var(--wa-font-size-xs);
  line-height: 1.5rem;
}
</style>
