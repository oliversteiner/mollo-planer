<script setup lang="ts">
/**
 * Icon button for light / dark. While Nuxt UI is still in the app, the
 * colour mode module is the one source of truth; plugins/webawesome.client.ts
 * mirrors it as wa-dark / wa-light on <html>.
 */
const { t } = useI18n()
const colorMode = useColorMode()

const dark = computed(() => colorMode.value === 'dark')

function toggle(): void {
  colorMode.preference = dark.value ? 'light' : 'dark'
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
