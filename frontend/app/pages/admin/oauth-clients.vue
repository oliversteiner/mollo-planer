<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import { oauthClientsQuery, revokeOAuthClientMutation } from '~/graphql/admin'
import type { ActionMenuItem } from '~/components/ActionMenu.vue'
import { formatDateTime } from '~/utils/dates'

/**
 * The applications that may log in through the planer (docs/oauth-sso.md): Club DB, the app, Claude. Only admins
 * (settings.manage). Making and revoking need a recent password confirmation; the secret of a confidential client
 * is shown once, right after it was made.
 */
const { t, locale } = useI18n()
const toast = useToast()

useHead({ title: () => t('admin.oauthClients.title') })

const { result, loading } = useQuery(oauthClientsQuery, null, { fetchPolicy: 'cache-and-network' })
type Client = NonNullable<typeof result.value>['oauthClients'][number]
const clients = computed(() => result.value?.oauthClients ?? [])

const { mutate: revokeClient } = useMutation(revokeOAuthClientMutation, { refetchQueries: 'active', awaitRefetchQueries: true })

const creating = ref(false)
const created = ref<{ name: string, secret: string } | null>(null)
const revoking = ref<Client | null>(null)

function onCreated(secret: string | null, name: string): void {
  if (secret) {
    created.value = { name, secret }
  } else {
    toast.add({ title: t('admin.oauthClients.created', { name }), color: 'success' })
  }
}

async function copySecret(): Promise<void> {
  if (created.value) {
    await navigator.clipboard.writeText(created.value.secret)
    toast.add({ title: t('claude.copied'), color: 'success' })
  }
}

async function confirmRevoke(): Promise<void> {
  const target = revoking.value

  if (!target) {
    return
  }

  // The password is asked for in the dialog of the question (ConfirmModal requires-password).
  await revokeClient({ id: target.id })
  toast.add({ title: t('admin.oauthClients.revoked', { name: target.name }), color: 'success' })
}

function actions(client: Client): ActionMenuItem[][] {
  return client.revoked
    ? []
    : [[{ label: t('admin.oauthClients.revoke'), icon: 'ban', color: 'error', onSelect: () => { revoking.value = client } }]]
}
</script>

<template>
  <PageFrame
    :title="t('admin.oauthClients.title')"
    :breadcrumbs="[{ label: t('nav.admin') }, { label: t('admin.oauthClients.title') }]"
  >
    <template #actions>
      <wa-button
        variant="brand"
        @click="creating = true"
      >
        <wa-icon
          slot="start"
          name="plus"
        ></wa-icon>
        {{ t('admin.oauthClients.create') }}
      </wa-button>
    </template>

    <p class="wa-body-s wa-color-text-quiet clients-intro">
      {{ t('admin.oauthClients.intro') }}
    </p>

    <wa-skeleton
      v-if="loading && !result"
      effect="sheen"
      class="clients-skeleton"
    ></wa-skeleton>
    <p
      v-else-if="clients.length === 0"
      class="wa-body-s"
    >
      {{ t('admin.oauthClients.none') }}
    </p>
    <ul
      v-else
      class="clients-list wa-list-plain"
    >
      <li
        v-for="client in clients"
        :key="client.id"
        class="clients-row"
      >
        <span class="clients-text">
          <span class="clients-name">
            <span class="clients-name-text">{{ client.name }}</span>
            <wa-badge
              :variant="client.confidential ? 'brand' : 'neutral'"
              appearance="outlined"
            >
              {{ client.confidential ? t('admin.oauthClients.confidentialBadge') : t('admin.oauthClients.publicBadge') }}
            </wa-badge>
            <wa-badge
              v-if="client.selfRegistered"
              variant="warning"
              appearance="outlined"
              :title="t('admin.oauthClients.selfRegisteredHint')"
            >
              {{ t('admin.oauthClients.selfRegisteredBadge') }}
            </wa-badge>
            <wa-badge
              v-if="client.revoked"
              variant="danger"
              appearance="filled"
            >
              {{ t('admin.oauthClients.revokedBadge') }}
            </wa-badge>
          </span>
          <span class="clients-uris">{{ client.redirectUris.join(', ') }}</span>
          <span class="clients-meta">{{ t('admin.oauthClients.madeOn', { date: formatDateTime(client.createdAt, locale) }) }}</span>
        </span>
        <ActionMenu
          v-if="!client.revoked"
          :items="actions(client)"
          :label="t('admin.oauthClients.actions', { name: client.name })"
          size="m"
        />
      </li>
    </ul>

    <AdminOAuthClientFormModal
      :open="creating"
      @close="creating = false"
      @created="onCreated"
    />
    <AppDialog
      :open="created !== null"
      :label="t('admin.oauthClients.secretTitle', { name: created?.name ?? '' })"
      @update:open="created = null"
    >
      <div class="wa-stack wa-gap-s">
        <AppCallout
          variant="warning"
          :title="t('admin.oauthClients.secretOnce')"
        />
        <FormField :label="t('admin.oauthClients.secret')">
          <div class="clients-copy">
            <FormInput
              :model-value="created?.secret ?? ''"
              :label="t('admin.oauthClients.secret')"
              hide-label
              readonly
              class="clients-secret"
            />
            <wa-button
              appearance="outlined"
              variant="neutral"
              :aria-label="t('admin.oauthClients.copySecret')"
              @click="copySecret"
            >
              <wa-icon name="copy"></wa-icon>
            </wa-button>
          </div>
        </FormField>
      </div>
      <template #footer>
        <wa-button
          variant="brand"
          @click="created = null"
        >
          {{ t('security.done') }}
        </wa-button>
      </template>
    </AppDialog>
    <ConfirmModal
      :open="revoking !== null"
      :title="t('admin.oauthClients.revokeTitle', { name: revoking?.name ?? '' })"
      :description="t('admin.oauthClients.revokeDescription')"
      :confirm-label="t('admin.oauthClients.revokeConfirm')"
      requires-password
      :action="confirmRevoke"
      @close="revoking = null"
    />
  </PageFrame>
</template>

<style scoped>
.clients-intro {
  max-inline-size: 42rem;
}

.clients-skeleton {
  block-size: 6rem;
}

.clients-list {
  margin: 0;
  padding: 0;
  border: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  border-radius: var(--wa-border-radius-l);
}

.clients-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-s);
  padding: var(--wa-space-s) var(--wa-space-m);
}

.clients-row + .clients-row {
  border-block-start: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
}

.clients-text {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-inline-size: 0;
}

.clients-name {
  display: flex;
  align-items: center;
  gap: var(--wa-space-xs);
  font-weight: var(--wa-font-weight-semibold);
}

.clients-name-text {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.clients-uris {
  overflow-wrap: anywhere;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}

.clients-meta {
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-xs);
}

.clients-copy {
  display: flex;
  align-items: center;
  gap: var(--wa-space-xs);
}

.clients-secret {
  flex: 1;
}
</style>
