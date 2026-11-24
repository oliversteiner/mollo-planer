<script setup lang="ts">
import type { PasswordStep } from '~/composables/usePasswordStep'

/**
 * The password field of usePasswordStep(): shown inside the dialog that needs the confirmation, above the button of
 * the action (Auftrag 55, rule R11: no dialog above a dialog). It is not a form of its own, because it sits in the
 * form or the dialog of the action: Enter confirms, the buttons are `type="button"`. The field exists only while it
 * is shown, so a password manager does not fill other fields of the page (docs/fix-m1-f4.md F4.2).
 */
const props = defineProps<{ step: PasswordStep }>()

const { t } = useI18n()
const { viewer } = useViewer()

const root = ref<HTMLElement | null>(null)

// The field takes the focus when it appears: the person goes on typing.
watch(() => props.step.waiting, async (waiting) => {
  if (!waiting) {
    return
  }

  await nextTick()
  const input = root.value?.querySelector<HTMLElement & { updateComplete?: Promise<unknown> }>('wa-input')

  try {
    // A web component that was just inserted draws itself first; its inner field takes the focus only after that.
    await input?.updateComplete
    input?.focus()
  } catch {
    // No focus is better than an error: the person clicks into the field.
  }
})
</script>

<template>
  <div
    v-if="step.waiting"
    ref="root"
    class="password-confirm wa-stack wa-gap-s"
    role="group"
    :aria-label="t('security.confirmPassword.title')"
    @keydown.enter.prevent.stop="step.submit()"
  >
    <p class="password-confirm-text">
      {{ t('security.confirmPassword.inline') }}
    </p>
    <!-- For the password manager only: whose password this is. -->
    <input
      type="text"
      name="username"
      autocomplete="username"
      :value="viewer?.email ?? ''"
      readonly
      hidden
    >
    <FormInput
      :model-value="step.password"
      type="password"
      name="password"
      autocomplete="current-password"
      :label="t('auth.password')"
      :error="step.error || undefined"
      required
      @update:model-value="step.setPassword"
    />
    <div class="wa-cluster wa-gap-s">
      <wa-button
        type="button"
        variant="brand"
        size="s"
        :loading="step.checking"
        :disabled="!step.password"
        @click="step.submit()"
      >
        {{ t('security.confirmPassword.submit') }}
      </wa-button>
      <wa-button
        type="button"
        appearance="plain"
        variant="neutral"
        size="s"
        @click="step.cancel()"
      >
        {{ t('common.cancel') }}
      </wa-button>
    </div>
  </div>
</template>

<style scoped>
.password-confirm {
  padding: var(--wa-space-s);
  border: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  border-radius: var(--wa-border-radius-m);
  background-color: var(--wa-color-neutral-fill-quiet);
}

.password-confirm-text {
  margin: 0;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}
</style>
