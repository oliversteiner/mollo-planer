<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import { projectWebhookQuery, removeProjectWebhookMutation, setProjectWebhookMutation, testProjectWebhookMutation } from '~/graphql/planer'
import type { WebhookKind } from '~/graphql/generated/graphql'
import { formatDateTime, isAtOrAfter } from '~/utils/dates'

/**
 * Project settings: the notification when the planer file changes (project.update, docs/auftrag-m5.md §4.5).
 * Kind GitHub (repository_dispatch with a token) or generic (signed POST). The secret goes in and never
 * comes back: after saving the card says "saved" and offers "Replace". Save, test (sends now and shows the
 * answer), remove (asks first), and a line for the status of the last notification.
 */
const props = defineProps<{ projectId: string }>()

const { t, localeProperties } = useI18n()
const toast = useToast()
const { errors, pending: saving, run } = useGraphqlForm()

const { result } = useQuery(projectWebhookQuery, () => ({ id: props.projectId }), { fetchPolicy: 'cache-and-network' })
const saved = computed(() => result.value?.project?.webhook ?? null)

const form = reactive<{ kind: WebhookKind, target: string, secret: string }>({ kind: 'GITHUB', target: '', secret: '' })
const replacing = ref(false)
const removing = ref(false)
const testing = ref(false)
const testResult = ref<{ ok: boolean, message: string } | null>(null)

/** The form shows what is saved, once it is loaded and while nothing is being edited. */
watch(saved, (webhook) => {
  if (webhook) {
    Object.assign(form, { kind: webhook.kind, target: webhook.target, secret: '' })
  }
  replacing.value = false
}, { immediate: true })

/**
 * Another kind has another target (owner/name or an address): the field is emptied, so the old value does
 * not stand there as an address. Back to the saved kind, the saved target is back. The token or secret
 * of the old kind is no use for the new one, so a new one is asked for.
 */
watch(() => form.kind, (kind) => {
  form.target = saved.value && saved.value.kind === kind ? saved.value.target : ''
  form.secret = ''
  replacing.value = false
})

const kinds = computed(() => [
  { label: t('planer.webhook.kinds.GITHUB'), value: 'GITHUB' },
  { label: t('planer.webhook.kinds.GENERIC'), value: 'GENERIC' }
])

const github = computed(() => form.kind === 'GITHUB')
const secretSaved = computed(() => !!saved.value?.hasSecret && saved.value.kind === form.kind && !replacing.value)
const dirty = computed(() => !saved.value || saved.value.kind !== form.kind || saved.value.target !== form.target.trim() || form.secret !== '')
const language = computed(() => localeProperties.value.language ?? 'de-CH')

// The notification is no part of the plan: only its own query is loaded again.
const refresh = { refetchQueries: [{ query: projectWebhookQuery, variables: { id: props.projectId } }], awaitRefetchQueries: true }
const { mutate: setWebhook } = useMutation(setProjectWebhookMutation, refresh)
const { mutate: removeWebhook } = useMutation(removeProjectWebhookMutation, refresh)
const { mutate: testWebhook } = useMutation(testProjectWebhookMutation, refresh)

async function save(): Promise<void> {
  testResult.value = null
  const response = await run(() => setWebhook({
    projectId: props.projectId,
    input: { kind: form.kind, target: form.target.trim(), secret: form.secret === '' ? null : form.secret }
  }))

  if (response) {
    form.secret = ''
    replacing.value = false
    toast.add({ title: t('planer.webhook.saved'), color: 'success' })
  }
}

async function test(): Promise<void> {
  testing.value = true

  try {
    const response = await testWebhook({ projectId: props.projectId })
    testResult.value = response?.data?.testProjectWebhook ?? null
  } catch {
    // Reported by the api plugin.
  } finally {
    testing.value = false
  }
}

async function remove(): Promise<void> {
  await removeWebhook({ projectId: props.projectId })
  Object.assign(form, { kind: 'GITHUB', target: '', secret: '' })
  testResult.value = null
  toast.add({ title: t('planer.webhook.removed'), color: 'success' })
}

/** A short code of the server as a sentence: timeout, connection, target, "HTTP 401". */
function errorText(code: string | null | undefined): string {
  if (!code) {
    return ''
  }

  return ['timeout', 'connection', 'target', 'error'].includes(code) ? t(`planer.webhook.error.${code}`) : code
}
</script>

<template>
  <wa-card>
    <div slot="header">
      <h2 class="wa-heading-m">
        {{ t('planer.webhook.title') }}
      </h2>
      <p class="wa-body-s wa-color-text-quiet">
        {{ t('planer.webhook.intro') }}
      </p>
    </div>

    <form
      class="wa-stack wa-gap-m"
      @submit.prevent="save"
    >
      <FormSelect
        v-model="form.kind"
        name="kind"
        :items="kinds"
        :label="t('planer.webhook.kind')"
        class="webhook-kind"
      />

      <FormInput
        v-model="form.target"
        name="target"
        :label="github ? t('planer.webhook.repository') : t('planer.webhook.url')"
        :hint="github ? t('planer.webhook.repositoryHint') : t('planer.webhook.urlHint')"
        :error="errors.target"
        autocomplete="off"
        required
      />

      <div
        v-if="secretSaved"
        class="webhook-secret"
      >
        <span class="webhook-secret-text">
          <wa-icon name="lock"></wa-icon>
          {{ github ? t('planer.webhook.tokenSaved') : t('planer.webhook.secretSaved') }}
        </span>
        <wa-button
          appearance="outlined"
          variant="neutral"
          size="s"
          @click="replacing = true"
        >
          {{ t('planer.webhook.replace') }}
        </wa-button>
      </div>
      <FormInput
        v-else
        v-model="form.secret"
        name="secret"
        type="password"
        :label="github ? t('planer.webhook.token') : t('planer.webhook.secret')"
        :hint="github ? t('planer.webhook.tokenHint') : t('planer.webhook.secretHint')"
        :error="errors.secret"
        autocomplete="new-password"
        :required="!saved || saved.kind !== form.kind"
      />

      <div class="wa-cluster wa-gap-s">
        <wa-button
          type="submit"
          variant="brand"
          :loading="saving"
          :disabled="!dirty"
        >
          {{ t('common.save') }}
        </wa-button>
        <wa-button
          v-if="saved"
          appearance="outlined"
          variant="neutral"
          :loading="testing"
          :disabled="dirty"
          @click="test"
        >
          <wa-icon
            slot="start"
            name="paper-plane"
          ></wa-icon>
          {{ t('planer.webhook.test') }}
        </wa-button>
        <wa-button
          v-if="saved"
          appearance="plain"
          variant="danger"
          @click="removing = true"
        >
          <wa-icon
            slot="start"
            name="trash"
          ></wa-icon>
          {{ t('planer.webhook.remove') }}
        </wa-button>
      </div>

      <AppCallout
        v-if="testResult"
        :variant="testResult.ok ? 'success' : 'danger'"
        :description="testResult.message"
      />

      <p
        v-if="saved?.lastFailedAt && (!saved.lastSentAt || isAtOrAfter(saved.lastFailedAt, saved.lastSentAt))"
        class="webhook-status"
        data-status="failed"
      >
        <wa-icon name="circle-exclamation"></wa-icon>
        {{ t('planer.webhook.failed', { date: formatDateTime(saved.lastFailedAt, language), error: errorText(saved.lastError) }) }}
      </p>
      <p
        v-else-if="saved?.lastSentAt"
        class="webhook-status"
        data-status="sent"
      >
        <wa-icon name="circle-check"></wa-icon>
        {{ t('planer.webhook.sent', { date: formatDateTime(saved.lastSentAt, language), revision: saved.lastSentRevision ?? '' }) }}
      </p>
      <p
        v-else-if="saved"
        class="webhook-status"
        data-status="none"
      >
        {{ t('planer.webhook.notSent') }}
      </p>
    </form>

    <ConfirmModal
      :open="removing"
      :title="t('planer.webhook.removeTitle')"
      :description="t('planer.webhook.removeDescription')"
      :confirm-label="t('planer.webhook.remove')"
      :action="remove"
      @close="removing = false"
    />
  </wa-card>
</template>

<style scoped>
.webhook-kind {
  inline-size: min(100%, 16rem);
}

.webhook-secret {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-s);
}

.webhook-secret-text {
  display: inline-flex;
  align-items: center;
  gap: var(--wa-space-xs);
  color: var(--wa-color-text-quiet);
}

.webhook-status {
  display: flex;
  align-items: flex-start;
  gap: var(--wa-space-xs);
  margin: 0;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}

.webhook-status[data-status='failed'] {
  color: var(--wa-color-danger-on-quiet);
}
</style>
