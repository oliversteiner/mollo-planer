<script setup lang="ts">
import type { TemplatesQuery } from '~/graphql/generated/graphql'

/**
 * The outline of a template to look at: milestones, their todos and the
 * tasks in them as a tree (wa-tree), each task with its labels. Nothing can
 * be changed here.
 */
defineProps<{
  outline: TemplatesQuery['templates'][number]['outline']
  /** Names of the labels by key, for the tasks. */
  labelNames: Readonly<Record<string, string>>
}>()

const { t } = useI18n()
</script>

<template>
  <wa-tree
    class="template-outline"
    :aria-label="t('templates.outline')"
  >
    <wa-tree-item
      v-for="(milestone, m) in outline"
      :key="m"
      expanded
    >
      <wa-icon name="flag"></wa-icon>
      {{ milestone.title }}
      <wa-badge
        v-if="milestone.version"
        variant="neutral"
        appearance="outlined"
      >
        {{ milestone.version }}
      </wa-badge>
      <wa-tree-item
        v-for="(todo, d) in milestone.todos"
        :key="d"
        expanded
      >
        <wa-icon name="list-check"></wa-icon>
        {{ todo.title }}
        <wa-tree-item
          v-for="(task, k) in todo.tasks"
          :key="k"
        >
          <wa-icon name="square-check"></wa-icon>
          {{ task.title }}
          <span
            v-if="task.labels.length"
            class="template-outline-labels"
          >
            {{ task.labels.map(key => labelNames[key] ?? key).join(', ') }}
          </span>
        </wa-tree-item>
      </wa-tree-item>
    </wa-tree-item>
  </wa-tree>
</template>

<style scoped>
.template-outline {
  --indent-guide-width: 1px;
}

.template-outline-labels {
  margin-inline-start: var(--wa-space-xs);
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-xs);
}
</style>
