<script setup lang="ts">
import { EMPTY_ROLE_FILTER, groupRights, isRoleFilterActive, type RoleFilter } from '~/composables/useRoleFilters'

/**
 * Search and filters of the role page (docs/fix-m1-rollenseite.md §3): text,
 * shipped or own, "has right …" and in use. Emits the whole filter; the page
 * keeps it in the URL.
 */
const props = defineProps<{
  filter: RoleFilter
  /** The rights of the current scope, for "has right …". */
  rights: readonly string[]
  shown: number
  total: number
}>()

const emit = defineEmits<{ 'update:filter': [filter: RoleFilter] }>()

const { t } = useI18n()

/** The search waits a moment for the typing to stop. */
const SEARCH_DELAY_MS = 200

const query = ref(props.filter.q)
let pending: ReturnType<typeof setTimeout> | undefined

watch(() => props.filter.q, (q) => {
  if (q !== query.value) {
    query.value = q
  }
})

watch(query, (q) => {
  clearTimeout(pending)
  pending = setTimeout(() => change({ q }), SEARCH_DELAY_MS)
})

onBeforeUnmount(() => clearTimeout(pending))

function change(part: Partial<RoleFilter>): void {
  emit('update:filter', { ...props.filter, ...part })
}

function reset(): void {
  clearTimeout(pending)
  query.value = ''
  emit('update:filter', { ...EMPTY_ROLE_FILTER })
}

const originItems = computed(() => (['all', 'standard', 'own'] as const).map(value => ({ label: t(`roleSettings.filters.origin.${value}`), value })))
const usageItems = computed(() => (['all', 'used', 'unused'] as const).map(value => ({ label: t(`roleSettings.filters.usage.${value}`), value })))
const rightGroups = computed(() => groupRights(props.rights))
const active = computed(() => isRoleFilterActive(props.filter))

function onRights(event: Event): void {
  const value = (event.target as HTMLSelectElement & { value: string | string[] | null }).value
  change({ rights: Array.isArray(value) ? [...value] : value ? [value] : [] })
}
</script>

<template>
  <div class="role-filters">
    <FormInput
      v-model="query"
      type="search"
      autocomplete="off"
      :label="t('roleSettings.filters.search')"
      :placeholder="t('roleSettings.filters.searchPlaceholder')"
      hide-label
      class="role-filters-search"
    >
      <template #start>
        <wa-icon
          slot="start"
          name="magnifying-glass"
        ></wa-icon>
      </template>
    </FormInput>

    <FormSelect
      :model-value="filter.origin"
      :items="originItems"
      :label="t('roleSettings.filters.originLabel')"
      hide-label
      class="role-filters-select"
      @update:model-value="value => change({ origin: (value ?? 'all') as RoleFilter['origin'] })"
    />

    <wa-select
      multiple
      with-clear
      :value="filter.rights"
      :label="t('roleSettings.filters.rights')"
      :placeholder="t('roleSettings.filters.rightsPlaceholder')"
      max-options-visible="1"
      class="role-filters-rights wa-visually-hidden-label"
      @change="onRights"
    >
      <template
        v-for="entry in rightGroups"
        :key="entry.group"
      >
        <small>{{ t(`roleSettings.groups.${entry.group}`) }}</small>
        <wa-option
          v-for="right in entry.rights"
          :key="right"
          :value="right"
        >
          {{ t(`rights.names.${right}`) }}
        </wa-option>
      </template>
    </wa-select>

    <FormSelect
      :model-value="filter.usage"
      :items="usageItems"
      :label="t('roleSettings.filters.usageLabel')"
      hide-label
      class="role-filters-select"
      @update:model-value="value => change({ usage: (value ?? 'all') as RoleFilter['usage'] })"
    />

    <span
      class="role-filters-count"
      aria-live="polite"
    >{{ t('roleSettings.filters.count', { shown, total }) }}</span>

    <wa-button
      v-if="active"
      appearance="plain"
      variant="neutral"
      size="s"
      @click="reset"
    >
      {{ t('roleSettings.filters.reset') }}
    </wa-button>
  </div>
</template>

<style scoped>
.role-filters {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-s);
}

.role-filters-search {
  flex: 1 1 14rem;
  max-inline-size: 24rem;
}

.role-filters-select {
  inline-size: 14rem;
}

.role-filters-rights {
  inline-size: 16rem;
}

.role-filters-count {
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}

@media (max-width: 40em) {
  .role-filters-search,
  .role-filters-select,
  .role-filters-rights {
    flex: 1 1 100%;
    inline-size: 100%;
    max-inline-size: none;
  }
}
</style>
