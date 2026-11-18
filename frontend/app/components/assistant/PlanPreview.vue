<script setup lang="ts">
import type { DraftPlanMutation } from '~/graphql/generated/graphql'
import { newCounts, readPlan } from '~/utils/assistant'

/**
 * The preview of the assistant (docs/auftrag-m6.md §4.4): the plan as a tree
 * with the marks of the dry run; every new element has a checkbox to strike it
 * out (the preview is checked again). Nothing is saved until "Jetzt eintragen".
 */
type Preview = NonNullable<DraftPlanMutation['draftPlan']['preview']>

const props = defineProps<{
  /** The plan as text (server canonical or our own filtered JSON) */
  plan: string
  preview: Preview | null
  hints: readonly string[]
  struck: ReadonlySet<string>
  /** A project is chosen: the plan only adds to it */
  existingProject: boolean
  busy?: boolean
  checking?: boolean
  error?: string | null
}>()

const emit = defineEmits<{
  toggle: [id: string]
  import: [notifyAssignees: boolean]
  ideas: []
  discard: []
}>()

const { t } = useI18n()
const notifyAssignees = ref(false)

const document = computed(() => readPlan(props.plan))
const counts = computed(() => (document.value ? newCounts(document.value, props.struck) : { milestones: 0, todos: 0, tasks: 0 }))
const errors = computed(() => props.preview?.errors ?? [])
const canImport = computed(() => !props.busy && !props.checking && props.preview !== null && props.preview.ok && (props.preview.changes?.length ?? 0) > 0)
</script>

<template>
  <div class="wa-stack wa-gap-m">
    <div
      v-if="document"
      class="wa-stack wa-gap-2xs"
    >
      <h2 class="wa-heading-m">
        {{ document.project.name || t('assistant.plan.title') }}
      </h2>
      <p class="plan-quiet">
        {{ t('assistant.plan.counts', counts) }}
      </p>
    </div>

    <AppCallout
      v-if="errors.length"
      variant="danger"
      :title="t('assistant.plan.errors')"
    >
      <ul class="plan-list">
        <li
          v-for="(item, index) in errors"
          :key="index"
        >
          <code v-if="item.path">{{ item.path }}</code>
          {{ item.message }}
        </li>
      </ul>
    </AppCallout>

    <AppCallout
      v-if="hints.length"
      variant="warning"
      :title="t('assistant.plan.hints')"
    >
      <ul class="plan-list">
        <li
          v-for="(hint, index) in hints"
          :key="index"
        >
          {{ hint }}
        </li>
      </ul>
    </AppCallout>

    <PlanerTree
      v-if="document"
      :document="document"
      :changes="preview?.changes ?? []"
      strikable
      :struck="struck"
      @toggle="id => emit('toggle', id)"
    />

    <AppCallout
      v-if="error"
      variant="danger"
      :description="error"
    />

    <div class="plan-actions">
      <FormCheckbox
        v-model="notifyAssignees"
        :label="t('assistant.plan.notify')"
        :hint="t('assistant.plan.notifyHint')"
      />
      <div class="wa-cluster wa-gap-s">
        <wa-button
          appearance="plain"
          variant="neutral"
          :disabled="busy"
          @click="emit('discard')"
        >
          {{ t('assistant.plan.discard') }}
        </wa-button>
        <wa-button
          appearance="outlined"
          variant="neutral"
          :disabled="busy || checking"
          @click="emit('ideas')"
        >
          <wa-icon
            slot="start"
            name="lightbulb"
          ></wa-icon>
          {{ t('assistant.plan.toIdeas') }}
        </wa-button>
        <wa-button
          variant="brand"
          :loading="busy"
          :disabled="!canImport"
          @click="emit('import', notifyAssignees)"
        >
          <wa-icon
            slot="start"
            name="floppy-disk-circle-arrow-right"
          ></wa-icon>
          {{ t('assistant.plan.importNow') }}
        </wa-button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.plan-quiet {
  margin: 0;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}

.plan-list {
  margin: 0;
  padding-inline-start: var(--wa-space-m);
}

.plan-actions {
  position: sticky;
  inset-block-end: 0;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--wa-space-s);
  padding-block: var(--wa-space-s);
  background-color: var(--wa-color-surface-default);
  border-block-start: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
}
</style>
