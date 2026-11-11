<script setup lang="ts">
import { DB_TABLE_NAMES } from '~/utils/db-schema'

/**
 * Database documentation: a horizontal navigation with one page per table
 * (nested pages in system/db/). The current table is marked as the current page.
 * Admins only (the middleware checks USERS_MANAGE).
 */
definePageMeta({ middleware: 'admin' })

const { t } = useI18n()
const route = useRoute()

useHead({ title: () => t('doc.db.title') })
</script>

<template>
  <PageFrame
    :title="t('doc.db.title')"
    :breadcrumbs="[{ label: t('nav.admin') }, { label: t('doc.db.title') }]"
    :description="t('doc.db.subtitle', { count: DB_TABLE_NAMES.length })"
  >
    <nav
      class="db-nav"
      :aria-label="t('db.nav.label')"
    >
      <ul class="db-nav-list wa-list-plain">
        <li
          v-for="name in DB_TABLE_NAMES"
          :key="name"
        >
          <NuxtLink
            :to="`/system/db/${name}`"
            class="db-nav-link"
            :aria-current="route.params.table === name ? 'page' : undefined"
          >
            {{ t(`db.tables.${name}.title`) }}
          </NuxtLink>
        </li>
      </ul>
    </nav>

    <NuxtPage />
  </PageFrame>
</template>

<style scoped>
.db-nav-list {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wa-space-xs);
  border-block-end: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  padding-block-end: var(--wa-space-xs);
  margin-block-end: var(--wa-space-l);
}

.db-nav-list > li {
  flex: none;
}

.db-nav-link {
  display: block;
  padding: var(--wa-space-xs) var(--wa-space-s);
  border-radius: var(--wa-border-radius-m);
  color: var(--wa-color-text-quiet);
  text-decoration: none;
}

.db-nav-link:hover {
  color: var(--wa-color-text-normal);
  background-color: var(--wa-color-neutral-fill-quiet);
}

.db-nav-link[aria-current='page'] {
  color: var(--wa-color-brand-on-quiet);
  background-color: var(--wa-color-brand-fill-quiet);
  font-weight: var(--wa-font-weight-semibold);
}

.db-nav-link:focus-visible {
  outline: var(--wa-focus-ring);
  outline-offset: var(--wa-focus-ring-offset);
}
</style>
