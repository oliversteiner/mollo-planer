<script setup lang="ts">
/**
 * Pages of a project as tabs below the page header: links (each page has
 * its own address), the current one underlined. "Tasks" keeps the address
 * /kanban, the mails of the backend link to it.
 */
const props = defineProps<{
  projectId: string
  /** Show "Settings" (only when the viewer may change or delete the project). */
  withSettings?: boolean
}>()

const { t } = useI18n()
const route = useRoute()

const tabs = computed(() => {
  const base = `/projects/${props.projectId}`

  return [
    { label: t('project.tabs.milestones'), to: base, exact: true },
    { label: t('project.tabs.todos'), to: `${base}/todos` },
    { label: t('project.tabs.tasks'), to: `${base}/kanban` },
    { label: t('project.tabs.members'), to: `${base}/members` },
    ...(props.withSettings ? [{ label: t('project.tabs.settings'), to: `${base}/settings` }] : [])
  ]
})

function isCurrent(tab: { to: string, exact?: boolean }): boolean {
  return route.path === tab.to || (!tab.exact && route.path.startsWith(`${tab.to}/`))
}
</script>

<template>
  <nav
    class="project-tabs"
    :aria-label="t('project.tabs.label')"
  >
    <NuxtLink
      v-for="tab in tabs"
      :key="tab.to"
      :to="tab.to"
      class="project-tab"
      :aria-current="isCurrent(tab) ? 'page' : undefined"
    >
      {{ tab.label }}
    </NuxtLink>
  </nav>
</template>

<style scoped>
.project-tabs {
  display: flex;
  gap: var(--wa-space-l);
  overflow: auto hidden;
  /* The base line is a shadow, so the underline of the current tab can lie on it without overflowing. */
  box-shadow: inset 0 calc(var(--wa-border-width-s) * -1) 0 var(--wa-color-surface-border);
  scrollbar-width: thin;
}

.project-tab {
  flex: none;
  padding-block: var(--wa-space-s);
  border-block-end: var(--wa-border-width-m) solid transparent;
  color: var(--wa-color-text-quiet);
  font-weight: var(--wa-font-weight-semibold);
  text-decoration: none;
  white-space: nowrap;
}

.project-tab:hover {
  color: var(--wa-color-text-normal);
}

.project-tab:focus-visible {
  outline: var(--wa-focus-ring);
  outline-offset: var(--wa-focus-ring-offset);
}

.project-tab[aria-current='page'] {
  border-block-end-color: var(--wa-color-brand-fill-loud);
  color: var(--wa-color-brand-on-quiet);
}
</style>
