<script setup lang="ts">
/** Choice of the interface language (remembered by the i18n module in a cookie). */
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
    :aria-label="t('settings.language')"
    size="small"
    @update:model-value="value => value && setLocale(value as 'de' | 'en')"
  />
</template>
