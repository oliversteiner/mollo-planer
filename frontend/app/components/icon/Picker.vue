<script setup lang="ts">
import type { IconEntry } from '~/utils/icon-search'
import { searchIcons } from '~/utils/icon-search'

/**
 * Choose a Font Awesome icon (Duotone Light) by name, or none. The
 * catalogue is loaded once, when the picker is opened for the first time.
 * Few requests (Auftrag 36 B): the search waits 250 ms after the last key and starts at two characters, a tile loads its
 * icon only when it comes into view, and one tooltip serves all tiles.
 *
 * The choice opens as a panel under the button, inside the form that holds it, not as a second dialog on top of the
 * dialog of the form (Auftrag 55, rule R11). Escape closes the tooltip first, then the panel, never the dialog of the form.
 */
defineProps<{
  /** Icon shown when none is chosen (the default of the level). */
  fallback: string
}>()

const model = defineModel<string | null>({ default: null })

const { t } = useI18n()
const config = useRuntimeConfig()
const iconBase = iconBaseUrl(config.app.baseURL, config.public.iconVersion)

const catalog = useState<IconEntry[] | null>('iconCatalog', () => null)
const open = ref(false)
const panel = ref<HTMLElement | null>(null)
const opener = ref<HTMLElement | null>(null)
const query = ref('')
const gridId = useId()
const loading = ref(false)

const MIN_QUERY = 2
const { value: searched, flush } = useDebouncedRef(query, 250)
/** One character is no search yet: the suggestions stay. */
const effectiveQuery = computed(() => searched.value.trim().length >= MIN_QUERY ? searched.value : '')

const grid = ref<HTMLElement | null>(null)
const { isSeen, track, reset } = useVisibleOnce(grid)

const tip = ref<(HTMLElement & { for: string | null, open: boolean }) | null>(null)
const tipText = ref('')
/** The tile the tooltip belongs to, while it is open. */
const tipTile = ref<HTMLElement | null>(null)

watch(open, async (isOpen) => {
  if (!isOpen) {
    hideTip()
    reset()
  }
  if (!isOpen || catalog.value) {
    return
  }
  loading.value = true
  try {
    const data = await $fetch<{ icons: IconEntry[] }>(`${iconBase}catalog.json`)
    catalog.value = data.icons
  } finally {
    loading.value = false
  }
})

const results = computed(() => searchIcons(catalog.value ?? [], effectiveQuery.value))

function close(): void {
  open.value = false
  query.value = ''
  flush()
  // The focus goes back to the button that opened the panel.
  void nextTick(() => opener.value?.focus())
}

function choose(name: string | null): void {
  model.value = name
  close()
}

/** The search takes the focus when the panel opens: the person types at once. */
watch(open, async (isOpen) => {
  if (isOpen) {
    await nextTick()
    panel.value?.querySelector<HTMLElement>('wa-input')?.focus()
  }
})

/** Escape: the tooltip first, then the panel. It never reaches the dialog of the form. */
function onEscape(event: KeyboardEvent): void {
  event.stopPropagation()
  event.preventDefault()

  if (tip.value?.open) {
    hideTip()

    return
  }
  close()
}

/** The tooltip follows the tile that is hovered or focused; one element for all of them. */
function showTip(event: Event): void {
  const tile = (event.target as HTMLElement | null)?.closest<HTMLElement>('[data-icon]')

  if (!tile || !tip.value) {
    return
  }

  tipText.value = `${tile.dataset.label} (${tile.dataset.icon})`
  tipTile.value = tile
  tip.value.for = tile.id
  tip.value.open = true
}

function hideTip(): void {
  tipTile.value = null
  if (tip.value) {
    tip.value.open = false
  }
}

/**
 * The list scrolls: the tooltip stays with its tile (the popup follows the anchor) and closes only when the tile has left the
 * visible part of the list. A focus that scrolls the list to its tile keeps the tooltip, as it should.
 */
function scrolled(): void {
  const tile = tipTile.value
  const list = grid.value

  if (!tile || !list || !tip.value?.open) {
    return
  }

  const box = tile.getBoundingClientRect()
  const area = list.getBoundingClientRect()

  if (box.top < area.top || box.bottom > area.bottom) {
    hideTip()
  }
}

/** The pointer leaves the list: a tile that still has the keyboard focus keeps its tooltip. */
function pointerLeft(): void {
  const focused = grid.value?.contains(document.activeElement) ? document.activeElement : null

  if (focused?.closest('[data-icon]')) {
    showTip({ target: focused } as unknown as Event)

    return
  }

  hideTip()
}
</script>

<template>
  <div class="icon-picker">
    <wa-button
      ref="opener"
      appearance="outlined"
      variant="neutral"
      :aria-label="t('icons.choose')"
      :aria-expanded="open"
      @click="open = !open"
    >
      <ItemIcon
        slot="start"
        :name="model"
        :fallback="fallback"
        class="icon-picker-current"
      />
      {{ model ?? t('icons.default') }}
    </wa-button>
    <wa-button
      v-if="model"
      appearance="plain"
      variant="neutral"
      size="s"
      :aria-label="t('icons.remove')"
      @click="choose(null)"
    >
      <wa-icon name="xmark"></wa-icon>
    </wa-button>

    <div
      v-if="open"
      ref="panel"
      class="icon-picker-panel"
      role="group"
      :aria-label="t('icons.title')"
      @keydown.esc="onEscape"
    >
      <div class="wa-stack wa-gap-s">
        <div class="icon-picker-head">
          <strong>{{ t('icons.title') }}</strong>
          <wa-button
            appearance="plain"
            variant="neutral"
            size="s"
            :aria-label="t('icons.close')"
            @click="close"
          >
            <wa-icon name="xmark"></wa-icon>
          </wa-button>
        </div>
        <FormInput
          v-model="query"
          :placeholder="t('icons.search')"
          type="search"
          autocomplete="off"
          :label="t('icons.search')"
          hide-label
        >
          <template #start>
            <wa-icon
              slot="start"
              name="magnifying-glass"
            ></wa-icon>
          </template>
        </FormInput>
        <p class="icon-picker-count">
          <wa-spinner v-if="loading"></wa-spinner>
          {{ effectiveQuery ? t('icons.results', { count: results.length }) : t('icons.suggestions') }}
        </p>

        <ul
          ref="grid"
          class="icon-picker-grid wa-list-plain"
          :aria-label="t('icons.title')"
          @pointerover="showTip"
          @focusin="showTip"
          @pointerleave="pointerLeft"
          @focusout="hideTip"
          @scroll.passive="scrolled"
        >
          <li
            v-for="[name, label] in results"
            :key="name"
            :ref="element => track(element, name)"
          >
            <wa-button
              :id="`${gridId}-${name}`"
              :data-icon="name"
              :data-label="label"
              :appearance="model === name ? 'filled' : 'plain'"
              variant="neutral"
              :aria-label="label"
              :aria-pressed="model === name"
              class="icon-picker-choice"
              @click="choose(name)"
            >
              <ItemIcon
                v-if="isSeen(name)"
                :name="name"
                :fallback="fallback"
                class="icon-picker-icon"
              />
              <span
                v-else
                class="icon-picker-icon icon-picker-pending"
                aria-hidden="true"
              />
            </wa-button>
          </li>
        </ul>
        <!--
          Deliberate deviation (review of Auftrag 36, Abnahme of PR #51): ONE tooltip for all tiles instead of one per tile.
          A search can give 120 tiles, and 120 tooltip elements cost time on every search. Every tile keeps its own
          aria-label (the label of the icon), so a screen reader and the focus name a tile without the tooltip. The tooltip
          follows the hovered or focused tile (showTip) and closes on focus-out. Escape closes the tooltip first (wa-tooltip
          handles it; the picker stays open, a second Escape closes it), and the tooltip stays with its tile while the list
          scrolls (scrolled).
        -->
        <wa-tooltip
          ref="tip"
          trigger="manual"
          placement="top"
        >
          {{ tipText }}
        </wa-tooltip>
        <EmptyState
          v-if="!loading && effectiveQuery && !results.length"
          icon="icons"
          :description="t('icons.noResults')"
          size="sm"
          bare
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.icon-picker {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-xs);
}

.icon-picker-panel {
  flex-basis: 100%;
  padding: var(--wa-space-s);
  border: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  border-radius: var(--wa-border-radius-m);
  background-color: var(--wa-color-surface-default);
}

.icon-picker-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.icon-picker-current {
  font-size: var(--wa-font-size-xl);
  color: var(--wa-color-brand-on-quiet);
}

.icon-picker-count {
  display: flex;
  align-items: center;
  gap: var(--wa-space-xs);
  margin: 0;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-xs);
}

.icon-picker-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(3rem, 1fr));
  gap: var(--wa-space-3xs);
  max-block-size: 20rem;
  margin: 0;
  padding: 0;
  overflow-y: auto;
}

.icon-picker-choice {
  inline-size: 100%;
}

.icon-picker-icon {
  font-size: var(--wa-font-size-2xl);
}

/* Keeps the height of a tile whose icon is not loaded yet (the icon is 1em high, its width follows the drawing), so the grid
   hardly moves when the icon arrives. */
.icon-picker-pending {
  display: inline-block;
  inline-size: 1em;
  block-size: 1em;
  vertical-align: -0.125em;
}
</style>
