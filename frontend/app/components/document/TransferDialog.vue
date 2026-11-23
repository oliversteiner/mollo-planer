<script setup lang="ts">
import { useQuery } from '@vue/apollo-composable'
import type { PersonGroup } from '~/components/form/PersonSelect.vue'
import { transferDocumentMutation } from '~/graphql/documents'
import { projectMembersQuery } from '~/graphql/task'

/**
 * Hands a document to another member of the project (transferDocument). Only members can be chosen; the current
 * owner is not offered. The server checks the right.
 */
const props = defineProps<{ open: boolean, projectId: string, documentId: string, ownerId: string | null }>()
const emit = defineEmits<{ close: [] }>()

const { t } = useI18n()
const toast = useToast()
const { errors, pending, run } = useGraphqlForm()
const { mutate: transfer } = useProjectMutation(transferDocumentMutation)
const { result: members } = useQuery(projectMembersQuery, () => ({ id: props.projectId }), () => ({ enabled: props.open }))

const isOpen = computed({
  get: () => props.open,
  set: (value: boolean) => {
    if (!value) {
      emit('close')
    }
  }
})

const newOwner = ref<string | null>(null)

watch(() => props.open, (open) => {
  if (open) {
    errors.value = {}
    newOwner.value = null
  }
})

const groups = computed<PersonGroup[]>(() => [{
  people: (members.value?.project?.members ?? [])
    .map(member => member.user)
    .filter(user => user.id !== props.ownerId)
    .sort((a, b) => a.name.localeCompare(b.name))
}])

async function submit(): Promise<void> {
  if (!newOwner.value) {
    return
  }
  const result = await run(() => transfer({ id: props.documentId, ownerId: newOwner.value! }))

  if (result?.data) {
    toast.add({ title: t('documents.transferred'), color: 'success' })
    emit('close')
  }
}
</script>

<template>
  <AppDialog
    v-model:open="isOpen"
    :label="t('documents.transferTitle')"
    :description="t('documents.transferText')"
  >
    <form
      id="document-transfer-form"
      @submit.prevent="submit"
    >
      <FormPersonSelect
        v-model="newOwner"
        :groups="groups"
        :label="t('documents.transferOwner')"
        :hint="t('documents.sharing.membersOnly')"
        :error="errors.ownerId"
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
        form="document-transfer-form"
        variant="brand"
        :disabled="!newOwner"
        :loading="pending"
      >
        {{ t('documents.transferConfirm') }}
      </wa-button>
    </template>
  </AppDialog>
</template>
