<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import { labelSetsQuery, setProjectLabelSetMutation } from '~/graphql/labels'

/**
 * Project settings: the label set of the project, with a preview of its
 * labels. Tasks keep their labels when the set changes.
 */
const props = defineProps<{
  projectId: string
  labelSetId?: string | null
}>()

const { t } = useI18n()
const toast = useToast()

const { result } = useQuery(labelSetsQuery, null, { fetchPolicy: 'cache-and-network' })
const sets = computed(() => result.value?.labelSets ?? [])

const items = computed(() => [
  { label: t('labels.noSetChoice'), value: 'none' },
  ...sets.value.map(set => ({ label: set.name, value: set.id }))
])

const chosen = computed(() => sets.value.find(set => set.id === props.labelSetId) ?? null)

const { mutate, loading } = useMutation(setProjectLabelSetMutation, { refetchQueries: 'active', awaitRefetchQueries: true })

async function choose(value: string | null): Promise<void> {
  const labelSetId = !value || value === 'none' ? null : value

  if (labelSetId !== (props.labelSetId ?? null)) {
    const saved = await mutate({ projectId: props.projectId, labelSetId }).catch(() => null)

    if (saved) {
      toast.add({ title: t('labels.setChosen'), color: 'success' })
    }
  }
}
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
      <FormSelect
        :model-value="labelSetId ?? 'none'"
        :items="items"
        :label="t('labels.set')"
        :hint="chosen?.description ?? undefined"
        :disabled="loading"
        @update:model-value="choose"
      />
      <TaskLabels
        v-if="chosen"
        :labels="chosen.labels"
      />
      <div>
        <wa-button
          href="/label-sets"
          appearance="plain"
          variant="brand"
          @click.prevent="navigateTo('/label-sets')"
        >
          {{ t('labels.manage') }}
          <wa-icon
            slot="end"
            name="arrow-right"
          ></wa-icon>
        </wa-button>
      </div>
    </div>
  </wa-card>
</template>
