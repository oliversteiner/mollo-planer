<script setup lang="ts">
import { useMutation } from '@vue/apollo-composable'
import { createAgentMutation } from '~/graphql/admin'

/**
 * Create an agent account (docs/verwaltung-agenten-anfasser.md §3.4): name and address, no mail is sent. Needs a
 * recent password confirmation, asked for in a field of this dialog (Auftrag 55, rule R11). After creating, the
 * dialog closes and opens nothing else: the new row of the list offers «Zugang einrichten».
 */
const props = defineProps<{ open: boolean }>()

const emit = defineEmits<{
  close: []
  created: [id: string]
}>()

const { t } = useI18n()
const toast = useToast()
const step = usePasswordStep()
const { errors, run } = useGraphqlForm()
const { mutate: createAgent } = useMutation(createAgentMutation, { refetchQueries: 'active', awaitRefetchQueries: true })

const state = reactive({ name: '', email: '' })

watch(() => props.open, (open) => {
  if (open) {
    step.cancel()
    errors.value = {}
    Object.assign(state, { name: '', email: '' })
  }
})

/**
 * wa-dialog closes itself when told to (Escape, the X): while the agent is being made it must not. When the page itself
 * closes it (`open` is false then, after the agent was made), it goes.
 */
function keepOpenWhileBusy(event: Event): void {
  if (props.open && (step.working || step.checking) && event.target === event.currentTarget) {
    event.preventDefault()
  }
}

/** Closing is the person's wish, but not while the agent is being made. */
function onOpenChange(open: boolean): void {
  if (!open && !step.working && !step.checking) {
    step.cancel()
    emit('close')
  }
}

/** One agent per click, from the click to the answer (password field included). */
async function submit(): Promise<void> {
  if (step.working) {
    return
  }

  await step.run(async () => {
    const name = state.name.trim()
    const result = await run(() => createAgent({ input: { name, email: state.email.trim() } }))

    // The confirmation ran out meanwhile: the step asks again and keeps what is typed here.
    if (errors.value.password) {
      return NEEDS_PASSWORD
    }
    const agent = result?.data?.createAgent

    if (agent) {
      toast.add({ title: t('admin.users.newAgent.created', { name: agent.name }), color: 'success' })
      emit('created', agent.id)
    }
  })
}
</script>

<template>
  <AppDialog
    :open="open"
    :label="t('admin.users.newAgent.title')"
    :description="t('admin.users.newAgent.hint')"
    @wa-hide="keepOpenWhileBusy"
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
        :error="errors.email"
        required
      />
    </form>
    <PasswordConfirmField
      class="agent-password"
      :step="step"
    />

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
        :loading="step.working"
        :disabled="step.waiting"
      >
        {{ t('admin.users.newAgent.submit') }}
      </wa-button>
    </template>
  </AppDialog>
</template>

<style scoped>
.agent-password {
  margin-block-start: var(--wa-space-m);
}
</style>
