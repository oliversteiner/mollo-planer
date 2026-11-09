<script setup lang="ts">
/**
 * Choice of the appearance: system, light or dark. While Nuxt UI is still
 * in the app, the colour mode module keeps the setting (see ColorModeSwitch).
 */
defineProps<{ label?: string }>()

const { t } = useI18n()
const colorMode = useColorMode()

const items = computed(() => (['system', 'light', 'dark'] as const).map(mode => ({ label: t(`settings.colorModes.${mode}`), value: mode })))
</script>

<template>
  <FormSelect
    :model-value="colorMode.preference"
    :items="items"
    :label="label"
    :aria-label="label ? undefined : t('settings.colorMode')"
    @update:model-value="value => value && (colorMode.preference = value)"
  />
</template>
