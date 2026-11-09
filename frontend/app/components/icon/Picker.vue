<script setup lang="ts">
import type { IconEntry } from '~/utils/icon-search'
import { searchIcons } from '~/utils/icon-search'

/**
 * Choose a Font Awesome icon (Duotone Light) by name, or none. The
 * catalogue is loaded once, when the picker is opened for the first time.
 */
defineProps<{
  /** Icon shown when none is chosen (the default of the level). */
  fallback: string
}>()

const model = defineModel<string | null>({ default: null })

const { t } = useI18n()
const baseURL = useRuntimeConfig().app.baseURL

const catalog = useState<IconEntry[] | null>('iconCatalog', () => null)
const open = ref(false)
const query = ref('')
const gridId = useId()
const loading = ref(false)

watch(open, async (isOpen) => {
  if (!isOpen || catalog.value) {
    return
  }
  loading.value = true
  try {
    const data = await $fetch<{ icons: IconEntry[] }>(`${baseURL}icons/catalog.json`)
    catalog.value = data.icons
  } finally {
    loading.value = false
  }
})

const results = computed(() => searchIcons(catalog.value ?? [], query.value))

function choose(name: string | null): void {
  model.value = name
  open.value = false
  query.value = ''
}
</script>

<template>
  <div class="icon-picker">
    <wa-button
      appearance="outlined"
      variant="neutral"
      :aria-label="t('icons.choose')"
      @click="open = true"
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

    <AppDialog
      v-model:open="open"
      :label="t('icons.title')"
    >
      <div class="wa-stack wa-gap-s">
        <FormInput
          v-model="query"
          :placeholder="t('icons.search')"
          type="search"
          autocomplete="off"
          :label="t('icons.search')"
          hide-label
          autofocus
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
          {{ query ? t('icons.results', { count: results.length }) : t('icons.suggestions') }}
        </p>

        <ul
          class="icon-picker-grid wa-list-plain"
          :aria-label="t('icons.title')"
        >
          <li
            v-for="[name, label] in results"
            :key="name"
          >
            <wa-button
              :id="`${gridId}-${name}`"
              :appearance="model === name ? 'filled' : 'plain'"
              variant="neutral"
              :aria-label="label"
              :aria-pressed="model === name"
              class="icon-picker-choice"
              @click="choose(name)"
            >
              <ItemIcon
                :name="name"
                :fallback="fallback"
                class="icon-picker-icon"
              />
            </wa-button>
            <wa-tooltip :for="`${gridId}-${name}`">
              {{ label }} ({{ name }})
            </wa-tooltip>
          </li>
        </ul>
        <EmptyState
          v-if="!loading && query && !results.length"
          icon="icons"
          :description="t('icons.noResults')"
          size="sm"
          bare
        />
      </div>
    </AppDialog>
  </div>
</template>

<style scoped>
.icon-picker {
  display: flex;
  align-items: center;
  gap: var(--wa-space-xs);
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
</style>
