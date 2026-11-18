<script setup lang="ts">
import { pendingAccountPath } from '~/utils/api'

/**
 * Target of the link in the confirmation mail (the API hands over here, also when nobody is
 * logged in). Opening the page confirms nothing, mail scanners open links in advance: the
 * person confirms with the button. Every failure ends here with a clear message and a way on.
 */
definePageMeta({ layout: 'auth', public: true })

const { t } = useI18n()
const route = useRoute()
const { $backend } = useNuxtApp()
const { load } = useViewer()

useHead({ title: () => t('auth.verify.title') })

type Step = 'ready' | 'confirmed' | 'already' | 'invalid'

const param = (name: string): string => (typeof route.query[name] === 'string' ? route.query[name] : '')
const link = computed(() => ({ id: param('id'), hash: param('hash'), expires: param('expires'), signature: param('signature') }))
const complete = computed(() => Object.values(link.value).every(value => value !== ''))

const step = ref<Step>(complete.value && route.query.status !== 'invalid' ? 'ready' : 'invalid')
const pending = ref(false)
const failed = ref(false)
// After "invalid": logged in (an account that waits for the mail) or not decides how to send it again.
const loggedIn = ref(false)
const nextSteps = ref(false)

async function confirm(): Promise<void> {
  pending.value = true
  failed.value = false

  try {
    const { status } = await $backend<{ status: 'ok' | 'already' | 'invalid' }>(
      `/email/verify/${encodeURIComponent(link.value.id)}/${encodeURIComponent(link.value.hash)}`,
      { method: 'POST', query: { expires: link.value.expires, signature: link.value.signature } }
    )

    if (status === 'invalid') {
      step.value = 'invalid'
      await detectSession()
    } else {
      step.value = status === 'ok' ? 'confirmed' : 'already'
      await goOn()
    }
  } catch {
    // The api plugin has said what went wrong (network, throttle); the button stays.
    failed.value = true
  } finally {
    pending.value = false
  }
}

/** Confirmed: logged in, the state of the account decides where to go; otherwise say what comes next. */
async function goOn(): Promise<void> {
  const problem = await load()

  if (problem === null) {
    await navigateTo('/')
  } else if (problem === 'setup-required') {
    await navigateTo('/setup-security')
  } else if (pendingAccountPath(problem)) {
    await navigateTo(pendingAccountPath(problem))
  } else {
    nextSteps.value = true
  }
}

async function detectSession(): Promise<void> {
  loggedIn.value = (await load()) === 'unverified'
}

onMounted(() => {
  if (step.value === 'invalid') {
    void detectSession()
  }
})
</script>

<template>
  <wa-card>
    <h1
      slot="header"
      class="wa-heading-l"
    >
      {{ t('auth.verify.title') }}
    </h1>

    <div
      v-if="step === 'ready'"
      class="wa-stack wa-gap-m"
    >
      <p class="wa-body-s wa-color-text-quiet">
        {{ t('auth.verify.intro') }}
      </p>

      <AppCallout
        v-if="failed"
        variant="danger"
        :title="t('auth.verify.failed')"
      />

      <wa-button
        variant="brand"
        :loading="pending"
        style="width: 100%"
        @click="confirm"
      >
        {{ t('auth.verify.confirm') }}
      </wa-button>
    </div>

    <div
      v-else-if="step === 'invalid'"
      class="wa-stack wa-gap-m"
    >
      <AppCallout
        variant="danger"
        :title="t('auth.verify.invalid')"
      />
      <p class="wa-body-s wa-color-text-quiet">
        {{ t('auth.verify.invalidText') }}
      </p>

      <AuthResendVerification
        :mode="loggedIn ? 'session' : 'address'"
        @confirmed="goOn"
      />

      <p class="wa-text-center wa-body-s">
        <NuxtLink
          to="/login"
          class="wa-link"
        >
          {{ t('auth.verify.toLogin') }}
        </NuxtLink>
      </p>
    </div>

    <div
      v-else
      class="wa-stack wa-gap-m"
    >
      <AppCallout
        variant="success"
        :title="step === 'confirmed' ? t('auth.verify.done') : t('auth.verify.already')"
      />

      <template v-if="nextSteps">
        <p class="wa-body-s">
          {{ t('auth.verify.nextSteps') }}
        </p>
        <wa-button
          variant="brand"
          style="width: 100%"
          @click="navigateTo('/login')"
        >
          {{ t('auth.verify.login') }}
        </wa-button>
      </template>
    </div>
  </wa-card>
</template>
