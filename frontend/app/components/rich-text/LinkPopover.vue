<script setup lang="ts">
import type { Editor } from '@tiptap/vue-3'
import { isAllowedLink } from '~/utils/rich-text'

/** Toolbar button to add, change or remove a link (http, https, mailto). */
const props = defineProps<{ editor: Editor }>()

const { t } = useI18n()
const anchorId = useId()
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

/** Events of the fields inside bubble up; only the popover's own count. */
function onShow(event: Event): void {
  if (event.target === event.currentTarget) {
    open.value = true
  }
}

function onHide(event: Event): void {
  if (event.target === event.currentTarget) {
    open.value = false
  }
}
</script>

<template>
  <wa-button
    :id="anchorId"
    :appearance="active ? 'filled' : 'plain'"
    variant="neutral"
    size="small"
    :title="t('richText.link.button')"
    :aria-label="t('richText.link.button')"
    :aria-pressed="active"
    :disabled="!active && editor.state.selection.empty"
  >
    <wa-icon name="link"></wa-icon>
  </wa-button>

  <wa-popover
    :for="anchorId"
    :open="open"
    placement="bottom-start"
    @wa-show="onShow"
    @wa-hide="onHide"
  >
    <form
      class="link-form"
      @submit.prevent="apply"
    >
      <FormInput
        v-model="url"
        :label="t('richText.link.url')"
        :error="error"
        placeholder="https://"
        autofocus
      />
      <div class="link-actions">
        <wa-button
          v-if="active"
          appearance="plain"
          variant="neutral"
          size="small"
          @click="remove"
        >
          <wa-icon
            slot="start"
            name="link-slash"
          ></wa-icon>
          {{ t('richText.link.remove') }}
        </wa-button>
        <wa-button
          type="submit"
          variant="brand"
          size="small"
        >
          {{ t('richText.link.apply') }}
        </wa-button>
      </div>
    </form>
  </wa-popover>
</template>

<style scoped>
.link-form {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-xs);
  inline-size: 18rem;
}

.link-actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--wa-space-xs);
}
</style>
