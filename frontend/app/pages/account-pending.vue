<script setup lang="ts">
/**
 * Two states that look alike but ask for different things (the API answers ACCOUNT_NOT_READY):
 * - "confirm your address" (reason email): also reached without a session, because the registration answers a
 *   new and a taken address alike and logs in only the first. The page says the same in both cases and
 *   names the address when it knows it (from the API, or from the registration form in memory);
 * - "release pending" (reason approval): the address is confirmed, an admin has to release the account, the
 *   person need not do anything.
 */
definePageMeta({ layout: 'auth', public: true })

const { t } = useI18n()
const route = useRoute()
const { logout, pendingEmail } = useViewer()
const { proceed } = useAccountFlow()

const checking = ref(false)
const signedOut = ref(false)
const reason = computed(() => (route.query.reason === 'approval' ? 'approval' : 'email'))

useHead({ title: () => (reason.value === 'email' ? t('auth.pending.emailTitle') : t('auth.pending.approvalTitle')) })

async function check(): Promise<void> {
  checking.value = true

  try {
    await proceed('continued', {
      // Without a session the page about the confirmation stays (the person may just have registered); the
      // page about the release is only for those who are logged in.
      onSignedOut: async () => {
        if (reason.value === 'email') {
          signedOut.value = true
        } else {
          await navigateTo('/login')
        }
      }
    })
  } finally {
    checking.value = false
  }
}

/** Wrong address: end the session (if there is one) and register again. */
async function registerAgain(): Promise<void> {
  pendingEmail.value = null

  if (signedOut.value) {
    await navigateTo('/register')
  } else {
    await logout('/register')
  }
}

onMounted(check)
</script>

<template>
  <wa-card>
    <h1
      slot="header"
      class="wa-heading-l"
    >
      {{ reason === 'email' ? t('auth.pending.emailTitle') : t('auth.pending.approvalTitle') }}
    </h1>

    <div
      v-if="reason === 'email'"
      class="wa-stack wa-gap-m"
    >
      <p class="wa-body-s">
        {{ pendingEmail ? t('auth.pending.emailText', { email: pendingEmail }) : t('auth.pending.emailTextNoAddress') }}
      </p>
      <ul class="wa-body-s wa-color-text-quiet">
        <li>{{ t('auth.pending.deadline') }}</li>
        <li>{{ t('auth.pending.spam') }}</li>
        <li>{{ t('auth.pending.wait') }}</li>
      </ul>

      <wa-button
        v-if="!signedOut"
        variant="brand"
        :loading="checking"
        style="width: 100%"
        @click="check"
      >
        {{ t('auth.pending.recheck') }}
      </wa-button>

      <AuthResendVerification
        :mode="signedOut ? 'address' : 'session'"
        :email="pendingEmail"
        @confirmed="check"
      />

      <p class="wa-text-center wa-body-s">
        {{ t('auth.pending.wrongAddress') }}
        <a
          href="/register"
          class="wa-link"
          @click.prevent="registerAgain"
        >{{ t('auth.pending.registerAgain') }}</a>
      </p>
    </div>

    <div
      v-else
      class="wa-stack wa-gap-m"
    >
      <p class="wa-body-s">
        {{ t('auth.pending.approvalText') }}
      </p>

      <wa-button
        variant="brand"
        :loading="checking"
        style="width: 100%"
        @click="check"
      >
        {{ t('auth.pending.recheck') }}
      </wa-button>
    </div>

    <div
      v-if="!signedOut"
      class="wa-split wa-gap-s"
      style="margin-block-start: var(--wa-space-m)"
    >
      <wa-button
        appearance="plain"
        variant="neutral"
        style="margin-inline-start: auto"
        @click="logout()"
      >
        {{ t('auth.logout') }}
      </wa-button>
    </div>
  </wa-card>
</template>
