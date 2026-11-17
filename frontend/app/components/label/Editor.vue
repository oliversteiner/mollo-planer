<script setup lang="ts">
import type { LabelRow, LabelLocale } from '~/utils/labels'
import { LABEL_COLORS, LABEL_LOCALES, MAX_LABELS, lacksName, newRow } from '~/utils/labels'

/**
 * The labels of a project or a template in their order: a name per language
 * (the one of the project or template is required), a category, a colour,
 * moving and removing. The rows are the caller's (v-model); a label that
 * exists keeps its key, however it is renamed.
 */
const props = defineProps<{
  /** The language the labels need a name in (of the project or the template). */
  required: LabelLocale
  /** A message about the list from the server (keys like "labels" or "labels.2.names"). */
  errors?: Record<string, string>
}>()

const rows = defineModel<LabelRow[]>({ default: () => [] })

const { t } = useI18n()

const colorItems = computed(() => LABEL_COLORS.map(color => ({ label: t(`labels.colors.${color}`), value: color })))

function addRow(): void {
  rows.value = [...rows.value, newRow(rows.value)]
}

function move(index: number, by: -1 | 1): void {
  const next = [...rows.value]
  const [row] = next.splice(index, 1)
  next.splice(index + by, 0, row!)
  rows.value = next
}

function remove(index: number): void {
  rows.value = rows.value.filter((_, position) => position !== index)
}

function label(row: LabelRow, index: number): string {
  return row.names[props.required] || row.key || String(index + 1)
}

/** The message of the server for one row (labels.N.names, labels.N.key). */
function rowError(index: number): string | undefined {
  return props.errors?.[`labels.${index}.names`] ?? props.errors?.[`labels.${index}.key`]
}

const listError = computed(() => props.errors?.labels)

/**
 * A note under a row: the name in the required language is missing while another is
 * filled in, or one language has no name and shows the name of the required one.
 */
function note(row: LabelRow): string | null {
  const other = LABEL_LOCALES.find(locale => locale !== props.required) ?? props.required
  const filled = LABEL_LOCALES.filter(locale => row.names[locale].trim() !== '')

  if (lacksName(row, props.required) && filled.length > 0) {
    return t('labels.requiredNote', { language: t(`labels.languages.${props.required}`) })
  }

  return filled.length === 1 && !lacksName(row, props.required)
    ? t('labels.fallbackNote', { language: t(`labels.languages.${other}`), shown: t(`labels.languages.${props.required}`) })
    : null
}
</script>

<template>
  <FormField
    :label="t('labels.field')"
    :hint="t('labels.editorHint', { language: t(`labels.languages.${required}`) })"
    :error="listError"
  >
    <ol class="label-rows">
      <li
        v-for="(row, index) in rows"
        :key="row.uid"
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
          v-for="locale in LABEL_LOCALES"
          :key="locale"
          v-model="row.names[locale]"
          :label="t('labels.nameIn', { language: t(`labels.languages.${locale}`) })"
          hide-label
          :placeholder="t('labels.nameIn', { language: t(`labels.languages.${locale}`) }) + (locale === required ? ' *' : '')"
          maxlength="40"
          :required="locale === required"
          :error="locale === required ? rowError(index) : undefined"
          :class="`label-row-name label-row-name-${locale.toLowerCase()}`"
          :data-locale="locale"
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
            :aria-label="t('labels.moveUp', { name: label(row, index) })"
            @click="move(index, -1)"
          >
            <wa-icon name="arrow-up"></wa-icon>
          </wa-button>
          <wa-button
            appearance="plain"
            variant="neutral"
            size="s"
            :disabled="index === rows.length - 1"
            :aria-label="t('labels.moveDown', { name: label(row, index) })"
            @click="move(index, 1)"
          >
            <wa-icon name="arrow-down"></wa-icon>
          </wa-button>
          <wa-button
            appearance="plain"
            variant="danger"
            size="s"
            :aria-label="t('labels.removeLabel', { name: label(row, index) })"
            @click="remove(index)"
          >
            <wa-icon name="trash"></wa-icon>
          </wa-button>
        </span>
        <p
          v-if="note(row)"
          class="label-row-note"
          :data-form-error="lacksName(row, required) ? '' : undefined"
        >
          {{ note(row) }}
        </p>
      </li>
    </ol>
    <wa-button
      appearance="outlined"
      variant="neutral"
      size="s"
      :disabled="rows.length >= MAX_LABELS"
      @click="addRow"
    >
      <wa-icon
        slot="start"
        name="plus"
      ></wa-icon>
      {{ t('labels.addLabel') }}
    </wa-button>
    <span
      v-if="rows.length >= MAX_LABELS"
      class="wa-body-s wa-color-text-quiet"
    >{{ t('labels.maxReached', { max: MAX_LABELS }) }}</span>
  </FormField>
</template>

<style scoped>
.label-rows {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-s);
  inline-size: 100%;
  margin: 0 0 var(--wa-space-s);
  padding: 0;
  list-style: none;
}

.label-row {
  display: grid;
  grid-template-columns: auto 8rem repeat(2, minmax(7rem, 1fr)) minmax(6rem, 0.8fr) auto;
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

.label-row-note {
  grid-column: 2 / -1;
  margin: 0;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-xs);
}

.label-row-note[data-form-error] {
  color: var(--wa-color-danger-on-quiet);
}

@media (max-width: 48em) {
  .label-row {
    grid-template-columns: auto 1fr auto;
  }

  .label-row-name,
  .label-row-category {
    grid-column: 2 / -1;
  }
}
</style>
