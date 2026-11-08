<script setup lang="ts">
import { isEmptyDocument, richTextExtensions } from '~/utils/rich-text'

/**
 * Shows rich text read-only. TipTap renders the JSON into the page (no
 * v-html), so only the allowed elements can appear.
 */
const props = defineProps<{ content?: Record<string, unknown> | null }>()

const extensions = richTextExtensions()
const empty = computed(() => isEmptyDocument(props.content))
</script>

<template>
  <UEditor
    v-if="!empty"
    :model-value="content ?? undefined"
    content-type="json"
    :editable="false"
    :starter-kit="false"
    :image="false"
    :mention="false"
    :extensions="extensions"
    :ui="{ content: 'p-0' }"
  />
</template>
