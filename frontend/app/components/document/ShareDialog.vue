<script setup lang="ts">
import { useQuery } from '@vue/apollo-composable'
import type { PersonGroup } from '~/components/form/PersonSelect.vue'
import { setDocumentSharingMutation } from '~/graphql/documents'
import type { DocumentAccess, DocumentShareAccess } from '~/graphql/generated/graphql'
import { projectMembersQuery } from '~/graphql/task'

interface SharedPerson {
  user: { id: string, name: string, avatarUrl?: string | null, isAgent: boolean }
  access: DocumentShareAccess
}

const ACCESS: DocumentAccess[] = ['NONE', 'READ', 'EDIT']

/**
 * Who may do what with a document (docs/dokumentenverwaltung.md §4.4): the level for all members of the project
 * and single people with their level. Only members of the project can be chosen. The server decides; the
 * dialog only sends what was chosen.
 */
const props = defineProps<{
  open: boolean
  projectId: string
  document: { id: string, projectAccess: DocumentAccess, shares: readonly SharedPerson[] } | null
}>()
const emit = defineEmits<{ close: [] }>()

const { t } = useI18n()
const toast = useToast()
const { errors, pending, run } = useGraphqlForm()
const { mutate: setSharing } = useProjectMutation(setDocumentSharingMutation)

const { result: members } = useQuery(projectMembersQuery, () => ({ id: props.projectId }), () => ({ enabled: props.open }))

const isOpen = computed({
  get: () => props.open,
  set: (value: boolean) => {
    if (!value) {
      emit('close')
    }
  }
})

const projectAccess = ref<DocumentAccess>('READ')
const shares = ref<{ userId: string, name: string, access: DocumentShareAccess }[]>([])
const newPerson = ref<string | null>(null)

watch(() => props.open, (open) => {
  if (open && props.document) {
    errors.value = {}
    projectAccess.value = props.document.projectAccess
    shares.value = props.document.shares.map(share => ({ userId: share.user.id, name: share.user.name, access: share.access }))
    newPerson.value = null
  }
}, { immediate: true })

const accessItems = computed(() => ACCESS.map(access => ({ label: t(`documents.access.${access}`), value: access })))
const shareItems = computed(() => (['READ', 'EDIT'] as const).map(access => ({ label: t(`documents.sharing.${access.toLowerCase()}`), value: access })))

/** Only members of the project that do not have a share yet. */
const groups = computed<PersonGroup[]>(() => [{
  people: (members.value?.project?.members ?? [])
    .map(member => member.user)
    .filter(user => !shares.value.some(share => share.userId === user.id))
    .sort((a, b) => a.name.localeCompare(b.name))
}])

function addPerson(): void {
  const user = (members.value?.project?.members ?? []).map(member => member.user).find(person => person.id === newPerson.value)

  if (user) {
    shares.value.push({ userId: user.id, name: user.name, access: 'READ' })
    newPerson.value = null
  }
}

async function submit(): Promise<void> {
  if (!props.document) {
    return
  }
  const result = await run(() => setSharing({
    id: props.document!.id,
    input: { projectAccess: projectAccess.value, shares: shares.value.map(share => ({ userId: share.userId, access: share.access })) }
  }))

  if (result?.data) {
    toast.add({ title: t('documents.sharing.saved'), color: 'success' })
    emit('close')
  }
}
</script>

<template>
  <AppDialog
    v-model:open="isOpen"
    :label="t('documents.sharing.title')"
  >
    <form
      id="document-share-form"
      class="wa-stack wa-gap-m"
      @submit.prevent="submit"
    >
      <FormSelect
        :model-value="projectAccess"
        :items="accessItems"
        :label="t('documents.sharing.projectAccess')"
        :error="errors['input.projectAccess'] ?? errors.projectAccess"
        @update:model-value="projectAccess = $event as DocumentAccess"
      />

      <div class="wa-stack wa-gap-s">
        <h3 class="wa-heading-s">
          {{ t('documents.sharing.people') }}
        </h3>
        <p
          v-if="!shares.length"
          class="share-quiet"
        >
          {{ t('documents.sharing.none') }}
        </p>
        <ul
          v-else
          class="share-list wa-list-plain"
        >
          <li
            v-for="share in shares"
            :key="share.userId"
            class="share-row"
          >
            <span class="share-name">{{ share.name }}</span>
            <FormSelect
              :model-value="share.access"
              :items="shareItems"
              :label="t('documents.sharing.personAccess')"
              hide-label
              class="share-access"
              @update:model-value="share.access = $event as DocumentShareAccess"
            />
            <wa-button
              appearance="plain"
              variant="neutral"
              size="s"
              :aria-label="t('documents.sharing.remove', { name: share.name })"
              @click="shares = shares.filter(entry => entry.userId !== share.userId)"
            >
              <wa-icon name="xmark"></wa-icon>
            </wa-button>
          </li>
        </ul>
        <div class="share-add">
          <FormPersonSelect
            v-model="newPerson"
            :groups="groups"
            :label="t('documents.sharing.addPerson')"
            :hint="t('documents.sharing.membersOnly')"
            :error="errors['input.shares']"
            class="share-person"
          />
          <wa-button
            appearance="outlined"
            variant="neutral"
            :disabled="!newPerson"
            @click="addPerson"
          >
            <wa-icon
              slot="start"
              name="plus"
            ></wa-icon>
            {{ t('documents.sharing.addPerson') }}
          </wa-button>
        </div>
      </div>
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
        form="document-share-form"
        variant="brand"
        :loading="pending"
      >
        {{ t('common.save') }}
      </wa-button>
    </template>
  </AppDialog>
</template>

<style scoped>
.share-quiet {
  margin: 0;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}

.share-list {
  margin: 0;
  padding: 0;
  border: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  border-radius: var(--wa-border-radius-m);
}

.share-row {
  display: flex;
  align-items: center;
  gap: var(--wa-space-s);
  padding: var(--wa-space-xs) var(--wa-space-s);
}

.share-row + .share-row {
  border-block-start: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
}

.share-name {
  flex: 1;
  min-inline-size: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.share-access {
  inline-size: 9rem;
}

.share-add {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: var(--wa-space-s);
}

.share-person {
  flex: 1;
  min-inline-size: 12rem;
}
</style>
