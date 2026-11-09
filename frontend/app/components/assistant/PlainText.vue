<script setup lang="ts">
import { plainTextBlocks } from '~/utils/plan'

/** Plain-text description of a draft item: paragraphs and "- " lists. */
const props = defineProps<{ text?: string | null }>()

const blocks = computed(() => plainTextBlocks(props.text))
</script>

<template>
  <div
    v-if="blocks.length"
    class="plain-text wa-stack wa-gap-3xs"
  >
    <template
      v-for="(block, index) in blocks"
      :key="index"
    >
      <ul
        v-if="block.kind === 'list'"
        class="plain-text-list"
      >
        <li
          v-for="(item, itemIndex) in block.items"
          :key="itemIndex"
        >
          {{ item }}
        </li>
      </ul>
      <p v-else>
        {{ block.text }}
      </p>
    </template>
  </div>
</template>

<style scoped>
.plain-text {
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}

.plain-text p {
  margin: 0;
}

.plain-text-list {
  margin: 0;
  padding-inline-start: var(--wa-space-l);
  list-style: disc;
}
</style>
