<script setup lang="ts">
import { createDocumentMutation } from '~/graphql/documents'
import type { DocumentAccess, DocumentKind } from '~/graphql/generated/graphql'

const KINDS: DocumentKind[] = ['PROCEDURE', 'MINUTES', 'ANALYSIS', 'CONCEPT', 'OTHER']
const ACCESS: DocumentAccess[] = ['READ', 'EDIT', 'NONE']

/** An empty document: the server stores it as it is; the editor opens right after. */
const EMPTY = { type: 'doc', content: [{ type: 'paragraph' }] }

/**
 * Creates a document: title, kind and what the members of the project may do with it (default: read). The person
 * becomes the owner. Afterwards the document opens in edit mode.
 */
const props = defineProps<{ open: boolean, projectId: string }>()
const emit = defineEmits<{ close: [] }>()

const { t } = useI18n()
const toast = useToast()
const { errors, pending, run } = useGraphqlForm()
const { mutate: createDocument } = useProjectMutation(createDocumentMutation)

const isOpen = computed({
  get: () => props.open,
  set: (value: boolean) => {
    if (!value) {
      emit('close')
    }
  }
})

const state = reactive<{ title: string, kind: DocumentKind, access: DocumentAccess }>({ title: '', kind: 'OTHER', access: 'READ' })

watch(() => props.open, (open) => {
  if (open) {
    errors.value = {}
    Object.assign(state, { title: '', kind: 'OTHER', access: 'READ' })
  }
})

const kindItems = computed(() => KINDS.map(kind => ({ label: t(`documents.kinds.${kind}`), value: kind })))
const accessItems = computed(() => ACCESS.map(access => ({ label: t(`documents.access.${access}`), value: access })))

async function submit(): Promise<void> {
  const result = await run(() => createDocument({
    input: { projectId: props.projectId, title: state.title.trim(), kind: state.kind, contentJson: EMPTY, projectAccess: state.access }
  }))
  const id = result?.data?.createDocument.id

  if (id) {
    toast.add({ title: t('documents.created'), color: 'success' })
    emit('close')
    await navigateTo({ path: `/projects/${props.projectId}/documents/${id}`, query: { edit: '1' } })
  }
}
</script>

<template>
  <AppDialog
    v-model:open="isOpen"
    :label="t('documents.createTitle')"
  >
    <form
      id="document-create-form"
      class="wa-stack wa-gap-m"
      @submit.prevent="submit"
    >
      <FormInput
        v-model="state.title"
        name="title"
        :label="t('documents.fieldTitle')"
        :error="errors['input.title'] ?? errors.title"
        maxlength="200"
        autofocus
        required
      />
      <FormSelect
        :model-value="state.kind"
        :items="kindItems"
        :label="t('documents.fieldKind')"
        :error="errors['input.kind'] ?? errors.kind"
        @update:model-value="state.kind = $event as DocumentKind"
      />
      <FormSelect
        :model-value="state.access"
        :items="accessItems"
        :label="t('documents.fieldAccess')"
        :hint="t('documents.accessHint')"
        :error="errors['input.projectAccess'] ?? errors.projectAccess"
        @update:model-value="state.access = $event as DocumentAccess"
      />
    </form>

    <template #footer>
      <wa-button
        appearance="plain"
        variant="neutral"
        @click="isOpen = false"
      >
        {{ t('common.cancel') }}
      </wa-button>
      <wa-button
        type="submit"
        form="document-create-form"
        variant="brand"
        :loading="pending"
      >
        {{ t('documents.createSubmit') }}
      </wa-button>
    </template>
  </AppDialog>
</template>
