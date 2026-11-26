<script setup lang="ts">
/**
 * The person agrees (or not) when an application asks for access in the name of their account. The API keeps the
 * request in the session; this page reads what it asks for, sends the answer and then goes on to the address the API
 * names (the application's, also a URL scheme of an app, so a full navigation and not the router).
 */
definePageMeta({ layout: 'auth' })

interface Consent {
  client: { name: string, redirectHost: string | null, selfRegistered: boolean }
  scopes: { id: string, description: string }[]
  authToken: string
  user: { name: string }
}

const { t, te } = useI18n()
const { $backend } = useNuxtApp()
const route = useRoute()

useHead({ title: () => t('oauth.consent.title') })

const consent = ref<Consent | null>(null)
const loading = ref(true)
// No request in the session (it ran out, or the page was opened by hand) or the answer was refused.
const unavailable = ref(false)
// The account may not authorize (unconfirmed address, agent, not approved yet): the API says why, as a parameter of this page or
// as the answer 403 of the JSON routes.
const refusedByApi = ref<string | null>(null)
const refused = computed(() => refusedByApi.value ?? (typeof route.query.error === 'string' ? route.query.error : null))
const answering = ref<'approve' | 'deny' | null>(null)

/** The reason of a 403 of the API (account_not_allowed), or null. */
function reasonOf(error: unknown): string | null {
  const data = error as { status?: number, data?: { reason?: unknown } } | null

  return data?.status === 403 && typeof data.data?.reason === 'string' ? data.data.reason : null
}

const refusalText = computed(() => (refused.value && te(`oauth.consent.errors.${refused.value}`)
  ? t(`oauth.consent.errors.${refused.value}`)
  : t('oauth.consent.errors.other')))

onMounted(async () => {
  if (refused.value) {
    loading.value = false

    return
  }

  try {
    consent.value = await $backend<Consent>('/oauth/consent')
  } catch (error) {
    // 403: the account may not authorize (the reason is shown). 404 and anything else: the request is gone, the application starts again.
    refusedByApi.value = reasonOf(error)
    unavailable.value = refusedByApi.value === null
  } finally {
    loading.value = false
  }
})

async function answer(decision: 'approve' | 'deny'): Promise<void> {
  if (!consent.value || answering.value) {
    return
  }

  answering.value = decision

  try {
    const { redirect } = await $backend<{ redirect: string }>(`/oauth/consent/${decision}`, {
      method: 'POST',
      body: { auth_token: consent.value.authToken }
    })
    window.location.assign(redirect)
  } catch (error) {
    // The request is used up after the first answer; the application has to start again (or the account may not authorize).
    refusedByApi.value = reasonOf(error)
    unavailable.value = refusedByApi.value === null
    answering.value = null
  }
}
</script>

<template>
  <wa-card>
    <h1
      slot="header"
      class="wa-heading-l"
    >
      {{ refused ? t('oauth.consent.refusedTitle') : unavailable ? t('oauth.consent.unavailableTitle') : t('oauth.consent.title') }}
    </h1>

    <div
      v-if="loading"
      class="wa-stack wa-gap-m wa-align-items-center"
    >
      <wa-spinner />
    </div>

    <AppCallout
      v-else-if="refused"
      variant="warning"
      :description="refusalText"
    />

    <AppCallout
      v-else-if="unavailable || !consent"
      variant="warning"
      :description="t('oauth.consent.unavailableText')"
    />

    <div
      v-else
      class="wa-stack wa-gap-m"
    >
      <AppCallout
        v-if="consent.client.selfRegistered"
        variant="warning"
        :description="t('oauth.consent.selfRegistered', { host: consent.client.redirectHost ?? '' })"
      />

      <p class="wa-body-s">
        {{ t('oauth.consent.intro', { client: consent.client.name, name: consent.user.name }) }}
      </p>

      <ul
        class="wa-body-s"
        :aria-label="t('oauth.consent.scopes')"
      >
        <li
          v-for="scope in consent.scopes"
          :key="scope.id"
        >
          {{ scope.description }}
        </li>
      </ul>

      <p
        v-if="consent.client.redirectHost"
        class="wa-body-s wa-color-text-quiet"
      >
        {{ t('oauth.consent.redirect', { host: consent.client.redirectHost }) }}
      </p>

      <wa-button
        variant="brand"
        :loading="answering === 'approve'"
        :disabled="answering !== null"
        style="width: 100%"
        @click="answer('approve')"
      >
        {{ t('oauth.consent.allow') }}
      </wa-button>
      <wa-button
        appearance="outlined"
        variant="neutral"
        :loading="answering === 'deny'"
        :disabled="answering !== null"
        style="width: 100%"
        @click="answer('deny')"
      >
        {{ t('oauth.consent.deny') }}
      </wa-button>
    </div>
  </wa-card>
</template>
