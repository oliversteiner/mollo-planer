<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import { apiTokensQuery, createApiTokenMutation, deleteApiTokenMutation } from '~/graphql/security'
import { formatDateTime } from '~/utils/dates'

/**
 * Access for Claude: personal tokens for the MCP server of the app. Claude
 * then acts with your permissions. A token is shown once and expires after
 * 90 days.
 */
const { t, localeProperties } = useI18n()
const toast = useToast()
const apiBase = useRuntimeConfig().public.apiBase.replace(/\/$/, '')
const language = computed(() => localeProperties.value.language ?? 'de-CH')

const { result, refetch } = useQuery(apiTokensQuery, null, { fetchPolicy: 'network-only' })
const tokens = computed(() => result.value?.me.apiTokens ?? [])

const name = ref('Claude')
const created = ref<{ name: string, plainTextToken: string } | null>(null)
const revoking = ref<{ id: string, name: string } | null>(null)
const { errors, pending, run } = useGraphqlForm()

const { mutate: createToken } = useMutation(createApiTokenMutation)
const { mutate: deleteToken } = useMutation(deleteApiTokenMutation)

const command = computed(() => (created.value
  ? `claude mcp add --transport http projektverwaltung ${apiBase}/mcp --header "Authorization: Bearer ${created.value.plainTextToken}"`
  : ''))

async function create(): Promise<void> {
  const result = await run(() => createToken({ name: name.value.trim() }))
  const token = result?.data?.createApiToken

  if (token) {
    created.value = { name: token.token.name, plainTextToken: token.plainTextToken }
    await refetch()
  }
}

async function copy(text: string): Promise<void> {
  await navigator.clipboard.writeText(text)
  toast.add({ title: t('claude.copied'), color: 'success' })
}

async function confirmRevoke(): Promise<void> {
  if (revoking.value) {
    await deleteToken({ id: revoking.value.id })
    toast.add({ title: t('claude.revoked'), color: 'success' })
    await refetch()
  }
}
</script>

<template>
  <wa-card>
    <div slot="header">
      <h2 class="wa-heading-xs">
        {{ t('claude.title') }}
      </h2>
      <p class="wa-body-s wa-color-text-quiet">
        {{ t('claude.intro') }}
      </p>
    </div>

    <div class="wa-stack wa-gap-l">
      <div
        v-if="created"
        class="wa-stack wa-gap-s"
      >
        <AppCallout
          variant="warning"
          :title="t('claude.showOnce')"
        />
        <FormField :label="t('claude.token')">
          <div class="claude-copy">
            <FormInput
              :model-value="created.plainTextToken"
              readonly
              class="claude-code-field"
            />
            <wa-button
              appearance="outlined"
              variant="neutral"
              :aria-label="t('claude.copyToken')"
              @click="copy(created.plainTextToken)"
            >
              <wa-icon name="copy"></wa-icon>
            </wa-button>
          </div>
        </FormField>
        <FormField
          :label="t('claude.command')"
          :hint="t('claude.commandHint')"
        >
          <div class="claude-copy">
            <code class="claude-command">{{ command }}</code>
            <wa-button
              appearance="outlined"
              variant="neutral"
              :aria-label="t('claude.copyCommand')"
              @click="copy(command)"
            >
              <wa-icon name="copy"></wa-icon>
            </wa-button>
          </div>
        </FormField>
        <div>
          <wa-button
            variant="brand"
            @click="created = null"
          >
            {{ t('security.done') }}
          </wa-button>
        </div>
      </div>

      <form
        v-else
        class="claude-form"
        @submit.prevent="create"
      >
        <FormInput
          v-model="name"
          class="claude-name"
          :label="t('claude.name')"
          :hint="t('claude.nameHint')"
          :error="errors.name"
          maxlength="60"
        />
        <wa-button
          type="submit"
          variant="brand"
          :loading="pending"
        >
          <wa-icon
            slot="start"
            name="key"
          ></wa-icon>
          {{ t('claude.create') }}
        </wa-button>
      </form>

      <ul
        v-if="tokens.length"
        class="claude-list wa-list-plain"
      >
        <li
          v-for="token in tokens"
          :key="token.id"
          class="claude-row"
        >
          <wa-icon
            name="key"
            class="claude-icon"
          ></wa-icon>
          <span class="claude-text">
            <span class="claude-title">{{ token.name }}</span>
            <span class="claude-sub">
              {{ token.lastUsedAt ? t('claude.lastUsed', { date: formatDateTime(token.lastUsedAt, language) }) : t('claude.neverUsed') }}
              <template v-if="token.expiresAt"> · {{ t('claude.expires', { date: formatDateTime(token.expiresAt, language) }) }}</template>
            </span>
          </span>
          <wa-button
            appearance="plain"
            variant="neutral"
            size="s"
            :aria-label="t('claude.revoke', { name: token.name })"
            @click="revoking = { id: token.id, name: token.name }"
          >
            <wa-icon name="trash"></wa-icon>
          </wa-button>
        </li>
      </ul>
    </div>

    <ConfirmModal
      :open="revoking !== null"
      :title="t('claude.revokeTitle', { name: revoking?.name ?? '' })"
      :description="t('claude.revokeDescription')"
      :confirm-label="t('claude.revokeConfirm')"
      :action="confirmRevoke"
      @close="revoking = null"
    />
  </wa-card>
</template>

<style scoped>
.claude-form {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: var(--wa-space-s);
}

.claude-name {
  flex: 1;
  min-inline-size: 12rem;
}

/* The button sits next to the field, level with its input, not with its label. */
.claude-form > wa-button {
  margin-block-start: 1.6em;
}

.claude-copy {
  display: flex;
  align-items: center;
  gap: var(--wa-space-s);
  inline-size: 100%;
}

.claude-code-field {
  flex: 1;
  font-family: var(--wa-font-family-code);
}

.claude-command {
  flex: 1;
  overflow-x: auto;
  padding: var(--wa-space-xs);
  border-radius: var(--wa-border-radius-m);
  background-color: var(--wa-color-surface-lowered);
  font-family: var(--wa-font-family-code);
  font-size: var(--wa-font-size-xs);
  white-space: nowrap;
}

.claude-list {
  margin: 0;
  padding: 0;
  border: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  border-radius: var(--wa-border-radius-m);
}

.claude-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-s);
  padding: var(--wa-space-xs) var(--wa-space-s);
}

.claude-row + .claude-row {
  border-block-start: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
}

.claude-icon {
  font-size: var(--wa-font-size-l);
  color: var(--wa-color-brand-on-quiet);
}

.claude-text {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-inline-size: 0;
}

.claude-title {
  overflow: hidden;
  font-weight: var(--wa-font-weight-semibold);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.claude-sub {
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-xs);
}
</style>
