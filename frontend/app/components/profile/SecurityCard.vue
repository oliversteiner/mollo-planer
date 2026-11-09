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
  <wa-card>
    <div slot="header">
      <h2 class="wa-heading-xs">
        {{ t('security.title') }}
      </h2>
      <p class="wa-body-s wa-color-text-quiet">
        {{ twoFactorEnabled ? t('security.required') : t('security.optional') }}
      </p>
    </div>

    <div class="wa-stack wa-gap-xl">
      <section class="wa-stack wa-gap-s">
        <div class="wa-split wa-gap-s">
          <h3 class="wa-heading-xs">
            {{ t('security.twoFactor.title') }}
          </h3>
          <wa-badge
            :variant="me?.twoFactorConfirmed ? 'success' : 'neutral'"
            appearance="filled"
          >
            {{ me?.twoFactorConfirmed ? t('security.active') : t('security.inactive') }}
          </wa-badge>
        </div>

        <SecurityTwoFactorSetup
          v-if="settingUp"
          @done="onTwoFactorDone"
        />
        <div v-else-if="!me?.twoFactorConfirmed">
          <wa-button
            variant="brand"
            @click="settingUp = true"
          >
            <wa-icon
              slot="start"
              name="mobile-screen"
            ></wa-icon>
            {{ t('security.twoFactor.start') }}
          </wa-button>
        </div>
        <template v-else>
          <div class="wa-cluster wa-gap-s">
            <wa-button
              appearance="outlined"
              variant="neutral"
              size="small"
              @click="showCodes(false)"
            >
              <wa-icon
                slot="start"
                name="key"
              ></wa-icon>
              {{ t('security.recovery.show') }}
            </wa-button>
            <wa-button
              appearance="outlined"
              variant="neutral"
              size="small"
              @click="showCodes(true)"
            >
              <wa-icon
                slot="start"
                name="arrow-rotate-right"
              ></wa-icon>
              {{ t('security.recovery.regenerate') }}
            </wa-button>
            <wa-button
              appearance="plain"
              variant="danger"
              size="small"
              @click="confirming = { kind: 'twoFactor' }"
            >
              <wa-icon
                slot="start"
                name="trash"
              ></wa-icon>
              {{ t('security.twoFactor.remove') }}
            </wa-button>
          </div>
          <SecurityRecoveryCodes
            v-if="shownCodes"
            :codes="shownCodes"
          />
        </template>
      </section>

      <section class="wa-stack wa-gap-s">
        <h3 class="wa-heading-xs">
          {{ t('security.passkeys.title') }}
        </h3>
        <p class="wa-body-s wa-color-text-quiet">
          {{ t('security.passkeys.intro') }}
        </p>

        <ul
          v-if="passkeys.length"
          class="security-list wa-list-plain"
        >
          <li
            v-for="passkey in passkeys"
            :key="passkey.id"
            class="security-row"
          >
            <wa-icon
              name="fingerprint"
              class="security-icon"
            ></wa-icon>
            <span class="security-text">
              <span class="security-title">{{ passkey.name }}</span>
              <span class="security-sub">
                {{ passkey.lastUsedAt ? t('security.passkeys.lastUsed', { date: formatDateTime(passkey.lastUsedAt, language) }) : t('security.passkeys.neverUsed') }}
              </span>
            </span>
            <wa-button
              appearance="plain"
              variant="neutral"
              size="small"
              :aria-label="t('security.passkeys.remove', { name: passkey.name })"
              @click="confirming = { kind: 'passkey', id: passkey.id, name: passkey.name }"
            >
              <wa-icon name="trash"></wa-icon>
            </wa-button>
          </li>
        </ul>
        <AppCallout
          v-if="passkeys.length === 1 && !me?.twoFactorConfirmed"
          variant="brand"
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
  </wa-card>
</template>

<style scoped>
.security-list {
  margin: 0;
  padding: 0;
  border: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  border-radius: var(--wa-border-radius-m);
}

.security-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-s);
  padding: var(--wa-space-xs) var(--wa-space-s);
}

.security-row + .security-row {
  border-block-start: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
}

.security-icon {
  font-size: var(--wa-font-size-l);
  color: var(--wa-color-brand-on-quiet);
}

.security-text {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-inline-size: 0;
}

.security-title {
  overflow: hidden;
  font-weight: var(--wa-font-weight-semibold);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.security-sub {
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-xs);
}
</style>
