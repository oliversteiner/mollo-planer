<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import {
  cancelInvoiceMutation,
  deleteInvoiceDraftMutation,
  exportInvoicePdfMutation,
  invoiceQuery,
  issueInvoiceMutation,
  markInvoicePaidMutation,
  previewInvoicePdfMutation,
  updateInvoiceDraftMutation
} from '~/graphql/billing'
import { formatDate, today } from '~/utils/dates'
import { formatMoney } from '~/utils/money'

/**
 * One invoice (Auftrag 39 D). A draft: texts of the items and the note, items removed, preview as PDF, delete, and
 * issue after a question (final). Issued: the items as frozen, the kept PDF, «paid» with a date, «cancel» with a
 * reason. Nothing else changes after issuing (the backend refuses it too).
 */
const { t, locale } = useI18n()
const route = useRoute()
const toast = useToast()
const id = computed(() => String(route.params.id))

const { result, loading } = useQuery(invoiceQuery, () => ({ id: id.value }), { fetchPolicy: 'cache-and-network' })
const invoice = computed(() => result.value?.invoice ?? null)
const draft = computed(() => invoice.value?.status === 'DRAFT')
const title = computed(() => invoice.value?.number
  ? t('billing.invoice.titleNumber', { number: invoice.value.number })
  : t('billing.invoice.titleDraft'))

useHead({ title: () => title.value })

const options = { refetchQueries: 'active' as const, awaitRefetchQueries: true }
const { mutate: updateDraft } = useMutation(updateInvoiceDraftMutation, options)
const { mutate: deleteDraft } = useMutation(deleteInvoiceDraftMutation, options)
const { mutate: issue } = useMutation(issueInvoiceMutation, options)
const { mutate: markPaid } = useMutation(markInvoicePaidMutation, options)
const { mutate: cancel } = useMutation(cancelInvoiceMutation, options)
const { mutate: exportPdf } = useMutation(exportInvoicePdfMutation)
const { mutate: previewPdf } = useMutation(previewInvoicePdfMutation)
const { making, download } = useBillingPdf()
const { errors, pending, run } = useGraphqlForm()

/** The texts of a draft as they are typed; kept until saved. */
const notes = ref('')
const descriptions = reactive<Record<string, string>>({})

watch(invoice, (current) => {
  notes.value = current?.notes ?? ''
  for (const item of current?.items ?? []) {
    descriptions[item.id] = item.description ?? ''
  }
}, { immediate: true })

const groups = computed(() => {
  const byProject = new Map<string, { name: string, items: NonNullable<typeof invoice.value>['items'], totalCents: number }>()

  for (const item of invoice.value?.items ?? []) {
    const group = byProject.get(item.projectName) ?? { name: item.projectName, items: [], totalCents: 0 }
    group.items.push(item)
    group.totalCents += item.amountCents
    byProject.set(item.projectName, group)
  }

  return [...byProject.values()]
})

function money(cents: number): string {
  return formatMoney(cents, locale.value)
}

function itemsInput(without?: string) {
  return (invoice.value?.items ?? [])
    .filter(item => item.id !== without)
    .map(item => ({ id: item.id, description: descriptions[item.id]?.trim() || null }))
}

async function save(): Promise<void> {
  const saved = await run(() => updateDraft({ id: id.value, notes: notes.value.trim() || null, items: itemsInput() }))

  if (saved) {
    toast.add({ title: t('billing.invoice.saved'), color: 'success' })
  }
}

const removing = ref<{ id: string, title: string } | null>(null)

async function confirmRemove(): Promise<void> {
  const target = removing.value

  if (target) {
    await updateDraft({ id: id.value, items: itemsInput(target.id) })
    toast.add({ title: t('billing.invoice.removed', { title: target.title }), color: 'success' })
  }
}

const deleting = ref(false)

async function confirmDelete(): Promise<void> {
  await deleteDraft({ id: id.value })
  toast.add({ title: t('billing.invoice.deleted'), color: 'success' })
  await navigateTo('/billing/invoices')
}

const issuing = ref(false)

async function confirmIssue(): Promise<void> {
  // Unsaved texts go into the invoice first: what the person sees is what is issued.
  await updateDraft({ id: id.value, notes: notes.value.trim() || null, items: itemsInput() })
  const issued = await issue({ id: id.value })
  toast.add({ title: t('billing.invoice.issued', { number: issued?.data?.issueInvoice.number ?? '' }), color: 'success' })
}

const paying = ref(false)
const paidAt = ref(today())

async function confirmPaid(): Promise<void> {
  await markPaid({ id: id.value, paidAt: paidAt.value })
  toast.add({ title: t('billing.invoice.paidDone'), color: 'success' })
}

const cancelling = ref(false)
const reason = ref('')

async function confirmCancel(): Promise<void> {
  await cancel({ id: id.value, reason: reason.value.trim() })
  toast.add({ title: t('billing.invoice.cancelled'), color: 'success' })
}

function downloadPdf(): void {
  void download('pdf', async () => (await exportPdf({ id: id.value }))?.data?.exportInvoicePdf)
}

function preview(): void {
  void download('preview', async () => (await previewPdf({ id: id.value }))?.data?.previewInvoicePdf)
}

const address = computed(() => {
  const value = invoice.value?.recipient ?? invoice.value?.customer

  return value
    ? [value.company, value.name, [value.street, value.houseNumber].filter(Boolean).join(' '), `${value.country !== 'CH' ? `${value.country}-` : ''}${value.postalCode} ${value.city}`].filter(Boolean)
    : []
})
</script>

<template>
  <PageFrame
    :title="title"
    :breadcrumbs="[{ label: t('nav.billing') }, { label: t('billing.invoices.title'), to: '/billing/invoices' }, { label: title }]"
  >
    <template
      v-if="invoice"
      #title-end
    >
      <BillingInvoiceStatusBadge
        :status="invoice.status"
        :overdue="invoice.overdue"
      />
    </template>

    <template
      v-if="invoice"
      #actions
    >
      <template v-if="draft">
        <wa-button
          appearance="plain"
          variant="danger"
          @click="deleting = true"
        >
          <wa-icon
            slot="start"
            name="trash-can"
          ></wa-icon>
          {{ t('billing.invoice.delete') }}
        </wa-button>
        <wa-button
          appearance="outlined"
          variant="neutral"
          :loading="making === 'preview'"
          @click="preview"
        >
          <wa-icon
            slot="start"
            name="file-pdf"
          ></wa-icon>
          {{ t('billing.invoice.preview') }}
        </wa-button>
        <wa-button
          variant="brand"
          @click="issuing = true"
        >
          <wa-icon
            slot="start"
            name="paper-plane"
          ></wa-icon>
          {{ t('billing.invoice.issue') }}
        </wa-button>
      </template>
      <template v-else>
        <wa-button
          v-if="invoice.status === 'ISSUED'"
          appearance="plain"
          variant="danger"
          @click="cancelling = true"
        >
          <wa-icon
            slot="start"
            name="ban"
          ></wa-icon>
          {{ t('billing.invoice.cancel') }}
        </wa-button>
        <wa-button
          v-if="invoice.status === 'ISSUED'"
          appearance="outlined"
          variant="neutral"
          @click="paying = true"
        >
          <wa-icon
            slot="start"
            name="circle-check"
          ></wa-icon>
          {{ t('billing.invoice.markPaid') }}
        </wa-button>
        <wa-button
          variant="brand"
          :disabled="!invoice.hasPdf"
          :loading="making === 'pdf'"
          @click="downloadPdf"
        >
          <wa-icon
            slot="start"
            name="file-arrow-down"
          ></wa-icon>
          {{ t('billing.invoice.download') }}
        </wa-button>
      </template>
    </template>

    <wa-skeleton
      v-if="loading && !invoice"
      effect="sheen"
      class="invoice-skeleton"
    ></wa-skeleton>
    <EmptyState
      v-else-if="!invoice"
      icon="file-invoice-dollar"
      :title="t('billing.invoice.notFound')"
    />
    <div
      v-else
      class="invoice-grid"
    >
      <wa-card>
        <h2
          slot="header"
          class="wa-heading-m"
        >
          {{ t('billing.invoice.facts') }}
        </h2>
        <dl class="invoice-facts">
          <dt>{{ t('billing.invoices.customer') }}</dt>
          <dd>
            <NuxtLink :to="`/billing/customers/${invoice.customer.id}`">
              {{ invoice.customer.company || invoice.customer.name }}
            </NuxtLink>
          </dd>
          <dt>{{ t('billing.invoice.address') }}</dt>
          <dd>
            <span
              v-for="(line, index) in address"
              :key="index"
              class="invoice-address-line"
            >{{ line }}</span>
          </dd>
          <template v-if="invoice.issueDate">
            <dt>{{ t('billing.invoices.issueDate') }}</dt>
            <dd>{{ formatDate(invoice.issueDate, locale) }}</dd>
          </template>
          <template v-if="invoice.dueDate">
            <dt>{{ t('billing.invoices.dueDate') }}</dt>
            <dd>{{ formatDate(invoice.dueDate, locale) }}</dd>
          </template>
          <template v-if="invoice.reference">
            <dt>{{ t('billing.invoice.reference') }}</dt>
            <dd class="invoice-mono">
              {{ invoice.reference.replace(/(.{4})(?=.)/g, '$1 ') }}
            </dd>
          </template>
          <template v-if="invoice.paidAt">
            <dt>{{ t('billing.invoice.paidAt') }}</dt>
            <dd>{{ formatDate(invoice.paidAt, locale) }}</dd>
          </template>
          <template v-if="invoice.cancelReason">
            <dt>{{ t('billing.invoice.cancelReason') }}</dt>
            <dd>{{ invoice.cancelReason }}</dd>
          </template>
          <dt>{{ t('billing.invoice.language') }}</dt>
          <dd>{{ t(`locale.${invoice.locale}`) }}</dd>
        </dl>
        <AppCallout
          v-if="draft"
          variant="brand"
          :title="t('billing.invoice.draftHint')"
          class="invoice-hint"
        />
      </wa-card>

      <wa-card class="invoice-items-card">
        <h2
          slot="header"
          class="wa-heading-m"
        >
          {{ t('billing.invoice.items') }}
        </h2>
        <form
          class="wa-stack wa-gap-l"
          @submit.prevent="save"
        >
          <section
            v-for="group in groups"
            :key="group.name"
            class="wa-stack wa-gap-s"
          >
            <h3 class="wa-heading-s invoice-project">
              <span>{{ group.name }}</span>
              <span class="invoice-amount">{{ money(group.totalCents) }}</span>
            </h3>
            <ul class="invoice-items wa-list-plain">
              <li
                v-for="item in group.items"
                :key="item.id"
                class="invoice-item"
              >
                <div class="invoice-item-head">
                  <span class="invoice-item-title">{{ item.title }}</span>
                  <span class="invoice-amount">{{ money(item.amountCents) }}</span>
                  <wa-button
                    v-if="draft"
                    size="s"
                    appearance="plain"
                    variant="neutral"
                    :aria-label="t('billing.invoice.removeItem', { title: item.title })"
                    @click="removing = { id: item.id, title: item.title }"
                  >
                    <wa-icon name="xmark"></wa-icon>
                  </wa-button>
                </div>
                <FormTextarea
                  v-if="draft"
                  v-model="descriptions[item.id]"
                  :name="`description-${item.id}`"
                  :label="t('billing.invoice.description')"
                  :hint="t('billing.invoice.descriptionHint')"
                  :rows="2"
                />
                <p
                  v-else-if="item.description"
                  class="wa-body-s wa-color-text-quiet invoice-description"
                >
                  {{ item.description }}
                </p>
              </li>
            </ul>
          </section>

          <div class="invoice-total">
            <span>{{ t('billing.invoices.total') }}</span>
            <span class="invoice-amount">CHF {{ money(invoice.totalCents) }}</span>
          </div>

          <FormTextarea
            v-if="draft"
            v-model="notes"
            name="notes"
            :label="t('billing.invoice.notes')"
            :hint="t('billing.invoice.notesHint')"
            :error="errors.notes"
            :rows="3"
          />
          <p
            v-else-if="invoice.notes"
            class="wa-body-s invoice-description"
          >
            {{ invoice.notes }}
          </p>

          <div v-if="draft">
            <wa-button
              type="submit"
              variant="brand"
              appearance="outlined"
              :loading="pending"
            >
              {{ t('common.save') }}
            </wa-button>
          </div>
        </form>
      </wa-card>
    </div>

    <ConfirmModal
      :open="issuing"
      :title="t('billing.invoice.issueTitle')"
      :description="t('billing.invoice.issueDescription')"
      :confirm-label="t('billing.invoice.issueConfirm')"
      confirm-variant="brand"
      :action="confirmIssue"
      @close="issuing = false"
    />
    <ConfirmModal
      :open="deleting"
      :title="t('billing.invoice.deleteTitle')"
      :description="t('billing.invoice.deleteDescription')"
      :confirm-label="t('common.delete')"
      :action="confirmDelete"
      @close="deleting = false"
    />
    <ConfirmModal
      :open="removing !== null"
      :title="t('billing.invoice.removeTitle', { title: removing?.title ?? '' })"
      :description="t('billing.invoice.removeDescription')"
      :confirm-label="t('billing.invoice.removeConfirm')"
      :action="confirmRemove"
      @close="removing = null"
    />
    <ConfirmModal
      :open="paying"
      :title="t('billing.invoice.markPaidTitle')"
      :description="t('billing.invoice.markPaidDescription')"
      :confirm-label="t('billing.invoice.markPaid')"
      confirm-variant="brand"
      :confirm-disabled="!paidAt"
      :action="confirmPaid"
      @close="paying = false"
    >
      <FormInput
        v-model="paidAt"
        type="date"
        name="paidAt"
        :label="t('billing.invoice.paidAt')"
        required
      />
    </ConfirmModal>
    <ConfirmModal
      :open="cancelling"
      :title="t('billing.invoice.cancelTitle', { number: invoice?.number ?? '' })"
      :description="t('billing.invoice.cancelDescription')"
      :confirm-label="t('billing.invoice.cancelConfirm')"
      :confirm-disabled="reason.trim().length < 3"
      :action="confirmCancel"
      @close="cancelling = false; reason = ''"
    >
      <FormTextarea
        v-model="reason"
        name="reason"
        :label="t('billing.invoice.cancelReason')"
        :hint="t('billing.invoice.cancelReasonHint')"
        :rows="2"
        required
      />
    </ConfirmModal>
  </PageFrame>
</template>

<style scoped>
.invoice-skeleton {
  block-size: 12rem;
}

.invoice-grid {
  display: grid;
  grid-template-columns: minmax(16rem, 1fr) minmax(0, 2fr);
  gap: var(--wa-space-l);
  align-items: start;
}

@media (max-width: 40em) {
  .invoice-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}

.invoice-facts {
  display: grid;
  grid-template-columns: max-content 1fr;
  gap: var(--wa-space-2xs) var(--wa-space-m);
  margin: 0;
  font-size: var(--wa-font-size-s);
}

.invoice-facts dt {
  color: var(--wa-color-text-quiet);
}

.invoice-facts dd {
  margin: 0;
}

.invoice-address-line {
  display: block;
}

.invoice-mono {
  font-variant-numeric: tabular-nums;
}

.invoice-hint {
  margin-block-start: var(--wa-space-m);
}

.invoice-project {
  display: flex;
  justify-content: space-between;
  gap: var(--wa-space-s);
  margin: 0;
  padding-block-end: var(--wa-space-2xs);
  border-block-end: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
}

.invoice-items {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-s);
  margin: 0;
  padding: 0;
}

.invoice-item {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-2xs);
}

.invoice-item-head {
  display: flex;
  align-items: center;
  gap: var(--wa-space-s);
}

.invoice-item-title {
  flex: 1;
  min-inline-size: 0;
}

.invoice-amount {
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.invoice-description {
  margin: 0;
  white-space: pre-line;
}

.invoice-total {
  display: flex;
  justify-content: space-between;
  padding-block-start: var(--wa-space-s);
  border-block-start: var(--wa-border-width-m) var(--wa-border-style) var(--wa-color-text-normal);
  font-weight: var(--wa-font-weight-semibold);
}
</style>
