<script setup lang="ts">
import { highlightParts } from '~/composables/useRoleFilters'

/** A text with the parts that match the search marked (role page filters). */
const props = defineProps<{
  text: string
  query?: string
}>()

const parts = computed(() => highlightParts(props.text, props.query ?? ''))
</script>

<template>
  <span><template
    v-for="(part, index) in parts"
    :key="index"
  ><mark
    v-if="part.hit"
    class="role-highlight"
  >{{ part.text }}</mark><template v-else>{{ part.text }}</template></template></span>
</template>

<style scoped>
.role-highlight {
  border-radius: var(--wa-border-radius-s);
  background-color: var(--wa-color-warning-fill-quiet);
  color: inherit;
}
</style>
