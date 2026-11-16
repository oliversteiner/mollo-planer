<script setup lang="ts">
import { groupRights } from '~/composables/useRoleFilters'

/**
 * Rights grouped by their area (Project, Tasks, …), each with its short text.
 * Read only, with an optional hint which functions give a right; or, with
 * `selectable`, as checkboxes (v-model: the chosen rights).
 */
const props = defineProps<{
  rights: readonly string[]
  selectable?: boolean
  /** Right → names of the functions that give it (roles only). */
  via?: Readonly<Record<string, readonly string[]>>
  /** Search text to mark in the short texts. */
  query?: string
}>()

const model = defineModel<string[]>({ default: () => [] })

const { t } = useI18n()

const groups = computed(() => groupRights(props.rights))

function toggle(right: string, checked: boolean): void {
  model.value = checked ? [...model.value, right] : model.value.filter(entry => entry !== right)
}
</script>

<template>
  <p
    v-if="!rights.length"
    class="right-groups-empty"
  >
    {{ t('roleSettings.noRights') }}
  </p>
  <div
    v-else
    class="right-groups"
  >
    <section
      v-for="entry in groups"
      :key="entry.group"
      class="right-group"
    >
      <p class="right-group-title">
        {{ t(`roleSettings.groups.${entry.group}`) }}
      </p>
      <ul class="right-group-list wa-list-plain">
        <li
          v-for="right in entry.rights"
          :key="right"
        >
          <FormCheckbox
            v-if="selectable"
            :model-value="model.includes(right)"
            :label="t(`rights.names.${right}`)"
            @update:model-value="checked => toggle(right, checked)"
          />
          <template v-else>
            <RoleHighlight
              :text="t(`rights.names.${right}`)"
              :query="query"
            />
            <span
              v-if="via?.[right]?.length"
              class="right-group-via"
            >{{ t('roleSettings.via', { names: via[right]!.join(', ') }) }}</span>
          </template>
        </li>
      </ul>
    </section>
  </div>
</template>

<style scoped>
.right-groups {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 16rem), 1fr));
  gap: var(--wa-space-m);
}

.right-group-title {
  margin: 0 0 var(--wa-space-2xs);
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-xs);
  font-weight: var(--wa-font-weight-semibold);
}

.right-group-list {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-2xs);
  margin: 0;
  padding: 0;
  font-size: var(--wa-font-size-s);
}

.right-group-via,
.right-groups-empty {
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-xs);
}

.right-group-via {
  margin-inline-start: var(--wa-space-2xs);
}

.right-groups-empty {
  margin: 0;
}
</style>
