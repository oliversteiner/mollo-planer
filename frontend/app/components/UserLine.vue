<script setup lang="ts">
/** Avatar with the name and, below it, an optional line (for example the role). */
withDefaults(defineProps<{
  name: string
  src?: string | null
  /** Second line under the name. */
  description?: string
  size?: '2xs' | 'xs' | 'sm' | 'md'
}>(), {
  src: null,
  description: undefined,
  size: 'sm'
})
</script>

<template>
  <span class="user-line">
    <UserAvatar
      :name="name"
      :src="src"
      :size="size"
    />
    <span class="user-line-text">
      <span class="user-line-name">{{ name }}</span>
      <span
        v-if="description"
        class="user-line-description"
      >{{ description }}</span>
    </span>
  </span>
</template>

<style scoped>
.user-line {
  display: inline-flex;
  align-items: center;
  gap: var(--wa-space-xs);
  min-inline-size: 0;
}

.user-line-text {
  display: flex;
  flex-direction: column;
  min-inline-size: 0;
}

.user-line-name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.user-line-description {
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}
</style>
