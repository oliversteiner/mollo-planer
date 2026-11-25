<script setup lang="ts">
import {
  createMilestoneMutation,
  createTodoMutation,
  updateMilestoneMutation,
  updateTodoMutation
} from '~/graphql/project'
import { isEmptyDocument } from '~/utils/rich-text'
import { setMilestonePriceMutation } from '~/graphql/billing'
import { MAX_CENTS, formatMoney, parseMoney } from '~/utils/money'

export interface ItemFormTarget {
  kind: 'milestone' | 'todo'
  /** Existing item to change; without it a new one is created. */
  item?: {
    id: string
    title: string
    dueDate?: string | null
    icon?: string | null
    description?: Record<string, unknown> | null
    /** Release of a milestone (version and status), null without a version. */
    release?: { version: string, status: string } | null
    /** Fixed price in Rappen (Auftrag 39); null without one or without billing.manage. */
    priceCents?: number | null
  }
  /** Project (new milestone) or milestone (new todo) the item belongs to. */
  parentId?: string
}

/**
 * Create or change a milestone or todo: title, description, icon, due date. With billing.manage a milestone also has
 * its fixed price in CHF (Auftrag 39), saved by its own mutation after the milestone.
 */
const props = defineProps<{
  target: ItemFormTarget | null
  /** Choice of milestone for a new todo without parentId. */
  milestones?: { id: string, title: string }[]
}>()

const emit = defineEmits<{ close: [] }>()

const { t, locale } = useI18n()
const toast = useToast()
const { can } = useViewer()
const { errors, pending, run } = useGraphqlForm()

const open = computed({
  get: () => props.target !== null,
  set: (value: boolean) => {
    if (!value) {
      emit('close')
    }
  }
})

type ReleaseStatusInput = 'PLANNED' | 'RELEASED'

const state = reactive<{
  title: string
  dueDate: string
  milestoneId: string
  icon: string | null
  description: Record<string, unknown> | null
  releaseVersion: string
  releaseStatus: ReleaseStatusInput | ''
  price: string
}>({ title: '', dueDate: '', milestoneId: '', icon: null, description: null, releaseVersion: '', releaseStatus: '', price: '' })

watch(() => props.target, (target) => {
  errors.value = {}
  state.title = target?.item?.title ?? ''
  state.dueDate = target?.item?.dueDate ?? ''
  state.icon = target?.item?.icon ?? null
  state.description = target?.item?.description ?? null
  state.milestoneId = target?.parentId ?? props.milestones?.[0]?.id ?? ''
  state.releaseVersion = target?.item?.release?.version ?? ''
  state.releaseStatus = (target?.item?.release?.status?.toUpperCase() as ReleaseStatusInput | undefined) ?? ''
  state.price = target?.item?.priceCents != null ? formatMoney(target.item.priceCents, locale.value) : ''
}, { immediate: true })

const isNew = computed(() => !props.target?.item)
const kind = computed(() => props.target?.kind ?? 'milestone')
const needsMilestoneChoice = computed(() => kind.value === 'todo' && isNew.value && !props.target?.parentId)
const title = computed(() => t(`item.${kind.value}.${isNew.value ? 'create' : 'edit'}`))
const releaseItems = computed(() => [
  { label: t('release.none'), value: '' },
  { label: t('release.planned'), value: 'PLANNED' },
  { label: t('release.released'), value: 'RELEASED' }
])
const milestoneItems = computed(() => (props.milestones ?? []).map(milestone => ({ label: milestone.title, value: milestone.id })))

const { mutate: createMilestone } = useProjectMutation(createMilestoneMutation)
const { mutate: updateMilestone } = useProjectMutation(updateMilestoneMutation)
const { mutate: createTodo } = useProjectMutation(createTodoMutation)
const { mutate: setPrice } = useProjectMutation(setMilestonePriceMutation)

/** The price field: only for milestones and only with the right of the billing. */
const withPrice = computed(() => kind.value === 'milestone' && can('BILLING_MANAGE'))
const { mutate: updateTodo } = useProjectMutation(updateTodoMutation)

async function submit(): Promise<void> {
  const target = props.target

  if (!target) {
    return
  }

  const price = withPrice.value ? parseMoney(state.price) : null

  if (price !== null && (Number.isNaN(price) || price > MAX_CENTS)) {
    errors.value = { price: t('billing.milestone.priceInvalid') }

    return
  }

  const fields = {
    title: state.title.trim(),
    dueDate: state.dueDate || null,
    icon: state.icon,
    description: isEmptyDocument(state.description) ? null : state.description,
    // Version and release belong together: both empty, or both set (the backend checks).
    ...(target.kind === 'milestone'
      ? { releaseVersion: state.releaseVersion.trim() || null, releaseStatus: state.releaseStatus || null }
      : {})
  }
  const result = await run<unknown>(() => {
    if (target.item) {
      return target.kind === 'milestone'
        ? updateMilestone({ id: target.item.id, input: fields })
        : updateTodo({ id: target.item.id, input: fields })
    }

    return target.kind === 'milestone'
      ? createMilestone({ input: { ...fields, projectId: target.parentId ?? '' } })
      : createTodo({ input: { ...fields, milestoneId: target.parentId ?? state.milestoneId } })
  })

  const milestoneId = target.kind === 'milestone'
    ? (target.item?.id ?? (result as { data?: { createMilestone?: { id: string } } } | null)?.data?.createMilestone?.id)
    : undefined
  const priceChanged = withPrice.value && price !== (target.item?.priceCents ?? null)

  if (result && milestoneId && priceChanged && !await run(() => setPrice({ milestoneId, priceCents: price }))) {
    return
  }

  if (result) {
    toast.add({ title: t(`item.${target.kind}.saved`), color: 'success' })
    emit('close')
  }
}
</script>

<template>
  <AppDialog
    v-model:open="open"
    :label="title"
    class="item-dialog"
  >
    <form
      id="item-form"
      class="wa-stack wa-gap-m"
      @submit.prevent="submit"
    >
      <FormSelect
        v-if="needsMilestoneChoice"
        v-model="state.milestoneId"
        name="milestoneId"
        :items="milestoneItems"
        :label="t('item.todo.milestone')"
        :error="errors.milestoneId"
        required
      />

      <FormInput
        v-model="state.title"
        name="title"
        :label="t('item.fields.title')"
        :error="errors.title"
        autofocus
        required
      />

      <div class="wa-cluster wa-gap-m wa-align-items-start">
        <FormField
          :label="t('item.fields.icon')"
          :error="errors.icon"
        >
          <IconPicker
            v-model="state.icon"
            :fallback="kind === 'milestone' ? 'flag-checkered' : 'list-check'"
          />
        </FormField>

        <FormInput
          v-model="state.dueDate"
          type="date"
          name="dueDate"
          :label="t('item.fields.dueDate')"
          :error="errors.dueDate"
        />
      </div>

      <div
        v-if="kind === 'milestone'"
        class="wa-cluster wa-gap-m wa-align-items-start"
      >
        <FormInput
          v-model="state.releaseVersion"
          name="releaseVersion"
          :label="t('item.fields.releaseVersion')"
          :hint="t('item.fields.releaseVersionHint')"
          :error="errors.releaseVersion"
        />
        <FormSelect
          v-model="state.releaseStatus"
          name="releaseStatus"
          :items="releaseItems"
          :label="t('item.fields.releaseStatus')"
          :error="errors.releaseStatus"
        />
      </div>

      <FormInput
        v-if="withPrice"
        v-model="state.price"
        name="price"
        inputmode="decimal"
        :label="t('billing.milestone.price')"
        :hint="t('billing.milestone.priceHint')"
        :error="errors.price || errors.priceCents"
        class="item-price"
      />

      <FormField
        :label="t('item.fields.description')"
        :error="errors.description"
      >
        <RichTextEditor
          v-model="state.description"
          :placeholder="t('item.fields.descriptionPlaceholder')"
        />
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
        form="item-form"
        variant="brand"
        :loading="pending"
      >
        {{ t('common.save') }}
      </wa-button>
    </template>
  </AppDialog>
</template>

<style scoped>
.item-dialog {
  --width: 42rem;
}

.item-price {
  max-inline-size: 14rem;
}
</style>
