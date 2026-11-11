<script setup lang="ts">
import { formatReleaseDate, type AppVersion, versionNumber } from '~/utils/versions'

/**
 * Version number and release date, e.g. "Version 0.7 · 10. Nov. 2026". Without a
 * release date (no git tag yet) only the number is shown. Ported from mollo-version
 * (frontend/src/components/VersionBadge.vue).
 */
const props = defineProps<{
  version: AppVersion
  /** BCP 47 tag for the date format, e.g. "de-CH". */
  locale: string
  /** Text before the number, translated by the caller. */
  label: string
}>()

const number = computed(() => versionNumber(props.version))
const date = computed(() => formatReleaseDate(props.version.releasedOn, props.locale))
</script>

<template>
  <span class="version-badge">
    <span class="version-badge__number">{{ label }} {{ number }}</span>
    <template v-if="date">
      <span
        class="version-badge__separator"
        aria-hidden="true"
      > · </span>
      <time
        class="version-badge__date"
        :datetime="version.releasedOn ?? undefined"
      >{{ date }}</time>
    </template>
  </span>
</template>

<style scoped>
/* The colour comes from the surrounding text, so the badge works in the sidebar and in both themes. */
.version-badge {
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
  white-space: nowrap;
}

.version-badge__number {
  font-weight: var(--wa-font-weight-semibold);
}
</style>
