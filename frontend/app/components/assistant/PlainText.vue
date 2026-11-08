<script setup lang="ts">
import { plainTextBlocks } from '~/utils/plan'

/** Plain-text description of a draft item: paragraphs and "- " lists. */
const props = defineProps<{ text?: string | null }>()

const blocks = computed(() => plainTextBlocks(props.text))
</script>

<template>
  <div
    v-if="blocks.length"
    class="space-y-1 text-sm text-muted"
  >
    <template
      v-for="(block, index) in blocks"
      :key="index"
    >
      <ul
        v-if="block.kind === 'list'"
        class="list-disc ps-5"
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
