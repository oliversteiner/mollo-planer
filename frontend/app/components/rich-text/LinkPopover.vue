<script setup lang="ts">
import type { Editor } from '@tiptap/vue-3'
import { isAllowedLink } from '~/utils/rich-text'

/** Toolbar button to add, change or remove a link (http, https, mailto). */
const props = defineProps<{ editor: Editor }>()

const { t } = useI18n()
const open = ref(false)
const url = ref('')
const error = ref('')

const active = computed(() => props.editor.isActive('link'))

watch(open, (isOpen) => {
  if (isOpen) {
    url.value = (props.editor.getAttributes('link').href as string | undefined) ?? ''
    error.value = ''
  }
})

/** "fcadb.ch" becomes "https://fcadb.ch", "a@b.ch" becomes a mail link. */
function normalize(value: string): string {
  const trimmed = value.trim()

  if (/^[^\s@/]+@[^\s@/]+\.[^\s@/]+$/.test(trimmed)) {
    return `mailto:${trimmed}`
  }

  return /^[a-z][a-z0-9+.-]*:/i.test(trimmed) ? trimmed : `https://${trimmed}`
}

function apply(): void {
  const href = normalize(url.value)

  if (!isAllowedLink(href)) {
    error.value = t('richText.link.invalid')

    return
  }

  props.editor.chain().focus().extendMarkRange('link').setLink({ href }).run()
  open.value = false
}

function remove(): void {
  props.editor.chain().focus().extendMarkRange('link').unsetLink().run()
  open.value = false
}
</script>

<template>
  <UPopover v-model:open="open">
    <UTooltip :text="t('richText.link.button')">
      <UButton
        icon="i-fadl-link"
        color="neutral"
        :variant="active ? 'soft' : 'ghost'"
        size="sm"
        :aria-label="t('richText.link.button')"
        :disabled="!active && editor.state.selection.empty"
      />
    </UTooltip>

    <template #content>
      <form
        class="flex w-72 flex-col gap-2 p-3"
        @submit.prevent="apply"
      >
        <UFormField
          :label="t('richText.link.url')"
          :error="error"
        >
          <UInput
            v-model="url"
            placeholder="https://"
            class="w-full"
            autofocus
          />
        </UFormField>
        <div class="flex justify-end gap-2">
          <UButton
            v-if="active"
            :label="t('richText.link.remove')"
            icon="i-fadl-link-slash"
            color="neutral"
            variant="ghost"
            size="sm"
            @click="remove"
          />
          <UButton
            type="submit"
            :label="t('richText.link.apply')"
            size="sm"
          />
        </div>
      </form>
    </template>
  </UPopover>
</template>
