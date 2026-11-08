<script setup lang="ts">
import { useQuery } from '@vue/apollo-composable'
import { securityQuery } from '~/graphql/security'
import { formatDateTime } from '~/utils/dates'

/**
 * How the own account is secured: authenticator app and passkeys. While
 * two-factor authentication is required, removing the last factor locks
 * the session until a new one is set up.
 */
const { t, localeProperties } = useI18n()
const toast = useToast()
const { twoFactorEnabled, load } = useViewer()
const { recoveryCodes, regenerateRecoveryCodes, disableTwoFactor, removePasskey } = useSecurity()

const { result, refetch } = useQuery(securityQuery, null, { fetchPolicy: 'network-only' })
const me = computed(() => result.value?.me)
const passkeys = computed(() => me.value?.passkeys ?? [])
const factorCount = computed(() => (me.value?.twoFactorConfirmed ? 1 : 0) + passkeys.value.length)
const language = computed(() => localeProperties.value.language ?? 'de-CH')

const settingUp = ref(false)
const shownCodes = ref<string[] | null>(null)
const confirming = ref<{ kind: 'twoFactor' } | { kind: 'passkey', id: string, name: string } | null>(null)

async function refresh(): Promise<void> {
  await refetch()
  const problem = await load()

  if (problem === 'setup-required') {
    await navigateTo('/setup-security')
  }
}

async function showCodes(regenerate: boolean): Promise<void> {
  const codes = regenerate ? await regenerateRecoveryCodes() : await recoveryCodes()

  if (codes) {
    shownCodes.value = codes
  }
}

/** Removing the last factor is only a problem while 2FA is required. */
const lastFactorWarning = computed(() => (twoFactorEnabled.value && factorCount.value <= 1 ? ` ${t('security.lastFactor')}` : ''))

async function confirmRemoval(): Promise<void> {
  const target = confirming.value

  if (!target) {
    return
  }
  const done = target.kind === 'twoFactor' ? await disableTwoFactor() : await removePasskey(target.id)

  if (done) {
    shownCodes.value = null
    toast.add({ title: t('security.removed'), color: 'success' })
    await refresh()
  }
}

async function onTwoFactorDone(): Promise<void> {
  settingUp.value = false
  await refresh()
}
</script>

<template>
  <UCard class="lg:col-span-2">
    <template #header>
      <h2 class="font-semibold text-highlighted">
        {{ t('security.title') }}
      </h2>
      <p class="text-sm text-muted">
        {{ twoFactorEnabled ? t('security.required') : t('security.optional') }}
      </p>
    </template>

    <div class="space-y-8">
      <section class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h3 class="font-medium text-highlighted">
            {{ t('security.twoFactor.title') }}
          </h3>
          <UBadge
            :color="me?.twoFactorConfirmed ? 'success' : 'neutral'"
            variant="subtle"
          >
            {{ me?.twoFactorConfirmed ? t('security.active') : t('security.inactive') }}
          </UBadge>
        </div>

        <SecurityTwoFactorSetup
          v-if="settingUp"
          @done="onTwoFactorDone"
        />
        <UButton
          v-else-if="!me?.twoFactorConfirmed"
          icon="i-fadl-mobile-screen"
          :label="t('security.twoFactor.start')"
          @click="settingUp = true"
        />
        <template v-else>
          <div class="flex flex-wrap gap-2">
            <UButton
              icon="i-fadl-key"
              :label="t('security.recovery.show')"
              color="neutral"
              variant="outline"
              size="sm"
              @click="showCodes(false)"
            />
            <UButton
              icon="i-fadl-arrow-rotate-right"
              :label="t('security.recovery.regenerate')"
              color="neutral"
              variant="outline"
              size="sm"
              @click="showCodes(true)"
            />
            <UButton
              icon="i-fadl-trash"
              :label="t('security.twoFactor.remove')"
              color="error"
              variant="ghost"
              size="sm"
              @click="confirming = { kind: 'twoFactor' }"
            />
          </div>
          <SecurityRecoveryCodes
            v-if="shownCodes"
            :codes="shownCodes"
          />
        </template>
      </section>

      <section class="space-y-3">
        <h3 class="font-medium text-highlighted">
          {{ t('security.passkeys.title') }}
        </h3>
        <p class="text-sm text-muted">
          {{ t('security.passkeys.intro') }}
        </p>

        <ul
          v-if="passkeys.length"
          class="divide-y divide-default rounded-md border border-default"
        >
          <li
            v-for="passkey in passkeys"
            :key="passkey.id"
            class="flex flex-wrap items-center gap-3 px-3 py-2"
          >
            <UIcon
              name="i-fadl-fingerprint"
              class="size-5 text-primary"
            />
            <span class="min-w-0 flex-1">
              <span class="block truncate font-medium">{{ passkey.name }}</span>
              <span class="block text-xs text-muted">
                {{ passkey.lastUsedAt ? t('security.passkeys.lastUsed', { date: formatDateTime(passkey.lastUsedAt, language) }) : t('security.passkeys.neverUsed') }}
              </span>
            </span>
            <UButton
              icon="i-fadl-trash"
              color="neutral"
              variant="ghost"
              size="sm"
              :aria-label="t('security.passkeys.remove', { name: passkey.name })"
              @click="confirming = { kind: 'passkey', id: passkey.id, name: passkey.name }"
            />
          </li>
        </ul>
        <UAlert
          v-if="passkeys.length === 1 && !me?.twoFactorConfirmed"
          color="info"
          variant="subtle"
          :title="t('security.passkeys.secondHint')"
        />

        <SecurityPasskeyAdd @added="refresh" />
      </section>
    </div>

    <ConfirmModal
      :open="confirming !== null"
      :title="confirming?.kind === 'passkey' ? t('security.passkeys.removeTitle', { name: confirming.name }) : t('security.twoFactor.removeTitle')"
      :description="t('security.removeDescription') + lastFactorWarning"
      :confirm-label="t('common.delete')"
      :action="confirmRemoval"
      @close="confirming = null"
    />
  </UCard>
</template>
