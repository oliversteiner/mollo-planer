<script setup lang="ts">
import { useMutation } from '@vue/apollo-composable'
import type { Right, RoleScope } from '~/graphql/generated/graphql'
import { createRoleFunctionMutation, updateRoleFunctionMutation } from '~/graphql/roles'

/**
 * Create a function of one's own or change a function: names in both
 * languages and the rights of its scope. The scope is chosen once; the key
 * is made by the backend.
 */
export interface RoleFunctionFormTarget {
  function?: {
    id: string
    scope: RoleScope
    names: { de: string, en: string }
    rights: readonly Right[]
  }
  /** Scope of a new function (the tab it was started from). */
  scope?: RoleScope
}

const props = defineProps<{
  target: RoleFunctionFormTarget | null
  systemRights: readonly Right[]
  projectRights: readonly Right[]
}>()
const emit = defineEmits<{ close: [] }>()

const { t } = useI18n()
const toast = useToast()
const { errors, pending, run } = useGraphqlForm()

const state = reactive<{ scope: RoleScope, de: string, en: string, rights: Right[] }>({ scope: 'PROJECT', de: '', en: '', rights: [] })

const open = computed({
  get: () => props.target !== null,
  set: (value: boolean) => {
    if (!value) {
      emit('close')
    }
  }
})

const isNew = computed(() => !props.target?.function)

watch(() => props.target, (target) => {
  const entry = target?.function

  errors.value = {}
  state.scope = entry?.scope ?? target?.scope ?? 'PROJECT'
  state.de = entry?.names.de ?? ''
  state.en = entry?.names.en ?? ''
  state.rights = [...(entry?.rights ?? [])]
}, { immediate: true })

const scopeItems = computed(() => (['SYSTEM', 'PROJECT'] as const).map(scope => ({ label: t(`roleSettings.scopes.${scope}`), value: scope })))

/** Only rights of the function's scope can be chosen. */
const choices = computed(() => (state.scope === 'SYSTEM' ? props.systemRights : props.projectRights))

function setScope(scope: string | null): void {
  state.scope = scope === 'SYSTEM' ? 'SYSTEM' : 'PROJECT'
  state.rights = []
}

/** A refusal that belongs to no single field: a ground rule, or the function itself. */
const refusal = computed(() => errors.value.rights ?? errors.value.id)

const { mutate: createFunction } = useMutation(createRoleFunctionMutation, roleSettingsRefresh)
const { mutate: updateFunction } = useMutation(updateRoleFunctionMutation, roleSettingsRefresh)

async function save(): Promise<void> {
  const names = { de: state.de.trim(), en: state.en.trim() }
  const rights = choices.value.filter(right => state.rights.includes(right))
  const entry = props.target?.function

  const result = await run<unknown>(() => (entry
    ? updateFunction({ id: entry.id, input: { names, rights } })
    : createFunction({ input: { scope: state.scope, names, rights } })))

  if (result) {
    toast.add({ title: t('roleSettings.saved'), color: 'success' })
    emit('close')
  }
}
</script>

<template>
  <AppDialog
    v-model:open="open"
    :label="isNew ? t('roleSettings.createFunction') : t('roleSettings.editFunction')"
    class="role-function-dialog"
  >
    <form
      id="role-function-form"
      class="wa-stack wa-gap-m"
      @submit.prevent="save"
    >
      <AppCallout
        v-if="refusal"
        variant="danger"
        :title="refusal"
      />
      <FormSelect
        v-if="isNew"
        :model-value="state.scope"
        :items="scopeItems"
        :label="t('roleSettings.scope')"
        :hint="t('roleSettings.scopeHint')"
        @update:model-value="setScope"
      />
      <FormInput
        v-model="state.de"
        name="nameDe"
        :label="t('roleSettings.nameDe')"
        :error="errors['names.de']"
        maxlength="60"
        autofocus
        required
      />
      <FormInput
        v-model="state.en"
        name="nameEn"
        :label="t('roleSettings.nameEn')"
        :hint="isNew ? t('roleSettings.keyHint') : undefined"
        :error="errors['names.en']"
        maxlength="60"
        required
      />

      <FormField :label="t('roleSettings.rightsOf')">
        <RoleRightGroups
          :model-value="state.rights"
          :rights="choices"
          selectable
          @update:model-value="value => (state.rights = value as Right[])"
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
        form="role-function-form"
        variant="brand"
        :loading="pending"
      >
        {{ t('common.save') }}
      </wa-button>
    </template>
  </AppDialog>
</template>

<style scoped>
.role-function-dialog {
  --width: 48rem;
}
</style>
