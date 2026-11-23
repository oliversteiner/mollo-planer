<script setup lang="ts">
import { useMutation } from '@vue/apollo-composable'
import { createOAuthClientMutation } from '~/graphql/admin'

/**
 * Register an application that may log in through the planer (docs/oauth-sso.md): a name, the exact addresses it
 * returns to (one per line) and whether it is a server with a secret (confidential) or a browser or app client.
 * Needs a recent password confirmation; the secret of a confidential client is shown once by the page.
 */
const props = defineProps<{ open: boolean }>()
const emit = defineEmits<{ close: [], created: [secret: string | null, name: string] }>()

const { t } = useI18n()
const { ensurePasswordConfirmed } = useSecurity()
const { errors, pending, run } = useGraphqlForm()
const { mutate } = useMutation(createOAuthClientMutation, { refetchQueries: 'active', awaitRefetchQueries: true })

const state = reactive({ name: '', redirectUris: '', confidential: true })

watch(() => props.open, (open) => {
  if (open) {
    errors.value = {}
    Object.assign(state, { name: '', redirectUris: '', confidential: true })
  }
})

const open = computed({
  get: () => props.open,
  set: (value: boolean) => {
    if (!value) {
      emit('close')
    }
  }
})

async function submit(): Promise<void> {
  if (!await ensurePasswordConfirmed()) {
    return
  }

  const name = state.name.trim()
  const result = await run(() => mutate({
    name,
    redirectUris: state.redirectUris.split('\n').map(line => line.trim()).filter(Boolean),
    confidential: state.confidential
  }))
  const created = result?.data?.createOAuthClient

  if (created) {
    emit('created', created.secret ?? null, name)
    emit('close')
  }
}
</script>

<template>
  <AppDialog
    v-model:open="open"
    :label="t('admin.oauthClients.create')"
    :description="t('admin.oauthClients.createHint')"
  >
    <form
      id="oauth-client-form"
      class="wa-stack wa-gap-m"
      @submit.prevent="submit"
    >
      <FormInput
        v-model="state.name"
        name="name"
        :label="t('admin.oauthClients.name')"
        :hint="t('admin.oauthClients.nameHint')"
        :error="errors.name"
        autofocus
        required
      />
      <FormTextarea
        v-model="state.redirectUris"
        :label="t('admin.oauthClients.redirectUris')"
        :hint="t('admin.oauthClients.redirectUrisHint')"
        :error="errors.redirectUris"
      />
      <FormSwitch
        v-model="state.confidential"
        :label="t('admin.oauthClients.confidential')"
        :hint="t('admin.oauthClients.confidentialHint')"
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
        form="oauth-client-form"
        variant="brand"
        :loading="pending"
      >
        {{ t('admin.oauthClients.createSubmit') }}
      </wa-button>
    </template>
  </AppDialog>
</template>
