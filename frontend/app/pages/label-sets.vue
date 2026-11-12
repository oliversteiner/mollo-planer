<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import type { ActionMenuItem } from '~/components/ActionMenu.vue'
import type { LabelSetFormTarget } from '~/components/label-set/FormModal.vue'
import type { LabelSetsQuery } from '~/graphql/generated/graphql'
import { deleteLabelSetMutation, duplicateLabelSetMutation, labelSetsQuery } from '~/graphql/labels'

/**
 * Label sets of the app: the built-in ones and own ones. Who may (admins,
 * owners and managers of any project) creates, duplicates, changes and
 * deletes own sets; the list is visible to everybody.
 */
const { t } = useI18n()
const toast = useToast()

useHead({ title: () => t('labels.pageTitle') })

type LabelSet = LabelSetsQuery['labelSets'][number]

const { result, loading } = useQuery(labelSetsQuery, null, { fetchPolicy: 'cache-and-network' })
const sets = computed(() => result.value?.labelSets ?? [])
const canManage = computed(() => (result.value?.viewerSystemAbilities ?? []).includes('LABEL_SETS_MANAGE'))

const editing = ref<LabelSetFormTarget | null>(null)

const refetch = { refetchQueries: [{ query: labelSetsQuery }], awaitRefetchQueries: true }
const { mutate: duplicateSet } = useMutation(duplicateLabelSetMutation, refetch)
const { mutate: deleteSet } = useMutation(deleteLabelSetMutation, refetch)

const deletion = useConfirmedDelete<LabelSet>(set => deleteSet({ id: set.id }), {
  onDeleted: () => toast.add({ title: t('labels.deleted'), color: 'success' })
})

async function duplicate(set: LabelSet): Promise<void> {
  const copy = (await duplicateSet({ id: set.id }).catch(() => null))?.data?.duplicateLabelSet

  if (copy) {
    toast.add({ title: t('labels.duplicated', { name: copy.name }), color: 'success' })
    const fresh = sets.value.find(entry => entry.id === copy.id)

    if (fresh) {
      editing.value = { set: fresh }
    }
  }
}

function actions(set: LabelSet): ActionMenuItem[] {
  const items: ActionMenuItem[] = []

  if (set.viewerCanUpdate) {
    items.push({ label: t('common.edit'), icon: 'pen', onSelect: () => (editing.value = { set }) })
  }
  if (canManage.value) {
    items.push({ label: t('labels.duplicate'), icon: 'copy', onSelect: () => duplicate(set) })
  }
  if (set.viewerCanDelete) {
    items.push({ label: set.usedByProjects ? `${t('common.delete')} (${t('labels.inUse')})` : t('common.delete'), icon: 'trash', color: 'error', disabled: set.usedByProjects > 0, onSelect: () => deletion.ask(set) })
  }

  return items
}
</script>

<template>
  <PageFrame
    :title="t('labels.pageTitle')"
    :breadcrumbs="[{ label: t('nav.workspace'), to: '/' }, { label: t('labels.pageTitle') }]"
    :description="t('labels.pageIntro')"
  >
    <template
      v-if="canManage"
      #actions
    >
      <wa-button
        variant="brand"
        @click="editing = {}"
      >
        <wa-icon
          slot="start"
          name="plus"
        ></wa-icon>
        {{ t('labels.create') }}
      </wa-button>
    </template>

    <wa-skeleton
      v-if="loading && !result"
      effect="sheen"
      class="label-sets-skeleton"
    ></wa-skeleton>
    <ul
      v-else
      class="label-sets wa-list-plain"
    >
      <li
        v-for="set in sets"
        :key="set.id"
      >
        <wa-card class="label-set">
          <div
            slot="header"
            class="label-set-head"
          >
            <div class="label-set-title">
              <h2 class="wa-heading-m">
                {{ set.name }}
              </h2>
              <wa-badge
                v-if="set.isBuiltin"
                variant="neutral"
                appearance="outlined"
              >
                {{ t('labels.builtin') }}
              </wa-badge>
            </div>
            <ActionMenu
              v-if="actions(set).length"
              :items="actions(set)"
              :label="t('labels.actionsOf', { name: set.name })"
            />
          </div>
          <div class="wa-stack wa-gap-s">
            <p
              v-if="set.description"
              class="label-set-description"
            >
              {{ set.description }}
            </p>
            <TaskLabels
              v-if="set.labels.length"
              :labels="set.labels"
            />
            <p
              v-else
              class="label-set-description"
            >
              {{ t('labels.empty') }}
            </p>
            <p class="label-set-usage">
              {{ t('labels.usedBy', set.usedByProjects) }}
            </p>
          </div>
        </wa-card>
      </li>
    </ul>

    <LabelSetFormModal
      :target="editing"
      @close="editing = null"
    />
    <ConfirmModal
      :open="deletion.isOpen"
      :title="t('labels.deleteTitle', { name: deletion.target?.name ?? '' })"
      :description="t('labels.deleteDescription')"
      :confirm-label="t('common.delete')"
      :action="deletion.confirm"
      @close="deletion.close"
    />
  </PageFrame>
</template>

<style scoped>
.label-sets-skeleton {
  block-size: 12rem;
}

.label-sets {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 22rem), 1fr));
  gap: var(--wa-space-l);
  margin: 0;
  padding: 0;
}

.label-set {
  block-size: 100%;
}

.label-set-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--wa-space-s);
}

.label-set-title {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-xs);
}

.label-set-title h2 {
  margin: 0;
}

.label-set-description,
.label-set-usage {
  margin: 0;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}

.label-set-usage {
  font-size: var(--wa-font-size-xs);
}
</style>
