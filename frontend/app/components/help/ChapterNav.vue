<script setup lang="ts">
import { manualOrder } from '~/utils/manual'

/**
 * The bar at the bottom of a chapter (Auftrag 66 E): previous chapter, «Inhalt», next chapter. It stays in view, also in
 * Zen (no `data-zen`), and is at the bottom and not at the top, because header and breadcrumb are at the top already
 * and the bottom is easy to reach on a narrow screen. The order is that of the overview, over the borders of the areas.
 * On a narrow screen only the direction and a shortened title show; the whole title is in the tooltip and the label.
 */
const props = defineProps<{ current: string }>()

const { t, locale } = useI18n()
const order = computed(() => manualOrder(locale.value))
const index = computed(() => order.value.findIndex(chapter => chapter.id === props.current))
const previous = computed(() => (index.value > 0 ? order.value[index.value - 1]! : null))
const next = computed(() => (index.value >= 0 && index.value < order.value.length - 1 ? order.value[index.value + 1]! : null))

const tocOpen = ref(false)
const tocButton = ref<HTMLElement | null>(null)

function follow(event: MouseEvent, id: string): void {
  if (!event.metaKey && !event.ctrlKey && !event.shiftKey && event.button === 0) {
    event.preventDefault()
    void navigateTo(`/help/${id}`)
  }
}

/** The focus goes to the chapter on the page once the contents are shown (the event of the drawer, no timer). */
function focusCurrent(event: Event): void {
  if (event.target === event.currentTarget) {
    document.querySelector<HTMLElement>('wa-drawer .help-toc-link[aria-current="page"]')?.focus()
  }
}

/** ... and back to «Inhalt» when they close. */
watch(tocOpen, async (open) => {
  await nextTick()

  if (!open) {
    tocButton.value?.focus()
  }
})
</script>

<template>
  <nav
    class="help-nav"
    :aria-label="t('help.nav.label')"
  >
    <a
      v-if="previous"
      id="help-previous"
      class="help-nav-link help-nav-previous"
      :href="`/help/${previous.id}`"
      :title="previous.title"
      :aria-label="`${t('help.nav.previous')}: ${previous.title}`"
      @click="follow($event, previous.id)"
    >
      <wa-icon name="arrow-left"></wa-icon>
      <span class="help-nav-text">
        <span class="help-nav-direction">{{ t('help.nav.previous') }}</span>
        <span class="help-nav-title">{{ previous.title }}</span>
      </span>
    </a>
    <span
      v-else
      class="help-nav-previous"
    />

    <wa-button
      id="help-toc"
      ref="tocButton"
      appearance="outlined"
      variant="neutral"
      @click="tocOpen = true"
    >
      <wa-icon
        slot="start"
        name="list"
      ></wa-icon>
      {{ t('help.nav.toc') }}
    </wa-button>

    <a
      v-if="next"
      id="help-next"
      class="help-nav-link help-nav-next"
      :href="`/help/${next.id}`"
      :title="next.title"
      :aria-label="`${t('help.nav.next')}: ${next.title}`"
      @click="follow($event, next.id)"
    >
      <span class="help-nav-text">
        <span class="help-nav-direction">{{ t('help.nav.next') }}</span>
        <span class="help-nav-title">{{ next.title }}</span>
      </span>
      <wa-icon name="arrow-right"></wa-icon>
    </a>
    <span
      v-else
      class="help-nav-next"
    />

    <AppDrawer
      v-model:open="tocOpen"
      :label="t('help.nav.tocTitle')"
      placement="start"
      @wa-after-show="focusCurrent"
    >
      <HelpToc
        :current="current"
        @navigate="tocOpen = false"
      />
    </AppDrawer>
  </nav>
</template>

<style scoped>
.help-nav {
  position: sticky;
  inset-block-end: 0;
  z-index: 1;
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
  align-items: center;
  gap: var(--wa-space-s);
  padding: var(--wa-space-xs) var(--wa-space-m);
  margin-block-start: var(--wa-space-xl);
  background: var(--wa-color-surface-default);
  border-block-start: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
}

.help-nav-link {
  display: flex;
  align-items: center;
  gap: var(--wa-space-xs);
  min-inline-size: 0;
  max-inline-size: 100%;
  padding: var(--wa-space-2xs) var(--wa-space-xs);
  border-radius: var(--wa-border-radius-m);
  color: inherit;
  text-decoration: none;
}

.help-nav-link:hover {
  background: var(--wa-color-neutral-fill-quiet);
}

.help-nav-link:focus-visible {
  outline: var(--wa-focus-ring);
  outline-offset: var(--wa-focus-ring-offset);
}

.help-nav-previous {
  justify-self: start;
}

.help-nav-next {
  justify-self: end;
}

.help-nav-text {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  min-inline-size: 0;
  text-align: start;
}

.help-nav-next .help-nav-text {
  align-items: flex-end;
  text-align: end;
}

.help-nav-direction {
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-xs);
}

.help-nav-title {
  max-inline-size: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

@media (max-width: 40em) {
  .help-nav {
    padding-inline: var(--wa-space-xs);
    gap: var(--wa-space-2xs);
  }

  /* Only the direction (the arrow) and the shortened title: the whole title is in the tooltip and the label. */
  .help-nav-direction {
    display: none;
  }
}
</style>
