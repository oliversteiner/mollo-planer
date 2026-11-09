<script setup lang="ts">
/** Vertical list of links of the sidebar; the page you are on is marked (aria-current). */
export interface NavItem {
  label: string
  /** Font Awesome name, for example "gauge-high". */
  icon: string
  to: string
  /** Only this exact path counts as the current page (not its sub pages). */
  exact?: boolean
}

defineProps<{
  items: readonly NavItem[]
  /** Name of the navigation for screen readers. */
  label?: string
}>()

const route = useRoute()

/** The link of the page you are on, or of a page above it (unless `exact`). */
function isCurrent(item: NavItem): boolean {
  return route.path === item.to || (!item.exact && route.path.startsWith(`${item.to}/`))
}
</script>

<template>
  <nav
    :aria-label="label"
    class="nav-list"
  >
    <ul>
      <li
        v-for="item in items"
        :key="item.to"
      >
        <NuxtLink
          :to="item.to"
          class="nav-link"
          :class="{ 'nav-link-active': isCurrent(item) }"
          :aria-current="isCurrent(item) ? 'page' : undefined"
        >
          <wa-icon :name="item.icon"></wa-icon>
          <span>{{ item.label }}</span>
        </NuxtLink>
      </li>
    </ul>
  </nav>
</template>

<style scoped>
ul {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-3xs);
  margin: 0;
  padding: 0;
  list-style: none;
}

.nav-link {
  display: flex;
  align-items: center;
  gap: var(--wa-space-s);
  padding: var(--wa-space-xs) var(--wa-space-s);
  border-radius: var(--wa-border-radius-m);
  color: var(--wa-color-text-normal);
  text-decoration: none;
}

.nav-link:hover {
  background-color: var(--wa-color-neutral-fill-quiet);
}

.nav-link:focus-visible {
  outline: var(--wa-focus-ring);
  outline-offset: var(--wa-focus-ring-offset);
}

.nav-link-active {
  background-color: var(--wa-color-brand-fill-quiet);
  color: var(--wa-color-brand-on-quiet);
  font-weight: var(--wa-font-weight-semibold);
}
</style>
