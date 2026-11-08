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
  <div class="flex items-center gap-2">
    <UButton
      color="neutral"
      variant="outline"
      :aria-label="t('icons.choose')"
      @click="open = true"
    >
      <ItemIcon
        :name="model"
        :fallback="fallback"
        class="text-xl text-primary"
      />
      <span>{{ model ?? t('icons.default') }}</span>
    </UButton>
    <UButton
      v-if="model"
      icon="i-fadl-xmark"
      color="neutral"
      variant="ghost"
      size="sm"
      :aria-label="t('icons.remove')"
      @click="choose(null)"
    />

    <UModal
      v-model:open="open"
      :title="t('icons.title')"
    >
      <template #body>
        <UInput
          v-model="query"
          icon="i-fadl-magnifying-glass"
          :placeholder="t('icons.search')"
          :loading="loading"
          class="w-full"
          autofocus
        />
        <p class="mt-2 text-xs text-muted">
          {{ query ? t('icons.results', { count: results.length }) : t('icons.suggestions') }}
        </p>

        <ul
          class="mt-3 grid max-h-80 grid-cols-6 gap-1 overflow-y-auto sm:grid-cols-8"
          :aria-label="t('icons.title')"
        >
          <li
            v-for="[name, label] in results"
            :key="name"
          >
            <UTooltip :text="`${label} (${name})`">
              <UButton
                color="neutral"
                :variant="model === name ? 'soft' : 'ghost'"
                :aria-label="label"
                :aria-pressed="model === name"
                class="flex aspect-square w-full items-center justify-center"
                @click="choose(name)"
              >
                <ItemIcon
                  :name="name"
                  :fallback="fallback"
                  class="text-2xl"
                />
              </UButton>
            </UTooltip>
          </li>
        </ul>
        <UEmpty
          v-if="!loading && query && !results.length"
          icon="i-fadl-icons"
          :description="t('icons.noResults')"
          size="sm"
        />
      </template>
    </UModal>
  </div>
</template>
