<script setup lang="ts">
import { manualGroups } from '~/utils/manual'

/**
 * The table of contents of the help (Auftrag 66 E): all chapters by area, the one on the page marked. Used in the drawer
 * («Inhalt») and as the column next to the text on a wide screen. A chapter the person lacks the right for stays in,
 * as in the overview.
 */
defineProps<{ current: string }>()

const emit = defineEmits<{ navigate: [] }>()

const { t, locale } = useI18n()
const groups = computed(() => manualGroups(locale.value))

/** A link stays within the app (no full page load); the new tab, Ctrl and Cmd keep their meaning. */
function follow(event: MouseEvent, id: string): void {
  emit('navigate')

  if (!event.metaKey && !event.ctrlKey && !event.shiftKey && event.button === 0) {
    event.preventDefault()
    void navigateTo(`/help/${id}`)
  }
}
</script>

<template>
  <nav
    class="help-toc"
    :aria-label="t('help.nav.tocTitle')"
  >
    <section
      v-for="group in groups"
      :key="group.area"
      class="help-toc-group"
    >
      <h2 class="help-toc-area">
        {{ t(`help.areas.${group.area}`) }}
      </h2>
      <ul class="wa-list-plain help-toc-list">
        <li
          v-for="chapter in group.chapters"
          :key="chapter.id"
        >
          <a
            :href="`/help/${chapter.id}`"
            class="help-toc-link"
            :aria-current="chapter.id === current ? 'page' : undefined"
            @click="follow($event, chapter.id)"
          >{{ chapter.title }}</a>
        </li>
      </ul>
    </section>
  </nav>
</template>

<style scoped>
.help-toc {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-m);
}

.help-toc-area {
  margin: 0 0 var(--wa-space-3xs);
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-xs);
  font-weight: var(--wa-font-weight-semibold);
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.help-toc-list {
  display: flex;
  flex-direction: column;
  margin: 0;
  padding: 0;
}

.help-toc-link {
  display: block;
  padding: var(--wa-space-3xs) var(--wa-space-xs);
  border-radius: var(--wa-border-radius-m);
  color: inherit;
  text-decoration: none;
}

.help-toc-link:hover {
  background: var(--wa-color-neutral-fill-quiet);
}

.help-toc-link[aria-current='page'] {
  background: var(--wa-color-brand-fill-quiet);
  font-weight: var(--wa-font-weight-semibold);
}
</style>
