<script setup lang="ts">
import { copyToClipboard } from '~/utils/share'
import { isSystemRight, manualChapters, rightEnum } from '~/utils/manual'

/**
 * One chapter of the manual (Auftrag 54): purpose, steps (with pictures, which a switch hides for all chapters and the
 * browser remembers), good to know, and the example prompts to copy. A chapter whose right the person lacks stays
 * readable and says which right it needs.
 */
const { t, locale } = useI18n()
const route = useRoute()
const toast = useToast()
const { can } = useViewer()

const chapter = computed(() => manualChapters(locale.value).find(candidate => candidate.id === String(route.params.id)) ?? null)
const showImages = useStoredSwitch('mollo.help.images', true)

useHead({ title: () => chapter.value?.title ?? t('help.title') })

/** The right the chapter needs, said only when the person lacks it (a system right) or it depends on the project role. */
const rightNote = computed(() => {
  const right = chapter.value?.right

  if (!right) {
    return null
  }

  const name = t(`rights.names.${rightEnum(right)}`)

  if (isSystemRight(right)) {
    return can(rightEnum(right)) ? null : t('help.needsRight', { right: name })
  }

  return t('help.needsProjectRight', { right: name })
})

async function copy(prompt: string): Promise<void> {
  try {
    await copyToClipboard(prompt)
    toast.add({ title: t('help.copied'), color: 'success' })
  } catch {
    toast.add({ title: t('help.copyFailed'), color: 'error' })
  }
}
</script>

<template>
  <PageFrame
    :title="chapter?.title ?? t('help.notFound')"
    :breadcrumbs="[{ label: t('help.title'), to: '/help' }, { label: chapter?.title ?? t('help.notFound') }]"
  >
    <template
      v-if="chapter"
      #actions
    >
      <FormSwitch
        v-model="showImages"
        :label="t('help.showImages')"
      />
    </template>

    <EmptyState
      v-if="!chapter"
      icon="circle-question"
      :title="t('help.notFound')"
    />
    <article
      v-else
      class="help-chapter wa-stack wa-gap-l"
    >
      <AppCallout
        v-if="rightNote"
        variant="neutral"
        :title="rightNote"
      />

      <section
        v-if="chapter.purpose"
        class="wa-stack wa-gap-s"
      >
        <h2 class="wa-heading-m">
          {{ t('help.sections.purpose') }}
        </h2>
        <RichTextView :content="chapter.purpose" />
      </section>

      <section
        v-if="chapter.steps.length"
        class="wa-stack wa-gap-s"
      >
        <h2 class="wa-heading-m">
          {{ t('help.sections.steps') }}
        </h2>
        <ol class="help-steps">
          <li
            v-for="(step, index) in chapter.steps"
            :key="index"
            class="help-step"
          >
            <div class="help-step-body">
              <RichTextView :content="step.doc" />
              <template v-if="showImages">
                <img
                  v-for="image in step.images"
                  :key="image.src"
                  :src="image.src"
                  :alt="image.alt"
                  class="help-image"
                  loading="lazy"
                >
              </template>
            </div>
          </li>
        </ol>
      </section>

      <section
        v-if="chapter.notes"
        class="wa-stack wa-gap-s"
      >
        <h2 class="wa-heading-m">
          {{ t('help.sections.notes') }}
        </h2>
        <RichTextView :content="chapter.notes" />
      </section>

      <section
        v-if="chapter.prompts.length"
        class="wa-stack wa-gap-s"
      >
        <h2 class="wa-heading-m">
          {{ t('help.sections.prompts') }}
        </h2>
        <p
          v-if="chapter.tools.length"
          class="wa-body-s wa-color-text-quiet"
        >
          {{ t('help.tools', { tools: chapter.tools.join(', ') }) }}
        </p>
        <ul class="help-prompts wa-list-plain">
          <li
            v-for="(prompt, index) in chapter.prompts"
            :key="index"
            class="help-prompt"
          >
            <p class="help-prompt-text">
              {{ prompt }}
            </p>
            <wa-button
              size="s"
              appearance="outlined"
              variant="neutral"
              :aria-label="t('help.copyPrompt', { number: index + 1 })"
              data-copy-prompt
              @click="copy(prompt)"
            >
              <wa-icon
                slot="start"
                name="copy"
              ></wa-icon>
              {{ t('help.copy') }}
            </wa-button>
          </li>
        </ul>
      </section>
    </article>
  </PageFrame>
</template>

<style scoped>
.help-chapter {
  max-inline-size: 48rem;
}

.help-steps {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-m);
  margin: 0;
  padding-inline-start: var(--wa-space-l);
  list-style: decimal;
}

.help-step::marker {
  font-weight: var(--wa-font-weight-semibold);
}

.help-step-body {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-s);
}

.help-image {
  box-sizing: border-box;
  max-inline-size: 100%;
  padding: var(--wa-space-2xs);
  background: var(--wa-color-surface-default);
  border: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-neutral-border-normal);
  border-radius: var(--wa-border-radius-m);
  box-shadow: var(--wa-shadow-s);
}

.help-prompts {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-s);
  margin: 0;
  padding: 0;
}

.help-prompt {
  display: flex;
  align-items: flex-start;
  gap: var(--wa-space-s);
  padding: var(--wa-space-s) var(--wa-space-m);
  border: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  border-radius: var(--wa-border-radius-l);
}

.help-prompt-text {
  flex: 1;
  margin: 0;
  white-space: pre-line;
}
</style>
