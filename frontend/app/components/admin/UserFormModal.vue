<script setup lang="ts">
import { useMutation } from '@vue/apollo-composable'
import { createUserMutation, updateUserMutation } from '~/graphql/admin'

/**
 * Invite a new person (they get a mail to set their password, valid 7
 * days) or change name, e-mail and admin role of an existing one.
 */
const props = defineProps<{
  /** null: closed; {}: new person; { user }: change this person. */
  target: { user?: { id: string, name: string, email: string, isAdmin: boolean } } | null
}>()

const emit = defineEmits<{ close: [] }>()

const { t } = useI18n()
const toast = useToast()
const { viewer } = useViewer()
const { errors, pending, run } = useGraphqlForm()

const open = computed({
  get: () => props.target !== null,
  set: (value: boolean) => {
    if (!value) {
      emit('close')
    }
  }
})

const state = reactive({ name: '', email: '', isAdmin: false })

watch(() => props.target, (target) => {
  errors.value = {}
  Object.assign(state, { name: target?.user?.name ?? '', email: target?.user?.email ?? '', isAdmin: target?.user?.isAdmin ?? false })
}, { immediate: true })

const isNew = computed(() => !props.target?.user)
/** The backend refuses removing one's own admin role; do not offer it. */
const isSelf = computed(() => props.target?.user?.id === viewer.value?.id)

const options = { refetchQueries: 'active' as const, awaitRefetchQueries: true }
const { mutate: createUser } = useMutation(createUserMutation, options)
const { mutate: updateUser } = useMutation(updateUserMutation, options)

async function submit(): Promise<void> {
  const input = { name: state.name.trim(), email: state.email.trim(), isAdmin: state.isAdmin }
  const user = props.target?.user
  const result = await run<unknown>(() => user ? updateUser({ id: user.id, input }) : createUser({ input }))

  if (result) {
    toast.add({
      title: isNew.value ? t('admin.users.invited', { email: input.email }) : t('admin.users.saved'),
      color: 'success'
    })
    emit('close')
  }
}
</script>

<template>
  <UModal
    v-model:open="open"
    :title="isNew ? t('admin.users.invite') : t('admin.users.edit')"
    :description="isNew ? t('admin.users.inviteHint') : undefined"
  >
    <template #body>
      <UForm
        id="user-form"
        :state="state"
        class="space-y-4"
        @submit="submit"
      >
        <UFormField
          :label="t('projects.fields.name')"
          name="name"
          :error="errors.name"
          required
        >
          <UInput
            v-model="state.name"
            class="w-full"
            autofocus
            required
          />
        </UFormField>
        <UFormField
          :label="t('auth.email')"
          name="email"
          :error="errors.email"
          required
        >
          <UInput
            v-model="state.email"
            type="email"
            class="w-full"
            required
          />
        </UFormField>
        <UCheckbox
          v-model="state.isAdmin"
          :label="t('admin.users.isAdmin')"
          :description="isSelf ? t('admin.users.ownAdmin') : t('admin.users.isAdminHint')"
          :disabled="isSelf"
        />
      </UForm>
    </template>
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton
          :label="t('common.cancel')"
          color="neutral"
          variant="ghost"
          @click="open = false"
        />
        <UButton
          type="submit"
          form="user-form"
          :label="isNew ? t('admin.users.sendInvite') : t('common.save')"
          :loading="pending"
        />
      </div>
    </template>
  </UModal>
</template>
