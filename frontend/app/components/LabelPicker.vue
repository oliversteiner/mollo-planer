<script setup lang="ts">
/**
 * Choice of several labels (wa-select multiple), grouped by category, each
 * with its coloured dot. Labels a task still has from an earlier set are
 * offered as well, so they can be removed.
 */
export interface PickerLabel {
  id: string
  name: string
  category?: string | null
  color: string
  position: number
}

const props = defineProps<{
  /** Labels of the project's set. */
  labels: readonly PickerLabel[]
  /** Labels the task has that are not in the set (any more). */
  extra?: readonly PickerLabel[]
  label?: string
  hint?: string
  disabled?: boolean
}>()

const model = defineModel<string[]>({ default: () => [] })

const { t } = useI18n()

/** Groups in the order of the set; labels without category first. */
const groups = computed(() => {
  const result: { category: string | null, labels: PickerLabel[] }[] = []

  for (const label of [...props.labels].sort((a, b) => a.position - b.position)) {
    const category = label.category ?? null
    const group = result.find(entry => entry.category === category)

    if (group) {
      group.labels.push(label)
    } else {
      result.push({ category, labels: [label] })
    }
  }

  return result
})

function onChange(event: Event) {
  const value = (event.target as HTMLSelectElement & { value: string | string[] }).value
  model.value = Array.isArray(value) ? [...value] : value ? [value] : []
}

function dot(color: string): Record<string, string> {
  return { backgroundColor: `var(--wa-color-${color.toLowerCase()}-60)` }
}
</script>

<template>
  <wa-select
    multiple
    with-clear
    :value.prop="model"
    :label="label"
    :hint="hint"
    :disabled="disabled"
    :placeholder="t('labels.none')"
    max-options-visible="0"
    @change="onChange"
  >
    <template
      v-for="(group, index) in groups"
      :key="group.category ?? ''"
    >
      <wa-divider v-if="index > 0"></wa-divider>
      <small
        v-if="group.category"
        class="label-picker-group"
      >{{ group.category }}</small>
      <wa-option
        v-for="item in group.labels"
        :key="item.id"
        :value="item.id"
      >
        <span
          class="label-picker-dot"
          :style="dot(item.color)"
          aria-hidden="true"
        />
        {{ item.name }}
      </wa-option>
    </template>
    <template v-if="extra?.length">
      <wa-divider></wa-divider>
      <small class="label-picker-group">{{ t('labels.outsideSetGroup') }}</small>
      <wa-option
        v-for="item in extra"
        :key="item.id"
        :value="item.id"
      >
        <span
          class="label-picker-dot"
          :style="dot(item.color)"
          aria-hidden="true"
        />
        {{ item.name }}
      </wa-option>
    </template>
  </wa-select>
</template>

<style scoped>
.label-picker-group {
  display: block;
  padding: var(--wa-space-2xs) var(--wa-space-s) 0;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-xs);
  font-weight: var(--wa-font-weight-semibold);
}

.label-picker-dot {
  display: inline-block;
  inline-size: 0.6em;
  block-size: 0.6em;
  margin-inline-end: var(--wa-space-2xs);
  border-radius: 50%;
  vertical-align: middle;
}
</style>
