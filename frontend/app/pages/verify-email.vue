<script setup lang="ts">
/**
 * Target of the link in the confirmation mail (the API redirects here).
 * Logged in: the confirmed account goes on to approval or the app. Not
 * logged in (for example on another device): ask to log in.
 */
definePageMeta({ layout: 'auth', public: true })

const { t } = useI18n()
const { load } = useViewer()

useHead({ title: () => t('auth.verify.title') })

const checked = ref(false)
const loggedOut = ref(false)

onMounted(async () => {
  const problem = await load()

  if (problem === null) {
    await navigateTo('/')
  } else if (problem === 'setup-required') {
    await navigateTo('/setup-security')
  } else if (pendingAccountPath(problem)) {
    await navigateTo(pendingAccountPath(problem))
  } else {
    loggedOut.value = true
    checked.value = true
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
      v-if="checked"
      class="wa-stack wa-gap-m"
    >
      <AppCallout
        variant="success"
        :title="t('auth.verify.done')"
      />
      <p
        v-if="loggedOut"
        class="wa-body-s wa-color-text-quiet"
      >
        {{ t('auth.verify.loginHint') }}
      </p>
      <NuxtLink
        to="/login"
        class="wa-link wa-text-center"
      >
        {{ t('auth.login.title') }}
      </NuxtLink>
    </div>
  </wa-card>
</template>
