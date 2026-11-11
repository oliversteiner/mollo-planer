<script setup lang="ts">
import data from '~/data/versions.json'
import { CHANGE_KINDS, type AppVersion, type ChangeKind, currentVersion, groupChanges, isReleased, paragraphs, sortVersions, type VersionFile, versionNumber } from '~/utils/versions'

/**
 * Version page: one tab per version, the newest preselected. The text and the
 * release notes come from docs/entwicklung.md (app/data/versions.json).
 */
const { t, localeProperties } = useI18n()

useHead({ title: () => t('systemVersion.title') })

const versions = sortVersions((data as VersionFile).versions)
const current = currentVersion(versions)

/** The tab on show, by version number ("0.7"). */
const selected = ref<string | null>(current ? versionNumber(current) : null)

function onTabShow(event: CustomEvent<{ name: string }>): void {
  selected.value = event.detail.name
}

function groupsOf(version: AppVersion): { kind: ChangeKind, label: string, items: string[] }[] {
  const groups = groupChanges(version.changes)
  return CHANGE_KINDS
    .map(kind => ({ kind, label: t(`systemVersion.kinds.${kind}`), items: groups[kind] }))
    .filter(group => group.items.length > 0)
}
</script>

<template>
  <PageFrame
    :title="t('systemVersion.title')"
    :breadcrumbs="[{ label: t('nav.admin') }, { label: t('nav.version') }]"
    :description="t('systemVersion.subtitle')"
  >
    <p v-if="versions.length === 0">
      {{ t('systemVersion.empty') }}
    </p>

    <wa-tab-group
      v-else
      :active="selected ?? undefined"
      class="version-tabs"
      @wa-tab-show="onTabShow"
    >
      <wa-tab
        v-for="version in versions"
        :key="versionNumber(version)"
        slot="nav"
        :panel="versionNumber(version)"
      >
        {{ versionNumber(version) }}
      </wa-tab>

      <wa-tab-panel
        v-for="version in versions"
        :key="versionNumber(version)"
        :name="versionNumber(version)"
      >
        <article class="version-detail">
          <header class="version-detail__header">
            <h2 class="version-detail__title">
              {{ version.title }}
            </h2>
            <div class="version-detail__meta">
              <VersionBadge
                :version="version"
                :locale="localeProperties.language ?? 'de-CH'"
                :label="t('systemVersion.label')"
              />
              <span
                v-if="!isReleased(version)"
                class="version-detail__planned"
              >{{ t('systemVersion.planned') }}</span>
            </div>
          </header>

          <p
            v-for="(line, index) in paragraphs(version.description)"
            :key="index"
            class="version-detail__text"
          >
            {{ line }}
          </p>

          <section
            v-for="group in groupsOf(version)"
            :key="group.kind"
            class="version-detail__group"
          >
            <h3>{{ group.label }}</h3>
            <ul>
              <li
                v-for="(item, index) in group.items"
                :key="index"
              >
                {{ item }}
              </li>
            </ul>
          </section>
        </article>
      </wa-tab-panel>
    </wa-tab-group>
  </PageFrame>
</template>

<style scoped>
.version-detail {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-m);
  max-inline-size: 48rem;
}

.version-detail__header {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-3xs);
}

.version-detail__title {
  margin: 0;
  font-size: var(--wa-font-size-l);
}

.version-detail__meta {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: var(--wa-space-s);
}

.version-detail__planned {
  color: var(--wa-color-text-quiet);
  font-style: italic;
}

.version-detail__text {
  margin: 0;
  line-height: var(--wa-line-height-normal);
}

.version-detail__group h3 {
  margin: 0 0 var(--wa-space-2xs);
  font-size: var(--wa-font-size-s);
  font-weight: var(--wa-font-weight-semibold);
}

.version-detail__group ul {
  margin: 0;
  padding-inline-start: var(--wa-space-l);
}
</style>
