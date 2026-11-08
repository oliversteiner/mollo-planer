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
  <UCard class="lg:col-span-2">
    <template #header>
      <h2 class="font-semibold text-highlighted">
        {{ t('claude.title') }}
      </h2>
      <p class="text-sm text-muted">
        {{ t('claude.intro') }}
      </p>
    </template>

    <div class="space-y-6">
      <div
        v-if="created"
        class="space-y-3"
      >
        <UAlert
          color="warning"
          variant="subtle"
          icon="i-fadl-triangle-exclamation"
          :title="t('claude.showOnce')"
        />
        <UFormField :label="t('claude.token')">
          <div class="flex gap-2">
            <UInput
              :model-value="created.plainTextToken"
              readonly
              class="flex-1 font-mono"
            />
            <UButton
              icon="i-fadl-copy"
              color="neutral"
              variant="outline"
              :aria-label="t('claude.copyToken')"
              @click="copy(created.plainTextToken)"
            />
          </div>
        </UFormField>
        <UFormField
          :label="t('claude.command')"
          :hint="t('claude.commandHint')"
        >
          <div class="flex gap-2">
            <code class="flex-1 overflow-x-auto rounded-md bg-elevated p-2 font-mono text-xs whitespace-nowrap">{{ command }}</code>
            <UButton
              icon="i-fadl-copy"
              color="neutral"
              variant="outline"
              :aria-label="t('claude.copyCommand')"
              @click="copy(command)"
            />
          </div>
        </UFormField>
        <UButton
          :label="t('security.done')"
          @click="created = null"
        />
      </div>

      <form
        v-else
        class="flex flex-wrap items-end gap-2"
        @submit.prevent="create"
      >
        <UFormField
          :label="t('claude.name')"
          :hint="t('claude.nameHint')"
          :error="errors.name"
          class="min-w-48 flex-1"
        >
          <UInput
            v-model="name"
            maxlength="60"
            class="w-full"
          />
        </UFormField>
        <UButton
          type="submit"
          icon="i-fadl-key"
          :label="t('claude.create')"
          :loading="pending"
        />
      </form>

      <ul
        v-if="tokens.length"
        class="divide-y divide-default rounded-md border border-default"
      >
        <li
          v-for="token in tokens"
          :key="token.id"
          class="flex flex-wrap items-center gap-3 px-3 py-2"
        >
          <UIcon
            name="i-fadl-key"
            class="size-5 text-primary"
          />
          <span class="min-w-0 flex-1">
            <span class="block truncate font-medium">{{ token.name }}</span>
            <span class="block text-xs text-muted">
              {{ token.lastUsedAt ? t('claude.lastUsed', { date: formatDateTime(token.lastUsedAt, language) }) : t('claude.neverUsed') }}
              <template v-if="token.expiresAt"> · {{ t('claude.expires', { date: formatDateTime(token.expiresAt, language) }) }}</template>
            </span>
          </span>
          <UButton
            icon="i-fadl-trash"
            color="neutral"
            variant="ghost"
            size="sm"
            :aria-label="t('claude.revoke', { name: token.name })"
            @click="revoking = { id: token.id, name: token.name }"
          />
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
  </UCard>
</template>
