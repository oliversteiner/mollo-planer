<script setup lang="ts">
/**
 * Choice of the interface language (remembered by the i18n module in a
 * cookie). With `label` the field shows it; without, it is named for screen
 * readers only.
 */
defineProps<{ label?: string }>()

const { t, locale, locales, setLocale } = useI18n()

const items = computed(() => locales.value.map(entry => ({
  label: typeof entry === 'string' ? entry : (entry.name ?? entry.code),
  value: typeof entry === 'string' ? entry : entry.code
})))
</script>

<template>
  <FormSelect
    :model-value="locale"
    :items="items"
    :label="label ?? t('settings.language')"
    :hide-label="!label"
    size="s"
    @update:model-value="value => value && setLocale(value as 'de' | 'en')"
  />
</template>
