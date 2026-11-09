<script setup lang="ts">
import type { NavItem } from '~/components/NavList.vue'

/**
 * App shell on <wa-page>: the navigation is a sidebar on wide screens and a
 * drawer (with its own hamburger button) on narrow ones.
 */
const { t } = useI18n()
const { viewer, logout } = useViewer()

const mainItems = computed<NavItem[]>(() => [
  { label: t('nav.dashboard'), icon: 'gauge-high', to: '/', exact: true },
  { label: t('nav.myTasks'), icon: 'square-check', to: '/my-tasks' },
  ...(viewer.value?.isAdmin ? [{ label: t('nav.assistant'), icon: 'wand-magic-sparkles', to: '/assistant' }] : [])
])

const adminItems = computed<NavItem[]>(() => (viewer.value?.isAdmin
  ? [
      { label: t('nav.users'), icon: 'users', to: '/admin/users' },
      { label: t('nav.appSettings'), icon: 'sliders', to: '/admin/settings' }
    ]
  : []))

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

    <div slot="navigation-header">
      <NuxtLink
        to="/"
        class="app-name"
      >
        {{ t('app.name') }}
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
      <section
        v-if="adminItems.length"
        class="app-admin"
        :aria-label="t('nav.admin')"
      >
        <h2 class="app-heading">
          {{ t('nav.admin') }}
        </h2>
        <NavList :items="adminItems" />
      </section>
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
            :description="viewer.isAdmin ? t('admin.users.admin') : viewer.email"
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

.app-name {
  font-size: var(--wa-font-size-l);
  font-weight: var(--wa-font-weight-bold);
  color: var(--wa-color-text-normal);
  text-decoration: none;
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
