<script setup lang="ts">
import { matchesSearch } from '~/utils/text-search'

/** One person to choose. A person with a reason is shown but cannot be chosen. */
export interface PersonOption {
  id: string
  name: string
  avatarUrl?: string | null
  /** Why the person cannot be chosen, for example "already a member". */
  disabledReason?: string | null
}

/** Optional groups, for example members and other people. */
export interface PersonGroup {
  label?: string
  people: readonly PersonOption[]
}

/**
 * One searchable field to choose a person (wa-combobox): shows everybody on
 * opening, filters while typing without case and accents, with avatar and,
 * for people who cannot be chosen, the reason (docs/fix-m1-mitglieder.md §2.4).
 */
const props = defineProps<{
  groups: readonly PersonGroup[]
  label: string
  hideLabel?: boolean
  hint?: string
  error?: string
  placeholder?: string
  disabled?: boolean
}>()

const model = defineModel<string | null>({ default: null })

/** The combobox asks this for every option while typing; it searches the name only. */
function matcher(option: HTMLElement, query: string): boolean {
  return matchesSearch(query, [option.dataset.name ?? option.textContent ?? ''])
}

function onChange(event: Event): void {
  const value = (event.target as HTMLInputElement).value

  model.value = value || null
}

const hasPeople = computed(() => props.groups.some(group => group.people.length > 0))

/**
 * The combobox keeps its typed text when its value is emptied from outside
 * (for example after "add"); a new element starts empty.
 */
const resetKey = ref(0)
watch(model, (value, previous) => {
  if (!value && previous) {
    resetKey.value += 1
  }
})
</script>

<template>
  <wa-combobox
    :key="resetKey"
    :value="model ?? ''"
    :label="label"
    :class="{ 'wa-visually-hidden-label': hideLabel }"
    :hint="error ? undefined : hint"
    :placeholder="placeholder"
    :disabled="disabled || !hasPeople"
    :aria-invalid="error ? 'true' : undefined"
    :data-invalid="error ? '' : undefined"
    :filter.prop="matcher"
    @change="onChange"
  >
    <template
      v-for="(group, index) in groups"
      :key="index"
    >
      <small v-if="group.label && group.people.length">{{ group.label }}</small>
      <wa-option
        v-for="person in group.people"
        :key="person.id"
        :value="person.id"
        :data-name="person.name"
        :disabled="Boolean(person.disabledReason)"
      >
        <UserAvatar
          slot="start"
          :name="person.name"
          :src="person.avatarUrl"
          size="xs"
        />
        {{ person.name }}
        <span
          v-if="person.disabledReason"
          class="person-select-reason"
        >· {{ person.disabledReason }}</span>
      </wa-option>
    </template>
    <span
      v-if="error"
      slot="hint"
      data-form-error
    >{{ error }}</span>
  </wa-combobox>
</template>

<style scoped>
.person-select-reason {
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}
</style>
