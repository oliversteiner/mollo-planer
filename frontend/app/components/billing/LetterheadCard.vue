<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import { pdfFrameQuery, removeLetterheadMutation, sampleInvoicePdfMutation, updatePdfFrameMutation, uploadLetterheadMutation } from '~/graphql/billing'
import { classifyApolloError } from '~/utils/api'
import { formatDateTime } from '~/utils/dates'
import { LETTERHEAD_GUIDE_URL } from '~/utils/letterhead-guide'

/**
 * Letterhead and layout of the PDFs (Auftrag 39 F): the guide with the measures (always in sight, also when an upload
 * fails), the upload of the PDF (page 1, optionally the following pages), the margins and the address field in mm,
 * the window of the envelope, the font, and a sample invoice to check it all. Changes need a fresh password, asked
 * in a field of this card (R11). Invoices issued before keep their PDF.
 */
const { t, locale } = useI18n()
const toast = useToast()

const { result } = useQuery(pdfFrameQuery, null, { fetchPolicy: 'cache-and-network' })
const frame = computed(() => result.value?.pdfFrame ?? null)
const refetch = { refetchQueries: ['PdfFrame'], awaitRefetchQueries: true }
const { mutate: upload } = useMutation(uploadLetterheadMutation, { ...refetch, context: { silentErrors: true } })
const { mutate: remove } = useMutation(removeLetterheadMutation, refetch)
const { mutate: saveFrame } = useMutation(updatePdfFrameMutation, refetch)
const { mutate: sample } = useMutation(sampleInvoicePdfMutation)
const { making, download } = useBillingPdf()

const uploadStep = usePasswordStep()
const layoutStep = usePasswordStep()
const removing = ref(false)
const uploadError = ref('')
const input = ref<HTMLInputElement | null>(null)
const { errors, run } = useGraphqlForm()

const keys = ['topFirst', 'topFollowing', 'bottom', 'left', 'right', 'addressLeft', 'addressTop', 'addressWidth', 'addressHeight'] as const
type LayoutKey = typeof keys[number]
const layout = reactive<Record<LayoutKey, string>>(Object.fromEntries(keys.map(key => [key, ''])) as Record<LayoutKey, string>)
const font = ref('dejavusans')
const addressWindow = ref('left')

watch(frame, (current) => {
  if (current) {
    for (const key of keys) {
      layout[key] = String(current.layout[key])
    }
    font.value = current.font
    addressWindow.value = current.addressWindow
  }
}, { immediate: true })

const fontItems = computed(() => (frame.value?.fonts ?? []).map(item => ({ label: item.name, value: item.key })))
const windowItems = computed(() => [
  { label: t('billing.letterhead.windowLeft'), value: 'left' },
  { label: t('billing.letterhead.windowRight'), value: 'right' }
])

/** The window side moves the address field to the defaults of that side; Oliver fine-tunes after measuring. */
watch(addressWindow, (side, before) => {
  if (before && side !== before && frame.value) {
    const width = Number(layout.addressWidth) || frame.value.defaults.addressWidth
    layout.addressLeft = String(side === 'right' ? 210 - frame.value.defaults.left - width : frame.value.defaults.addressLeft)
  }
})

function resetLayout(): void {
  if (frame.value) {
    for (const key of keys) {
      layout[key] = String(frame.value.defaults[key])
    }
  }
}

async function onFile(event: Event): Promise<void> {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  target.value = ''

  if (!file) {
    return
  }
  uploadError.value = ''

  await uploadStep.run(async () => {
    try {
      await upload({ file })
      toast.add({ title: t('billing.letterhead.uploaded'), color: 'success' })
    } catch (error) {
      const failure = classifyApolloError(error)

      if (failure.fields.password) {
        return NEEDS_PASSWORD
      }
      uploadError.value = failure.kind === 'refusal' && failure.message ? failure.message : t('apiError.unknown')
    }
  })
}

async function confirmRemove(): Promise<void> {
  await remove()
  toast.add({ title: t('billing.letterhead.removed'), color: 'success' })
}

async function submitLayout(): Promise<void> {
  if (layoutStep.working) {
    return
  }

  await layoutStep.run(async () => {
    const saved = await run(() => saveFrame({
      input: {
        layout: Object.fromEntries(keys.map(key => [key, Number(layout[key])])) as Record<LayoutKey, number>,
        font: font.value,
        addressWindow: addressWindow.value
      }
    }))

    if (errors.value.password) {
      return NEEDS_PASSWORD
    }

    if (saved) {
      toast.add({ title: t('billing.letterhead.layoutSaved'), color: 'success' })
    }
  })
}

function layoutError(key: LayoutKey): string | undefined {
  return errors.value[`layout.${key}`]
}

function downloadSample(): void {
  void download('sample', async () => (await sample({ locale: locale.value === 'en' ? 'EN' : 'DE' }))?.data?.sampleInvoicePdf)
}
</script>

<template>
  <wa-card>
    <h2
      slot="header"
      class="wa-heading-m"
    >
      {{ t('billing.letterhead.title') }}
    </h2>

    <wa-skeleton
      v-if="!frame"
      effect="sheen"
      class="letterhead-skeleton"
    ></wa-skeleton>
    <div
      v-else
      class="wa-stack wa-gap-l"
    >
      <AppCallout
        variant="brand"
        :title="t('billing.letterhead.guideTitle')"
        :description="t('billing.letterhead.guideText')"
      >
        <wa-button
          :href="LETTERHEAD_GUIDE_URL"
          target="_blank"
          download
          size="s"
          appearance="outlined"
          variant="brand"
          class="letterhead-guide"
        >
          <wa-icon
            slot="start"
            name="file-pdf"
          ></wa-icon>
          {{ t('billing.letterhead.guide') }}
        </wa-button>
      </AppCallout>

      <section class="wa-stack wa-gap-s">
        <h3 class="wa-heading-s">
          {{ t('billing.letterhead.file') }}
        </h3>
        <p
          v-if="frame.letterhead"
          class="wa-body-s"
        >
          {{ t('billing.letterhead.current', { name: frame.letterhead.fileName, date: formatDateTime(frame.letterhead.uploadedAt, locale) }) }}
          {{ t('billing.letterhead.pages', frame.letterhead.pages) }}
        </p>
        <p
          v-else
          class="wa-body-s wa-color-text-quiet"
        >
          {{ t('billing.letterhead.none') }}
        </p>
        <p class="wa-caption-s wa-color-text-quiet">
          {{ t('billing.letterhead.fileHint') }}
        </p>
        <div class="wa-cluster wa-gap-s">
          <wa-button
            appearance="outlined"
            variant="neutral"
            :loading="uploadStep.working"
            :disabled="uploadStep.waiting"
            @click="input?.click()"
          >
            <wa-icon
              slot="start"
              name="upload"
            ></wa-icon>
            {{ frame.letterhead ? t('billing.letterhead.replace') : t('billing.letterhead.choose') }}
          </wa-button>
          <wa-button
            v-if="frame.letterhead"
            appearance="plain"
            variant="danger"
            @click="removing = true"
          >
            {{ t('billing.letterhead.remove') }}
          </wa-button>
          <input
            ref="input"
            type="file"
            accept=".pdf,application/pdf"
            class="wa-visually-hidden"
            tabindex="-1"
            data-letterhead-file
            @change="onFile"
          >
        </div>
        <PasswordConfirmField :step="uploadStep" />
        <AppCallout
          v-if="uploadError"
          variant="danger"
          :title="uploadError"
        >
          <a
            :href="LETTERHEAD_GUIDE_URL"
            target="_blank"
            download
          >{{ t('billing.letterhead.guide') }}</a>
        </AppCallout>
      </section>

      <form
        class="wa-stack wa-gap-m"
        @submit.prevent="submitLayout"
      >
        <h3 class="wa-heading-s">
          {{ t('billing.letterhead.layout') }}
        </h3>
        <p class="wa-caption-s wa-color-text-quiet">
          {{ t('billing.letterhead.layoutHint') }}
        </p>
        <div class="letterhead-grid">
          <FormInput
            v-for="key in keys"
            :key="key"
            v-model="layout[key]"
            type="number"
            step="0.5"
            min="0"
            :name="key"
            :label="t(`billing.letterhead.fields.${key}`)"
            :error="layoutError(key)"
            required
          />
        </div>
        <FormSelect
          v-model="addressWindow"
          name="addressWindow"
          :items="windowItems"
          :label="t('billing.letterhead.window')"
          :hint="t('billing.letterhead.windowHint')"
          :error="errors.addressWindow"
        />
        <FormSelect
          v-model="font"
          name="font"
          :items="fontItems"
          :label="t('billing.letterhead.font')"
          :error="errors.font"
        />
        <PasswordConfirmField :step="layoutStep" />
        <div class="wa-cluster wa-gap-s">
          <wa-button
            type="submit"
            variant="brand"
            :loading="layoutStep.working"
            :disabled="layoutStep.waiting"
          >
            {{ t('common.save') }}
          </wa-button>
          <wa-button
            appearance="plain"
            variant="neutral"
            @click="resetLayout"
          >
            {{ t('billing.letterhead.defaults') }}
          </wa-button>
          <wa-button
            appearance="outlined"
            variant="neutral"
            :loading="making === 'sample'"
            @click="downloadSample"
          >
            <wa-icon
              slot="start"
              name="file-pdf"
            ></wa-icon>
            {{ t('billing.letterhead.sample') }}
          </wa-button>
        </div>
      </form>
    </div>

    <ConfirmModal
      :open="removing"
      :title="t('billing.letterhead.removeTitle')"
      :description="t('billing.letterhead.removeDescription')"
      :confirm-label="t('billing.letterhead.remove')"
      requires-password
      :action="confirmRemove"
      @close="removing = false"
    />
  </wa-card>
</template>

<style scoped>
.letterhead-skeleton {
  block-size: 12rem;
}

.letterhead-guide {
  margin-block-start: var(--wa-space-xs);
}

.letterhead-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(10rem, 1fr));
  gap: var(--wa-space-s);
}
</style>
