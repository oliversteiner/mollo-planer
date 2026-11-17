<script setup lang="ts">
import { useApolloClient, useMutation } from '@vue/apollo-composable'
import { createTemplateFromProjectMutation, exportPlanQuery } from '~/graphql/planer'
import { downloadName, downloadText, planerMarkdown, readPlanerFile } from '~/utils/planer'

/**
 * Project settings: the planer file of the project (docs/auftrag-m4.md §7).
 * Download as planer.json or Markdown (planer.export), read a file in
 * (planer.import) and save the project as a template (templates.manage).
 */
const props = defineProps<{
  projectId: string
  projectName: string
  /** planer.export */
  canExport: boolean
  /** planer.import; not in an archived project */
  canImport: boolean
}>()

const { t, locale } = useI18n()
const toast = useToast()
const { resolveClient } = useApolloClient()
const { can: canSystem } = useViewer()
const { errors, pending: saving, run } = useGraphqlForm()

const language = ref<'de' | 'en'>(locale.value === 'en' ? 'en' : 'de')
const legend = ref(false)
const busy = ref(false)
const importing = ref(false)
const savingTemplate = ref(false)
const templateName = ref('')

const languages = computed(() => [
  { label: t('locale.DE'), value: 'de' },
  { label: t('locale.EN'), value: 'en' }
])

/** The canonical text of the file, always fresh from the server. */
async function fetchFile(): Promise<string | null> {
  busy.value = true

  try {
    const result = await resolveClient().query({ query: exportPlanQuery, variables: { projectId: props.projectId }, fetchPolicy: 'network-only' })

    return result.data.exportPlan
  } catch {
    // Reported by the api plugin.
    return null
  } finally {
    busy.value = false
  }
}

async function downloadJson(): Promise<void> {
  const text = await fetchFile()

  if (text) {
    downloadText(downloadName(props.projectName, 'planer.json'), text, 'application/json')
  }
}

async function downloadMarkdown(): Promise<void> {
  const text = await fetchFile()
  const read = text ? readPlanerFile(text) : null

  if (read && 'document' in read) {
    try {
      downloadText(downloadName(props.projectName, 'md'), planerMarkdown(read.document, language.value, legend.value), 'text/markdown')
    } catch (error) {
      // The view refuses a description or label that would break its structure.
      toast.add({ title: t('planer.file.markdownFailed'), description: error instanceof Error ? error.message : undefined, color: 'error' })
    }
  }
}

function startTemplate(): void {
  templateName.value = props.projectName
  errors.value = {}
  savingTemplate.value = true
}

const { mutate: createTemplate } = useMutation(createTemplateFromProjectMutation, { refetchQueries: ['Templates'] })

async function saveTemplate(): Promise<void> {
  const result = await run(() => createTemplate({ projectId: props.projectId, name: templateName.value.trim() }))
  const template = result?.data?.createTemplateFromProject

  if (template) {
    savingTemplate.value = false
    toast.add({ title: t('planer.file.templateSaved', { name: template.name }), color: 'success' })
  }
}
</script>

<template>
  <wa-card>
    <div slot="header">
      <h2 class="wa-heading-m">
        {{ t('planer.file.title') }}
      </h2>
      <p class="wa-body-s wa-color-text-quiet">
        {{ t('planer.file.intro') }}
      </p>
    </div>

    <div class="wa-stack wa-gap-l">
      <section
        v-if="canExport"
        class="wa-stack wa-gap-s"
      >
        <h3 class="wa-heading-s">
          {{ t('planer.file.download') }}
        </h3>
        <div class="wa-cluster wa-gap-s">
          <wa-button
            appearance="outlined"
            variant="neutral"
            :loading="busy"
            @click="downloadJson"
          >
            <wa-icon
              slot="start"
              name="file-code"
            ></wa-icon>
            {{ t('planer.file.asJson') }}
          </wa-button>
          <wa-button
            appearance="outlined"
            variant="neutral"
            :loading="busy"
            @click="downloadMarkdown"
          >
            <wa-icon
              slot="start"
              name="file-lines"
            ></wa-icon>
            {{ t('planer.file.asMarkdown') }}
          </wa-button>
        </div>
        <div class="file-options">
          <FormSelect
            v-model="language"
            name="language"
            :items="languages"
            :label="t('planer.file.language')"
            :hint="t('planer.file.languageHint')"
            class="file-language"
          />
          <FormSwitch
            v-model="legend"
            :label="t('planer.file.legend')"
            :hint="t('planer.file.legendHint')"
          />
        </div>
      </section>

      <section
        v-if="canImport"
        class="wa-stack wa-gap-s"
      >
        <h3 class="wa-heading-s">
          {{ t('planer.file.import') }}
        </h3>
        <p class="wa-body-s wa-color-text-quiet">
          {{ t('planer.file.importHint') }}
        </p>
        <div>
          <wa-button
            appearance="outlined"
            variant="brand"
            @click="importing = true"
          >
            <wa-icon
              slot="start"
              name="file-import"
            ></wa-icon>
            {{ t('planer.file.importButton') }}
          </wa-button>
        </div>
      </section>

      <section
        v-if="canExport && canSystem('TEMPLATES_MANAGE')"
        class="wa-stack wa-gap-s"
      >
        <h3 class="wa-heading-s">
          {{ t('planer.file.template') }}
        </h3>
        <p class="wa-body-s wa-color-text-quiet">
          {{ t('planer.file.templateHint') }}
        </p>
        <div>
          <wa-button
            appearance="outlined"
            variant="neutral"
            @click="startTemplate"
          >
            <wa-icon
              slot="start"
              name="copy"
            ></wa-icon>
            {{ t('planer.file.templateButton') }}
          </wa-button>
        </div>
      </section>
    </div>

    <PlanerImportModal
      v-model:open="importing"
      :project-id="projectId"
    />

    <AppDialog
      v-model:open="savingTemplate"
      :label="t('planer.file.templateTitle')"
      :description="t('planer.file.templateDescription')"
    >
      <form
        id="save-template"
        class="wa-stack wa-gap-m"
        @submit.prevent="saveTemplate"
      >
        <FormInput
          v-model="templateName"
          name="name"
          :label="t('templates.name')"
          :error="errors.name ?? errors.projectId"
          autofocus
          required
        />
      </form>
      <template #footer>
        <wa-button
          appearance="plain"
          variant="neutral"
          @click="savingTemplate = false"
        >
          {{ t('common.cancel') }}
        </wa-button>
        <wa-button
          type="submit"
          form="save-template"
          variant="brand"
          :loading="saving"
        >
          {{ t('planer.file.templateSubmit') }}
        </wa-button>
      </template>
    </AppDialog>
  </wa-card>
</template>

<style scoped>
.file-options {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: var(--wa-space-m);
}

.file-language {
  inline-size: 12rem;
}
</style>
