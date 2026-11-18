<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import type { ActionMenuItem } from '~/components/ActionMenu.vue'
import type { TemplateFormTarget } from '~/components/template/FormModal.vue'
import type { TemplatesQuery } from '~/graphql/generated/graphql'
import { deleteTemplateMutation, duplicateTemplateMutation, templatesQuery } from '~/graphql/labels'
import { createTemplateFromFileMutation } from '~/graphql/planer'
import { outlineSize } from '~/utils/labels'
import { downloadName, downloadText, readPlanerFile } from '~/utils/planer'

/**
 * Templates of the app: the built-in ones and own ones. Everybody signed in
 * sees them all, with their labels and outline. Those with templates.manage
 * create and duplicate, the creator (or templates.manageAny) changes and
 * deletes own ones; built-in ones stay as they are.
 */
const { t } = useI18n()
const toast = useToast()

useHead({ title: () => t('templates.pageTitle') })

type Template = TemplatesQuery['templates'][number]

const { result, loading } = useQuery(templatesQuery, null, { fetchPolicy: 'cache-and-network' })
const templates = computed(() => result.value?.templates ?? [])
const canManage = computed(() => (result.value?.viewerRights ?? []).includes('TEMPLATES_MANAGE'))

const editing = ref<TemplateFormTarget | null>(null)

const { mutate: duplicateTemplate } = useMutation(duplicateTemplateMutation, templateRefresh)
const { mutate: deleteTemplate } = useMutation(deleteTemplateMutation, templateRefresh)

const { mutate: createFromFile } = useMutation(createTemplateFromFileMutation, templateRefresh)
const { errors: uploadErrors, run: runUpload } = useGraphqlForm()
const fileInput = ref<HTMLInputElement | null>(null)

// The buttons are in the page actions, which the calm view hides.
useCommands(() => [
  { id: 'templates-upload', label: t('templates.upload'), icon: 'file-import', group: 'action', run: () => fileInput.value?.click() },
  {
    id: 'templates-create',
    label: t('templates.create'),
    icon: 'plus',
    group: 'action',
    run: () => {
      editing.value = {}
    }
  }
])
const uploadProblem = ref<string | null>(null)

async function upload(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]

  if (!file) {
    return
  }

  uploadProblem.value = null
  const text = await file.text()
  input.value = ''
  const read = readPlanerFile(text)

  if ('problem' in read) {
    uploadProblem.value = t(`planer.import.${read.problem}`)

    return
  }

  const created = (await runUpload(() => createFromFile({ document: text })))?.data?.createTemplateFromFile

  if (created) {
    toast.add({ title: t('templates.uploaded', { name: created.name }), color: 'success' })
  } else {
    uploadProblem.value = uploadErrors.value.document ?? null
  }
}

function download(template: Template): void {
  downloadText(downloadName(template.name, 'planer.json'), template.file, 'application/json')
}

/** The outline as a planer document, for the tree. */
function documentOf(template: Template) {
  const read = readPlanerFile(template.file)

  return 'document' in read ? read.document : null
}

const deletion = useConfirmedDelete<Template>(template => deleteTemplate({ id: template.id }), {
  onDeleted: () => toast.add({ title: t('templates.deleted'), color: 'success' })
})

async function duplicate(template: Template): Promise<void> {
  const copy = (await duplicateTemplate({ id: template.id }).catch(() => null))?.data?.duplicateTemplate

  if (copy) {
    toast.add({ title: t('templates.duplicated', { name: copy.name }), color: 'success' })
    const fresh = templates.value.find(entry => entry.id === copy.id)

    if (fresh) {
      editing.value = { template: fresh }
    }
  }
}

function actions(template: Template): ActionMenuItem[] {
  const items: ActionMenuItem[] = []

  if (template.viewerCanUpdate) {
    items.push({ label: t('common.edit'), icon: 'pen', onSelect: () => (editing.value = { template }) })
  }
  if (canManage.value) {
    items.push({ label: t('templates.duplicate'), icon: 'copy', onSelect: () => duplicate(template) })
  }
  items.push({ label: t('templates.download'), icon: 'file-arrow-down', onSelect: () => download(template) })
  if (template.viewerCanDelete) {
    items.push({ label: t('common.delete'), icon: 'trash', color: 'error', onSelect: () => deletion.ask(template) })
  }

  return items
}

function labelItems(template: Template) {
  return template.labels.map((label, position) => ({ id: label.key, name: label.name, color: label.color, position }))
}

/** "2 Milestones, 4 ToDos, 7 Tasks" */
function size(template: Template): string {
  return outlineSize(template.outline, (key, count) => t(key, { n: count }, count))
}
</script>

<template>
  <PageFrame
    :title="t('templates.pageTitle')"
    :breadcrumbs="[{ label: t('nav.workspace'), to: '/' }, { label: t('templates.pageTitle') }]"
    :description="t('templates.pageIntro')"
  >
    <template
      v-if="canManage"
      #actions
    >
      <wa-button
        appearance="outlined"
        variant="neutral"
        @click="fileInput?.click()"
      >
        <wa-icon
          slot="start"
          name="file-import"
        ></wa-icon>
        {{ t('templates.upload') }}
      </wa-button>
      <wa-button
        variant="brand"
        @click="editing = {}"
      >
        <wa-icon
          slot="start"
          name="plus"
        ></wa-icon>
        {{ t('templates.create') }}
      </wa-button>
    </template>

    <input
      ref="fileInput"
      type="file"
      accept=".json,application/json"
      class="wa-visually-hidden"
      tabindex="-1"
      @change="upload"
    >
    <AppCallout
      v-if="uploadProblem"
      variant="danger"
      :description="uploadProblem"
    />

    <wa-skeleton
      v-if="loading && !result"
      effect="sheen"
      class="templates-skeleton"
    ></wa-skeleton>
    <ul
      v-else
      class="templates wa-list-plain"
    >
      <li
        v-for="template in templates"
        :key="template.id"
      >
        <wa-card class="template">
          <div
            slot="header"
            class="template-head"
          >
            <div class="template-title">
              <h2 class="wa-heading-m">
                {{ template.name }}
              </h2>
              <wa-badge
                v-if="template.isBuiltin"
                variant="neutral"
                appearance="outlined"
              >
                {{ t('templates.builtin') }}
              </wa-badge>
              <wa-badge
                v-else-if="template.createdBy"
                variant="neutral"
                appearance="outlined"
              >
                {{ t('templates.by', { name: template.createdBy.name }) }}
              </wa-badge>
            </div>
            <ActionMenu
              v-if="actions(template).length"
              :items="actions(template)"
              :label="t('templates.actionsOf', { name: template.name })"
            />
          </div>
          <div class="wa-stack wa-gap-s">
            <p
              v-if="template.description"
              class="template-quiet"
            >
              {{ template.description }}
            </p>
            <TaskLabels :labels="labelItems(template)" />
            <wa-details
              v-if="template.outline.length"
              :summary="`${t('templates.outline')}: ${size(template)}`"
            >
              <PlanerTree
                v-if="documentOf(template)"
                :document="documentOf(template)!"
              />
            </wa-details>
            <p
              v-else
              class="template-quiet"
            >
              {{ t('templates.noOutline') }}
            </p>
            <p class="template-usage">
              {{ t('templates.usedBy', template.projectCount) }}
            </p>
          </div>
        </wa-card>
      </li>
    </ul>

    <TemplateFormModal
      :target="editing"
      @close="editing = null"
    />
    <ConfirmModal
      :open="deletion.isOpen"
      :title="t('templates.deleteTitle', { name: deletion.target?.name ?? '' })"
      :description="t('templates.deleteDescription')"
      :confirm-label="t('common.delete')"
      :action="deletion.confirm"
      @close="deletion.close"
    />
  </PageFrame>
</template>

<style scoped>
.templates-skeleton {
  block-size: 12rem;
}

.templates {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 24rem), 1fr));
  gap: var(--wa-space-l);
  margin: 0;
  padding: 0;
}

.template {
  block-size: 100%;
}

.template-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--wa-space-s);
}

.template-title {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-xs);
}

.template-title h2 {
  margin: 0;
}

.template-quiet,
.template-usage {
  margin: 0;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}

.template-usage {
  font-size: var(--wa-font-size-xs);
}
</style>
