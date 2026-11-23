<script setup lang="ts">
/**
 * The person agrees (or not) when an application asks for access in the name of their account. The API keeps the
 * request in the session; this page reads what it asks for, sends the answer and then goes on to the address the API
 * names (the application's, also a URL scheme of an app, so a full navigation and not the router).
 */
definePageMeta({ layout: 'auth' })

interface Consent {
  client: { name: string, redirectHost: string | null }
  scopes: { id: string, description: string }[]
  authToken: string
  user: { name: string }
}

const { t } = useI18n()
const { $backend } = useNuxtApp()

useHead({ title: () => t('oauth.consent.title') })

const consent = ref<Consent | null>(null)
const loading = ref(true)
// No request in the session (it ran out, or the page was opened by hand) or the answer was refused.
const unavailable = ref(false)
const answering = ref<'approve' | 'deny' | null>(null)

onMounted(async () => {
  try {
    consent.value = await $backend<Consent>('/oauth/consent')
  } catch {
    // 404: no request in the session. Anything else: the page cannot help either, the application starts again.
    unavailable.value = true
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
  } catch {
    // The request is used up after the first answer; the application has to start again.
    unavailable.value = true
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
      {{ unavailable ? t('oauth.consent.unavailableTitle') : t('oauth.consent.title') }}
    </h1>

    <div
      v-if="loading"
      class="wa-stack wa-gap-m wa-align-items-center"
    >
      <wa-spinner />
    </div>

    <AppCallout
      v-else-if="unavailable || !consent"
      variant="warning"
      :description="t('oauth.consent.unavailableText')"
    />

    <div
      v-else
      class="wa-stack wa-gap-m"
    >
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
