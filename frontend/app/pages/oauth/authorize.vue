<script setup lang="ts">
/**
 * Entrance of the authorization flow of the planer as login service (OAuth/OpenID Connect). The API sends the
 * browser here when the person is not logged in (or not strongly): the login brings them back to this page, which
 * then hands the same request to the API again. Not a public page, so the global middleware does the login first.
 */
definePageMeta({ layout: 'auth' })

const { t } = useI18n()
const apiBase = useApiBase()

useHead({ title: () => t('oauth.authorize.title') })

onMounted(() => {
  window.location.replace(`${apiBase}/oauth/authorize${window.location.search}`)
})
</script>

<template>
  <wa-card>
    <h1
      slot="header"
      class="wa-heading-l"
    >
      {{ t('oauth.authorize.title') }}
    </h1>

    <div class="wa-stack wa-gap-m wa-align-items-center">
      <wa-spinner />
      <p class="wa-body-s wa-color-text-quiet">
        {{ t('oauth.authorize.text') }}
      </p>
    </div>
  </wa-card>
</template>
