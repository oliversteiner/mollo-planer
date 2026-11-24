<script setup lang="ts">
import { useMutation } from '@vue/apollo-composable'
import { createAgentMutation } from '~/graphql/admin'

/**
 * Create an agent account (docs/verwaltung-agenten-anfasser.md §3.4): name and address, no mail is sent. Needs a
 * recent password confirmation; this dialog steps aside while the prompt for it is open. After creating, the page
 * opens the access dialog of the new agent.
 */
const props = defineProps<{ open: boolean }>()

const emit = defineEmits<{
  close: []
  created: [id: string]
}>()

const { t } = useI18n()
const toast = useToast()
const { ensurePasswordConfirmed } = useSecurity()
const { errors, pending, run } = useGraphqlForm()
const { mutate: createAgent } = useMutation(createAgentMutation, { refetchQueries: 'active', awaitRefetchQueries: true })

const state = reactive({ name: '', email: '' })
const suspended = ref(false)

watch(() => props.open, (open) => {
  if (open) {
    errors.value = {}
    Object.assign(state, { name: '', email: '' })
  }
})

function onOpenChange(open: boolean): void {
  if (!open && !suspended.value) {
    emit('close')
  }
}

async function submit(): Promise<void> {
  suspended.value = true
  const confirmed = await ensurePasswordConfirmed().finally(() => {
    suspended.value = false
  })

  if (!confirmed) {
    return
  }
  const name = state.name.trim()
  const result = await run(() => createAgent({ input: { name, email: state.email.trim() } }))
  const agent = result?.data?.createAgent

  if (agent) {
    toast.add({ title: t('admin.users.newAgent.created', { name: agent.name }), color: 'success' })
    emit('created', agent.id)
  }
}
</script>

<template>
  <AppDialog
    :open="open && !suspended"
    :label="t('admin.users.newAgent.title')"
    :description="t('admin.users.newAgent.hint')"
    @update:open="onOpenChange"
  >
    <form
      id="agent-form"
      class="wa-stack wa-gap-m"
      @submit.prevent="submit"
    >
      <FormInput
        v-model="state.name"
        name="name"
        :label="t('projects.fields.name')"
        :hint="t('admin.users.newAgent.nameHint')"
        :error="errors.name"
        maxlength="120"
        autofocus
        required
      />
      <FormInput
        v-model="state.email"
        type="email"
        name="email"
        :label="t('auth.email')"
        :hint="t('admin.users.newAgent.emailHint')"
        :error="errors.email ?? errors.password"
        required
      />
    </form>

    <template #footer>
      <wa-button
        appearance="plain"
        variant="neutral"
        @click="emit('close')"
      >
        {{ t('common.cancel') }}
      </wa-button>
      <wa-button
        type="submit"
        form="agent-form"
        variant="brand"
        :loading="pending"
      >
        {{ t('admin.users.newAgent.submit') }}
      </wa-button>
    </template>
  </AppDialog>
</template>
