<script setup lang="ts">
/**
 * Self-registered accounts stay here until the mail address is confirmed
 * and an admin has approved them (the API answers ACCOUNT_NOT_READY). Once
 * ready, the check sends them on to the app.
 */
definePageMeta({ layout: 'auth', public: true })

const { t } = useI18n()
const route = useRoute()
const { load, logout } = useViewer()

useHead({ title: () => t('auth.pending.title') })

const checking = ref(false)
const reason = computed(() => (route.query.reason === 'approval' ? 'approval' : 'email'))

async function check(): Promise<void> {
  checking.value = true

  try {
    const problem = await load()
    const pendingPath = pendingAccountPath(problem)

    if (problem === null) {
      await navigateTo('/')
    } else if (problem === 'setup-required') {
      await navigateTo('/setup-security')
    } else if (pendingPath) {
      if (pendingPath !== route.fullPath) {
        await navigateTo(pendingPath, { replace: true })
      }
    } else {
      await navigateTo('/login')
    }
  } finally {
    checking.value = false
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
      {{ t('auth.pending.title') }}
    </h1>

    <div class="wa-stack wa-gap-m">
      <p class="wa-body-s">
        {{ reason === 'email' ? t('auth.pending.emailText') : t('auth.pending.approvalText') }}
      </p>

      <wa-button
        variant="brand"
        :loading="checking"
        style="width: 100%"
        @click="check"
      >
        {{ t('auth.pending.recheck') }}
      </wa-button>

      <div class="wa-split wa-gap-s">
        <wa-button
          appearance="plain"
          variant="neutral"
          style="margin-inline-start: auto"
          @click="logout"
        >
          {{ t('auth.logout') }}
        </wa-button>
      </div>
    </div>
  </wa-card>
</template>
