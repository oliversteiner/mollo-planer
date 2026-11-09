<script setup lang="ts">
import type { ColorPreference } from '~/composables/useColorScheme'

/** Choice of the appearance: system, light or dark (see composables/useColorScheme.ts). */
defineProps<{ label?: string }>()

const { t } = useI18n()
const { preference, setPreference } = useColorScheme()

const items = computed(() => (['system', 'light', 'dark'] as const).map(mode => ({ label: t(`settings.colorModes.${mode}`), value: mode })))
</script>

<template>
  <FormSelect
    :model-value="preference"
    :items="items"
    :label="label ?? t('settings.colorMode')"
    :hide-label="!label"
    @update:model-value="value => value && setPreference(value as ColorPreference)"
  />
</template>
