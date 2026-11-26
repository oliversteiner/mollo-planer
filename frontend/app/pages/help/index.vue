<script setup lang="ts">
import { MANUAL_AREAS, manualChapters } from '~/utils/manual'

/**
 * Help (Auftrag 54): the chapters of the manual by area, in the language of the app. Built from handbuch/ (scripts/manual.mjs).
 * The search and the «?» on every page come later (stage 4).
 */
const { t, locale } = useI18n()

useHead({ title: () => t('help.title') })

const groups = computed(() => {
  const chapters = manualChapters(locale.value)

  return MANUAL_AREAS
    .map(area => ({ area, chapters: chapters.filter(chapter => chapter.area === area).sort((a, b) => a.title.localeCompare(b.title)) }))
    .filter(group => group.chapters.length > 0)
})
</script>

<template>
  <PageFrame
    :title="t('help.title')"
    :description="t('help.intro')"
  >
    <EmptyState
      v-if="groups.length === 0"
      icon="circle-question"
      :title="t('help.none')"
    />
    <div
      v-else
      class="help-areas"
    >
      <wa-card
        v-for="group in groups"
        :key="group.area"
      >
        <h2
          slot="header"
          class="wa-heading-m"
        >
          {{ t(`help.areas.${group.area}`) }}
        </h2>
        <ul class="help-list wa-list-plain">
          <li
            v-for="chapter in group.chapters"
            :key="chapter.id"
          >
            <NuxtLink
              :to="`/help/${chapter.id}`"
              class="help-link"
            >
              {{ chapter.title }}
            </NuxtLink>
          </li>
        </ul>
      </wa-card>
    </div>
  </PageFrame>
</template>

<style scoped>
.help-areas {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(18rem, 1fr));
  gap: var(--wa-space-l);
  align-items: start;
}

.help-list {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-xs);
  margin: 0;
  padding: 0;
}

.help-link {
  color: inherit;
}
</style>
