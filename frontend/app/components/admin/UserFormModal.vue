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
  <AppDialog
    v-model:open="open"
    :label="isNew ? t('admin.users.invite') : t('admin.users.edit')"
    :description="isNew ? t('admin.users.inviteHint') : undefined"
  >
    <form
      id="user-form"
      class="wa-stack wa-gap-m"
      @submit.prevent="submit"
    >
      <FormInput
        v-model="state.name"
        name="name"
        :label="t('projects.fields.name')"
        :error="errors.name"
        autofocus
        required
      />
      <FormInput
        v-model="state.email"
        type="email"
        name="email"
        :label="t('auth.email')"
        :error="errors.email"
        required
      />
      <FormCheckbox
        v-model="state.isAdmin"
        :label="t('admin.users.isAdmin')"
        :hint="isSelf ? t('admin.users.ownAdmin') : t('admin.users.isAdminHint')"
        :disabled="isSelf"
      />
    </form>

    <template #footer>
      <wa-button
        appearance="plain"
        variant="neutral"
        @click="open = false"
      >
        {{ t('common.cancel') }}
      </wa-button>
      <wa-button
        type="submit"
        form="user-form"
        variant="brand"
        :loading="pending"
      >
        {{ isNew ? t('admin.users.sendInvite') : t('common.save') }}
      </wa-button>
    </template>
  </AppDialog>
</template>
