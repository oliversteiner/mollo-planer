<script setup lang="ts">
/** Icon button for light / dark (see composables/useColorScheme.ts). */
const { t } = useI18n()
const { dark, setPreference } = useColorScheme()

function toggle(): void {
  setPreference(dark.value ? 'light' : 'dark')
}
</script>

<template>
  <wa-button
    appearance="plain"
    variant="neutral"
    :aria-label="t('settings.colorMode')"
    @click="toggle"
  >
    <wa-icon
      :name="dark ? 'sun-bright' : 'moon'"
    ></wa-icon>
  </wa-button>
</template>
