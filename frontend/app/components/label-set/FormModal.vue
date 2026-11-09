<script setup lang="ts">
import { useMutation } from '@vue/apollo-composable'
import type { LabelColor } from '~/graphql/generated/graphql'
import { createLabelSetMutation, labelSetsQuery, updateLabelSetMutation } from '~/graphql/labels'

/**
 * Create an own label set or change one: name, description and the labels
 * (name, category, colour) in their order. Labels removed from an existing
 * set are deleted, also on the tasks; that is asked before saving.
 */
export interface LabelSetFormTarget {
  set?: {
    id: string
    name: string
    description?: string | null
    labels: { id: string, name: string, category?: string | null, color: string }[]
  }
}

const props = defineProps<{ target: LabelSetFormTarget | null }>()
const emit = defineEmits<{ close: [] }>()

const { t } = useI18n()
const toast = useToast()
const { errors, pending, run } = useGraphqlForm()

const COLORS: LabelColor[] = ['GRAY', 'RED', 'ORANGE', 'YELLOW', 'GREEN', 'CYAN', 'BLUE', 'INDIGO', 'PURPLE', 'PINK']
const colorItems = computed(() => COLORS.map(color => ({ label: t(`labels.colors.${color}`), value: color })))

interface Row { key: number, id?: string, name: string, category: string, color: LabelColor }

let nextKey = 0
const state = reactive<{ name: string, description: string, rows: Row[] }>({ name: '', description: '', rows: [] })

const open = computed({
  get: () => props.target !== null,
  set: (value: boolean) => {
    if (!value) {
      emit('close')
    }
  }
})

const isNew = computed(() => !props.target?.set)

watch(() => props.target, (target) => {
  errors.value = {}
  state.name = target?.set?.name ?? ''
  state.description = target?.set?.description ?? ''
  state.rows = (target?.set?.labels ?? []).map(label => ({
    key: nextKey++,
    id: label.id,
    name: label.name,
    category: label.category ?? '',
    color: label.color as LabelColor
  }))
}, { immediate: true })

/** A new label gets the first colour not used yet in the set (grey last). */
function freeColor(): LabelColor {
  const used = new Set(state.rows.map(row => row.color))
  const order = [...COLORS.slice(1), COLORS[0]!]

  return order.find(color => !used.has(color)) ?? order[state.rows.length % order.length]!
}

function addRow(): void {
  state.rows.push({ key: nextKey++, name: '', category: state.rows.at(-1)?.category ?? '', color: freeColor() })
}

function move(index: number, by: -1 | 1): void {
  const [row] = state.rows.splice(index, 1)
  state.rows.splice(index + by, 0, row!)
}

/** Existing labels that are no longer in the list: deleted on save, also on tasks. */
const removedCount = computed(() => {
  const kept = new Set(state.rows.map(row => row.id).filter(Boolean))

  return (props.target?.set?.labels ?? []).filter(label => !kept.has(label.id)).length
})

/** A message about the labels from the server (keys like "labels" or "labels.2.id"). */
const labelsError = computed(() => Object.entries(errors.value).find(([key]) => key.startsWith('labels'))?.[1])

const options = { refetchQueries: [{ query: labelSetsQuery }], awaitRefetchQueries: true }
const { mutate: createSet } = useMutation(createLabelSetMutation, options)
const { mutate: updateSet } = useMutation(updateLabelSetMutation, options)

const confirming = ref(false)

function submit(): void {
  if (removedCount.value > 0) {
    confirming.value = true
  } else {
    void save()
  }
}

async function save(): Promise<void> {
  const input = {
    name: state.name.trim(),
    description: state.description.trim() || null,
    labels: state.rows.map(row => ({
      ...(row.id ? { id: row.id } : {}),
      name: row.name.trim(),
      category: row.category.trim() || null,
      color: row.color
    }))
  }
  const set = props.target?.set
  const result = await run<unknown>(() => (set ? updateSet({ id: set.id, input }) : createSet({ input })))

  if (result) {
    toast.add({ title: t('labels.saved'), color: 'success' })
    emit('close')
  }
}
</script>

<template>
  <AppDialog
    v-model:open="open"
    :label="isNew ? t('labels.create') : t('labels.edit')"
    class="label-set-dialog"
  >
    <form
      id="label-set-form"
      class="wa-stack wa-gap-m"
      @submit.prevent="submit"
    >
      <FormInput
        v-model="state.name"
        name="name"
        :label="t('labels.name')"
        :error="errors.name"
        maxlength="60"
        autofocus
        required
      />
      <FormTextarea
        v-model="state.description"
        name="description"
        :label="t('labels.description')"
        :error="errors.description"
        maxlength="200"
        :rows="2"
      />

      <FormField
        :label="t('labels.field')"
        :error="labelsError"
      >
        <ol class="label-rows">
          <li
            v-for="(row, index) in state.rows"
            :key="row.key"
            class="label-row"
          >
            <span
              class="label-row-dot"
              :style="{ backgroundColor: `var(--wa-color-${row.color.toLowerCase()}-60)` }"
              aria-hidden="true"
            />
            <FormSelect
              v-model="row.color"
              :items="colorItems"
              :label="t('labels.color')"
              hide-label
              class="label-row-color"
            />
            <FormInput
              v-model="row.name"
              :label="t('labels.labelName')"
              hide-label
              :placeholder="t('labels.labelName')"
              maxlength="40"
              required
              class="label-row-name"
            />
            <FormInput
              v-model="row.category"
              :label="t('labels.category')"
              hide-label
              :placeholder="t('labels.category')"
              maxlength="40"
              class="label-row-category"
            />
            <span class="label-row-tools">
              <wa-button
                appearance="plain"
                variant="neutral"
                size="s"
                :disabled="index === 0"
                :aria-label="t('labels.moveUp', { name: row.name || index + 1 })"
                @click="move(index, -1)"
              >
                <wa-icon name="arrow-up"></wa-icon>
              </wa-button>
              <wa-button
                appearance="plain"
                variant="neutral"
                size="s"
                :disabled="index === state.rows.length - 1"
                :aria-label="t('labels.moveDown', { name: row.name || index + 1 })"
                @click="move(index, 1)"
              >
                <wa-icon name="arrow-down"></wa-icon>
              </wa-button>
              <wa-button
                appearance="plain"
                variant="danger"
                size="s"
                :aria-label="t('labels.removeLabel', { name: row.name || index + 1 })"
                @click="state.rows.splice(index, 1)"
              >
                <wa-icon name="trash"></wa-icon>
              </wa-button>
            </span>
          </li>
        </ol>
        <wa-button
          appearance="outlined"
          variant="neutral"
          size="s"
          :disabled="state.rows.length >= 30"
          @click="addRow"
        >
          <wa-icon
            slot="start"
            name="plus"
          ></wa-icon>
          {{ t('labels.addLabel') }}
        </wa-button>
      </FormField>
    </form>

    <template #footer>
      <wa-button
        appearance="plain"
        variant="neutral"
        @click="open = false"
      >
        {{ t('common.cancel') }}
      </wa-button>
      <wa-button
        type="submit"
        form="label-set-form"
        variant="brand"
        :loading="pending"
      >
        {{ t('common.save') }}
      </wa-button>
    </template>
  </AppDialog>

  <ConfirmModal
    :open="confirming"
    :title="t('labels.removeTitle')"
    :description="t('labels.removeDescription', removedCount)"
    :confirm-label="t('labels.removeConfirm')"
    :action="save"
    @close="confirming = false"
  />
</template>

<style scoped>
.label-set-dialog {
  --width: 48rem;
}

.label-rows {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-xs);
  inline-size: 100%;
  margin: 0 0 var(--wa-space-s);
  padding: 0;
  list-style: none;
}

.label-row {
  display: grid;
  grid-template-columns: auto 9rem minmax(8rem, 1fr) minmax(6rem, 1fr) auto;
  align-items: center;
  gap: var(--wa-space-xs);
}

.label-row-dot {
  inline-size: 0.75rem;
  block-size: 0.75rem;
  border-radius: 50%;
}

.label-row-tools {
  display: flex;
}

@media (max-width: 40em) {
  .label-row {
    grid-template-columns: auto 1fr auto;
  }

  .label-row-name,
  .label-row-category {
    grid-column: 2 / -1;
  }
}
</style>
