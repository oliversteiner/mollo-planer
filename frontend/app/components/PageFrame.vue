<script setup lang="ts">
/**
 * Frame of a page: the page header (breadcrumb on top, large title at the
 * left, actions at the right, an optional line below the title), an
 * optional navigation below the header (slot `nav`, for example the tabs of
 * a project) and the content. The sidebar and the scrolling belong to
 * <wa-page> (layouts/default.vue).
 *
 * Actions come from the slot `actions` or, from a child page, through
 * <Teleport to="#page-actions" defer>.
 */
export interface Crumb {
  label: string
  /** Without a link: the current page (the last crumb). */
  to?: string
}

defineProps<{
  title: string
  breadcrumbs?: readonly Crumb[]
  /** Line under the title, as text. */
  description?: string | null
  /** The page fills the screen height and its content does not scroll (for example a split view). */
  fill?: boolean
}>()

const { t } = useI18n()

/** Breadcrumb links stay within the app (no full page load). */
function follow(event: MouseEvent, to: string): void {
  if (!event.metaKey && !event.ctrlKey && !event.shiftKey && event.button === 0) {
    event.preventDefault()
    void navigateTo(to)
  }
}
</script>

<template>
  <div
    class="page-frame"
    :data-fill="fill ? '' : undefined"
  >
    <header class="page-header">
      <wa-breadcrumb
        v-if="breadcrumbs?.length"
        :label="t('nav.breadcrumb')"
        class="page-breadcrumb"
      >
        <wa-breadcrumb-item
          v-for="(crumb, index) in breadcrumbs"
          :key="index"
          :href="crumb.to"
          @click="crumb.to && follow($event, crumb.to)"
        >
          {{ crumb.label }}
        </wa-breadcrumb-item>
      </wa-breadcrumb>

      <div class="page-title-row">
        <div class="page-title-group">
          <h1 class="page-title">
            {{ title }}
          </h1>
          <slot name="title-end" />
        </div>
        <div
          id="page-actions"
          class="page-actions"
        >
          <slot name="actions" />
        </div>
      </div>

      <p
        v-if="description"
        class="page-description"
      >
        {{ description }}
      </p>
    </header>

    <div
      v-if="$slots.nav"
      class="page-nav"
    >
      <slot name="nav" />
    </div>

    <div class="page-body">
      <slot />
    </div>
  </div>
</template>

<style scoped>
.page-frame[data-fill] {
  display: flex;
  flex-direction: column;
  /* Screen height minus what wa-page shows above the content (on small screens the bar with the menu button). */
  block-size: calc(100dvh - var(--banner-height, 0px) - var(--header-height, 0px) - var(--subheader-height, 0px));
}

.page-frame[data-fill] .page-body {
  flex: 1;
  min-block-size: 0;
  padding: 0;
  overflow: hidden;
}

.page-header {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-xs);
  padding: var(--wa-space-xl) var(--wa-space-2xl) var(--wa-space-l);
}

.page-breadcrumb {
  font-size: var(--wa-font-size-s);
}

.page-title-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--wa-space-m);
}

.page-title-group {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-s);
  min-inline-size: 0;
}

.page-title {
  margin: 0;
  font-size: var(--wa-font-size-2xl);
  font-weight: var(--wa-font-weight-semibold);
  line-height: var(--wa-line-height-condensed);
}

.page-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-s);
}

.page-actions:empty {
  display: none;
}

.page-description {
  margin: 0;
  max-inline-size: 65ch;
  color: var(--wa-color-text-quiet);
}

.page-nav {
  padding-inline: var(--wa-space-2xl);
}

.page-body {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-l);
  padding: var(--wa-space-l) var(--wa-space-2xl) var(--wa-space-2xl);
}

@media (max-width: 40em) {
  .page-header,
  .page-nav,
  .page-body {
    padding-inline: var(--wa-space-m);
  }
}
</style>
