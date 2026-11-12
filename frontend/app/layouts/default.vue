<script setup lang="ts">
import type { NavItem } from '~/components/NavList.vue'
import versionData from '~/data/versions.json'
import { currentVersion, type VersionFile } from '~/utils/versions'

/**
 * App shell on <wa-page>: the navigation is a sidebar on wide screens and a
 * drawer (with its own hamburger button) on narrow ones.
 */
const { t, localeProperties } = useI18n()
const { viewer, can, logout } = useViewer()

/** The current (highest released) version, shown under the user menu. */
const appVersion = currentVersion((versionData as VersionFile).versions)

const mainItems = computed<NavItem[]>(() => [
  { label: t('nav.dashboard'), icon: 'gauge-high', to: '/', exact: true },
  { label: t('nav.myTasks'), icon: 'square-check', to: '/my-tasks' },
  ...(can('USERS_MANAGE') ? [{ label: t('nav.assistant'), icon: 'wand-magic-sparkles', to: '/assistant' }] : [])
])

/** The section "System": the database documentation (admins), the status and the version (everyone), then administration. */
const adminItems = computed<NavItem[]>(() => [
  ...(can('USERS_MANAGE') ? [{ label: t('nav.database'), icon: 'database', to: '/system/db' }] : []),
  ...(can('USERS_MANAGE') ? [{ label: t('nav.rights'), icon: 'list-check', to: '/system/rechte' }] : []),
  { label: t('nav.status'), icon: 'heart-pulse', to: '/system/status' },
  { label: t('nav.version'), icon: 'code-branch', to: '/system/version' },
  ...(can('USERS_MANAGE') ? [{ label: t('nav.users'), icon: 'users', to: '/admin/users' }] : []),
  ...(can('LABEL_SETS_MANAGE') ? [{ label: t('nav.labelSets'), icon: 'tags', to: '/label-sets' }] : []),
  ...(can('USERS_MANAGE') ? [{ label: t('nav.appSettings'), icon: 'sliders', to: '/admin/settings' }] : [])
])

function onUserMenu(event: CustomEvent<{ item: { value: string } }>): void {
  if (event.detail.item.value === 'profile') {
    void navigateTo('/profile')
  } else if (event.detail.item.value === 'logout') {
    void logout()
  }
}

/** Keyboard users jump past the sidebar to the content of the page. */
function skipToContent(): void {
  document.getElementById('main-content')?.focus()
}
</script>

<template>
  <wa-page
    mobile-breakpoint="1024px"
    class="app-shell"
  >
    <a
      slot="skip-to-content"
      href="#main-content"
      class="skip-link"
      @click.prevent="skipToContent"
    >{{ t('nav.skipToContent') }}</a>

    <div
      slot="navigation-header"
      class="app-header"
    >
      <NuxtLink
        to="/"
        class="app-name"
      >
        <AppLogo />
      </NuxtLink>
    </div>

    <div
      slot="navigation"
      class="app-navigation"
    >
      <NavList
        :items="mainItems"
        :label="t('nav.main')"
      />
      <SidebarProjectList />
      <!-- Closed by default; the user opens it with the summary. -->
      <wa-details
        v-if="adminItems.length"
        class="app-admin"
        appearance="plain"
      >
        <span
          slot="summary"
          class="app-heading"
        >{{ t('nav.admin') }}</span>
        <NavList
          :items="adminItems"
          :label="t('nav.admin')"
        />
      </wa-details>
    </div>

    <div
      slot="navigation-footer"
      class="app-footer"
    >
      <wa-dropdown
        v-if="viewer"
        placement="top-start"
        @wa-select="onUserMenu"
      >
        <wa-button
          slot="trigger"
          appearance="plain"
          variant="neutral"
          with-caret
          class="user-menu-button"
          :aria-label="t('auth.userMenu')"
        >
          <UserLine
            :name="viewer.name"
            :src="viewer.avatarUrl"
            :description="viewer.email"
            size="sm"
          />
        </wa-button>
        <h3>{{ viewer.email }}</h3>
        <wa-dropdown-item value="profile">
          <wa-icon
            slot="icon"
            name="user-gear"
          ></wa-icon>
          {{ t('profile.title') }}
        </wa-dropdown-item>
        <wa-dropdown-item value="logout">
          <wa-icon
            slot="icon"
            name="right-from-bracket"
          ></wa-icon>
          {{ t('auth.logout') }}
        </wa-dropdown-item>
      </wa-dropdown>
      <NuxtLink
        v-if="appVersion"
        to="/system/version"
        class="app-version"
      >
        <VersionBadge
          :version="appVersion"
          :locale="localeProperties.language ?? 'de-CH'"
          :label="t('systemVersion.label')"
        />
      </NuxtLink>
    </div>

    <main
      id="main-content"
      tabindex="-1"
    >
      <slot />
    </main>

    <PasswordPromptModal />
  </wa-page>
</template>

<style scoped>
/*
 * The sidebar stands out: it keeps the surface of cards and panels, the
 * content area behind the pages is one step lower.
 */
.app-shell {
  --menu-width: 16rem;

  background-color: var(--wa-color-surface-lowered);
}

/* On small screens the navigation is a drawer: no column may stay reserved for it. */
.app-shell[view='mobile'] {
  --menu-width: auto;
}

.app-shell::part(menu) {
  background-color: var(--wa-color-surface-default);
  border-inline-end: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
}

/* The logo sits in the middle of the header of the sidebar. */
/* wa-page makes the header slot a column; the logo must be centred in a row. */
.app-header {
  display: flex;
  flex-direction: row;
  justify-content: center;
}

/* Logo in the colour of the brand: the colour of the SVG in light mode, a lighter shade in dark mode. */
.app-name {
  display: inline-block;
  color: #612692;
  text-decoration: none;
}

:root.wa-dark .app-name {
  color: var(--wa-color-brand-on-quiet);
}

.app-name:focus-visible {
  outline: var(--wa-focus-ring);
  outline-offset: var(--wa-focus-ring-offset);
}

.app-navigation {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-xl);
}

.app-heading {
  margin: 0;
  padding: 0 var(--wa-space-s);
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-xs);
  font-weight: var(--wa-font-weight-semibold);
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.app-admin {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-xs);
}

/* In the drawer (small screens) the footer is placed at the right; it takes the whole width. */
.app-footer {
  flex: 1;
  inline-size: 100%;
}

.user-menu-button {
  inline-size: 100%;
  text-align: start;
}

/* Avatar, name and second line from the left; the caret stays at the right. */
.user-menu-button::part(base) {
  justify-content: flex-start;
}

.user-menu-button::part(label) {
  display: flex;
  flex: 1;
  min-inline-size: 0;
  text-align: start;
}

/* Under the user menu, aligned with its text. A link to the version page. */
.app-version {
  display: block;
  margin-block-start: var(--wa-space-2xs);
  padding-inline: var(--wa-space-s);
  border-radius: var(--wa-border-radius-m);
  color: inherit;
  text-decoration: none;
}

.app-version:hover :deep(.version-badge) {
  color: var(--wa-color-text-normal);
  text-decoration: underline;
}

.app-version:focus-visible {
  outline: var(--wa-focus-ring);
  outline-offset: var(--wa-focus-ring-offset);
}

wa-dropdown:has(> .user-menu-button) {
  display: block;
  inline-size: 100%;
}

/*
 * wa-page gives a slotted <main> a large padding (--wa-space-3xl). The pages
 * bring their own spacing (PageFrame), and a page that fills the screen must
 * not get taller than it, so the padding goes.
 */
main {
  padding: 0;
}

main:focus {
  outline: none;
}

.skip-link:not(:focus) {
  position: absolute;
  inline-size: 1px;
  block-size: 1px;
  overflow: hidden;
  clip-path: inset(50%);
  white-space: nowrap;
}

.skip-link:focus {
  position: fixed;
  inset-block-start: var(--wa-space-xs);
  inset-inline-start: var(--wa-space-xs);
  z-index: 1000;
  padding: var(--wa-space-xs) var(--wa-space-m);
  border-radius: var(--wa-border-radius-m);
  background-color: var(--wa-color-brand-fill-loud);
  color: var(--wa-color-brand-on-loud);
}
</style>
