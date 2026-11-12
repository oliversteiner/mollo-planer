<script setup lang="ts">
import type { ProjectOption } from '~/utils/project-filter'

/** Choice of one project for a list; null means all projects. */
const props = defineProps<{
  projects: readonly ProjectOption[]
  modelValue: string | null
}>()

const emit = defineEmits<{ 'update:modelValue': [value: string | null] }>()

const { t } = useI18n()

const items = computed(() => [
  { label: t('projectFilter.all'), value: '' },
  ...props.projects.map(project => ({ label: project.name, value: project.id }))
])
</script>

<template>
  <FormSelect
    :model-value="modelValue ?? ''"
    :items="items"
    :label="t('projectFilter.label')"
    hide-label
    class="project-filter"
    @update:model-value="emit('update:modelValue', $event || null)"
  />
</template>

<style scoped>
.project-filter {
  inline-size: 14rem;
}
</style>
