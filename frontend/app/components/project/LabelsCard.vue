<script setup lang="ts">
import { useQuery } from '@vue/apollo-composable'
import { projectLabelsQuery } from '~/graphql/labels'

/**
 * Project settings: the labels of the project (project.update), with the
 * editor and the take-over of the labels of a template.
 */
const props = defineProps<{ projectId: string }>()

const { t } = useI18n()

const { result } = useQuery(projectLabelsQuery, () => ({ id: props.projectId }), { fetchPolicy: 'cache-and-network' })
const project = computed(() => result.value?.project ?? null)
const labels = computed(() => project.value?.labels ?? [])

const editing = ref(false)
const fromTemplate = ref(false)
</script>

<template>
  <wa-card>
    <div slot="header">
      <h2 class="wa-heading-m">
        {{ t('labels.cardTitle') }}
      </h2>
      <p class="wa-body-s wa-color-text-quiet">
        {{ t('labels.cardIntro') }}
      </p>
    </div>

    <div class="wa-stack wa-gap-m">
      <TaskLabels
        v-if="labels.length"
        :labels="labels.map(label => ({ id: label.id, name: label.name, color: label.color, position: label.position }))"
      />
      <p
        v-else
        class="wa-body-s wa-color-text-quiet"
      >
        {{ t('labels.cardEmpty') }}
      </p>

      <div class="wa-cluster wa-gap-s">
        <wa-button
          variant="brand"
          appearance="outlined"
          :disabled="!project"
          @click="editing = true"
        >
          <wa-icon
            slot="start"
            name="pen"
          ></wa-icon>
          {{ t('labels.edit') }}
        </wa-button>
        <wa-button
          appearance="outlined"
          variant="neutral"
          :disabled="!project"
          @click="fromTemplate = true"
        >
          <wa-icon
            slot="start"
            name="copy"
          ></wa-icon>
          {{ t('labels.fromTemplate') }}
        </wa-button>
        <wa-button
          href="/templates"
          appearance="plain"
          variant="brand"
          @click.prevent="navigateTo('/templates')"
        >
          {{ t('labels.manageTemplates') }}
          <wa-icon
            slot="end"
            name="arrow-right"
          ></wa-icon>
        </wa-button>
      </div>
    </div>

    <ProjectLabelsFormModal
      v-if="project"
      :open="editing"
      :project-id="projectId"
      :locale="project.locale"
      :labels="labels"
      @close="editing = false"
    />
    <ProjectTemplateLabelsModal
      v-if="project"
      :open="fromTemplate"
      :project-id="projectId"
      :locale="project.locale"
      :labels="labels"
      @close="fromTemplate = false"
    />
  </wa-card>
</template>
