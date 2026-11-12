<script setup lang="ts">
import data from '~/data/versions.json'
import StepTimeline, { type TimelineEntry } from '~/components/StepTimeline.vue'
import { CHANGE_KINDS, type AppVersion, type ChangeKind, currentVersion, formatReleaseDate, groupChanges, isReleased, paragraphs, sortVersions, type VersionFile, versionNumber } from '~/utils/versions'

/**
 * Version page: a vertical timeline, one collapsible card per version. The
 * current version is open, the others are closed. Planned versions are hidden
 * until the filter is switched on. The text and the release notes come from
 * docs/entwicklung.md (app/data/versions.json).
 */
const { t, localeProperties } = useI18n()

useHead({ title: () => t('systemVersion.title') })

const locale = computed(() => localeProperties.value.language ?? 'de-CH')
const versions = sortVersions((data as VersionFile).versions)
const current = currentVersion(versions)
const currentNumber = current ? versionNumber(current) : null

/** Planned versions are off by default: the page shows what is released. */
const showPlanned = ref(false)

const visibleVersions = computed(() => versions.filter(version => showPlanned.value || isReleased(version)))

/** One timeline step per version, carrying the version for the card in its description. Newest first. */
type VersionStep = TimelineEntry & { version: AppVersion }

const steps = computed<VersionStep[]>(() => visibleVersions.value.slice().reverse().map((version) => {
  const released = isReleased(version)
  return {
    value: versionNumber(version),
    title: versionNumber(version),
    date: released ? (formatReleaseDate(version.releasedOn, locale.value) ?? undefined) : t('systemVersion.planned'),
    completed: released,
    version
  }
}))

function onFilterChange(event: Event): void {
  showPlanned.value = (event.target as HTMLElement & { checked: boolean }).checked
}

/** "Major release" for x.0, otherwise "Minor release". */
function releaseKind(version: AppVersion): string {
  return version.minor === 0 ? t('systemVersion.release.major') : t('systemVersion.release.minor')
}

function groupsOf(version: AppVersion): { kind: ChangeKind, label: string, items: string[] }[] {
  const groups = groupChanges(version.changes)
  return CHANGE_KINDS
    .map(kind => ({ kind, label: t(`systemVersion.kinds.${kind}`), items: groups[kind] }))
    .filter(group => group.items.length > 0)
}

/** Colour of the badge per kind of change. */
const KIND_VARIANT: Record<ChangeKind, 'success' | 'brand' | 'warning'> = {
  neu: 'success',
  verbessert: 'brand',
  behoben: 'warning'
}
</script>

<template>
  <PageFrame
    :title="t('systemVersion.title')"
    :breadcrumbs="[{ label: t('nav.admin') }, { label: t('nav.version') }]"
    :description="t('systemVersion.subtitle')"
  >
    <div class="version-filter">
      <wa-switch
        :checked="showPlanned"
        @change="onFilterChange"
      >
        {{ t('systemVersion.showPlanned') }}
      </wa-switch>
    </div>

    <p v-if="steps.length === 0">
      {{ t('systemVersion.empty') }}
    </p>

    <StepTimeline
      v-else
      :items="steps"
      :label="t('systemVersion.title')"
      :active="currentNumber ?? undefined"
    >
      <!-- Empty indicator: no check mark in the marker. -->
      <template #indicator></template>
      <template #title="{ item }">
        <strong>{{ item.title }}</strong>
      </template>
      <template #description="{ item }">
        <wa-details
          :open="item.version === current"
          appearance="outlined"
          class="version-card"
        >
          <span
            slot="summary"
            class="version-card__summary"
          >
            <span class="version-card__title">{{ item.version.title }}</span>
            <wa-badge
              pill
              appearance="outlined"
              variant="neutral"
            >
              {{ releaseKind(item.version) }}
            </wa-badge>
            <wa-badge
              pill
              :variant="isReleased(item.version) ? 'success' : 'neutral'"
            >
              {{ isReleased(item.version) ? t('systemVersion.released') : t('systemVersion.planned') }}
            </wa-badge>
          </span>

          <div class="version-card__body">
            <p
              v-for="(line, index) in paragraphs(item.version.description)"
              :key="index"
              class="version-card__text"
            >
              {{ line }}
            </p>

            <div
              v-if="groupsOf(item.version).length"
              class="version-groups"
            >
              <wa-card
                v-for="group in groupsOf(item.version)"
                :key="group.kind"
                appearance="plain"
                with-header
                class="version-group"
              >
                <wa-badge
                  slot="header"
                  pill
                  :variant="KIND_VARIANT[group.kind]"
                >
                  {{ group.label }}
                </wa-badge>
                <ul>
                  <li
                    v-for="(change, index) in group.items"
                    :key="index"
                  >
                    {{ change }}
                  </li>
                </ul>
              </wa-card>
            </div>
          </div>
        </wa-details>
      </template>
    </StepTimeline>
  </PageFrame>
</template>

<style scoped>
.version-filter {
  margin-block-end: var(--wa-space-m);
}

.version-card {
  inline-size: 100%;
}

.version-card__summary {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-xs);
}

.version-card__title {
  font-size: var(--wa-font-size-l);
  font-weight: var(--wa-font-weight-semibold);
  margin-inline-end: var(--wa-space-2xs);
}

.version-card__body {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-m);
  padding-block-start: var(--wa-space-s);
}

.version-card__text {
  margin: 0;
  line-height: var(--wa-line-height-normal);
}

/* Side by side when the card is wide enough, one under the other otherwise. */
.version-groups {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 14rem), 1fr));
  gap: var(--wa-space-s);
}

.version-group {
  inline-size: 100%;
}

.version-group ul {
  margin: 0;
  padding-inline-start: var(--wa-space-l);
}

.version-group li + li {
  margin-block-start: var(--wa-space-s);
}
</style>
