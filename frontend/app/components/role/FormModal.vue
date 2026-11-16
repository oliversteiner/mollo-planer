<script setup lang="ts">
import { useMutation } from '@vue/apollo-composable'
import type { Right, RoleScope } from '~/graphql/generated/graphql'
import { createRoleMutation, updateRoleMutation } from '~/graphql/roles'

/**
 * Create a role of one's own or change a role: names in both languages,
 * the functions of its scope and, for system roles, the cap for project
 * rights. The scope is chosen once; the key is made by the backend.
 */
export interface RoleFormFunction {
  id: string
  scope: RoleScope
  names: { de: string, en: string }
  rights: readonly Right[]
}

export interface RoleFormTarget {
  role?: {
    id: string
    scope: RoleScope
    names: { de: string, en: string }
    functions: readonly { id: string }[]
    projectCap?: readonly Right[] | null
  }
  /** Scope of a new role (the tab it was started from). */
  scope?: RoleScope
}

type CapMode = 'ALL' | 'NONE' | 'SOME'

const props = defineProps<{
  target: RoleFormTarget | null
  functions: readonly RoleFormFunction[]
  projectRights: readonly Right[]
}>()
const emit = defineEmits<{ close: [] }>()

const { t } = useI18n()
const toast = useToast()
const roleName = useRoleName()
const { errors, pending, run } = useGraphqlForm()

const state = reactive<{ scope: RoleScope, de: string, en: string, functionIds: string[], capMode: CapMode, capRights: Right[] }>({
  scope: 'PROJECT',
  de: '',
  en: '',
  functionIds: [],
  capMode: 'ALL',
  capRights: []
})

const open = computed({
  get: () => props.target !== null,
  set: (value: boolean) => {
    if (!value) {
      emit('close')
    }
  }
})

const isNew = computed(() => !props.target?.role)

watch(() => props.target, (target) => {
  const role = target?.role
  const cap = role?.projectCap

  errors.value = {}
  state.scope = role?.scope ?? target?.scope ?? 'PROJECT'
  state.de = role?.names.de ?? ''
  state.en = role?.names.en ?? ''
  state.functionIds = (role?.functions ?? []).map(entry => entry.id)
  state.capMode = cap == null ? 'ALL' : cap.length === 0 ? 'NONE' : 'SOME'
  state.capRights = [...(cap ?? [])]
}, { immediate: true })

const scopeItems = computed(() => (['SYSTEM', 'PROJECT'] as const).map(scope => ({ label: t(`roleSettings.scopes.${scope}`), value: scope })))
const capItems = computed(() => (['ALL', 'NONE', 'SOME'] as const).map(mode => ({ label: t(`roleSettings.capModes.${mode}`), value: mode })))

/** Only functions of the role's scope can be chosen. */
const choices = computed(() => props.functions.filter(entry => entry.scope === state.scope))

function setScope(scope: string | null): void {
  state.scope = scope === 'SYSTEM' ? 'SYSTEM' : 'PROJECT'
  state.functionIds = []
}

function toggle<T>(list: T[], value: T, on: boolean): void {
  const index = list.indexOf(value)

  if (on && index < 0) {
    list.push(value)
  } else if (!on && index >= 0) {
    list.splice(index, 1)
  }
}

function rightNames(rights: readonly Right[]): string {
  return rights.length ? rights.map(right => t(`rights.names.${right}`)).join(', ') : t('roleSettings.noRights')
}

/** A refusal that belongs to no single field: a ground rule, or the role itself. */
const refusal = computed(() => errors.value.rights ?? errors.value.id)

const { mutate: createRole } = useMutation(createRoleMutation, roleSettingsRefresh)
const { mutate: updateRole } = useMutation(updateRoleMutation, roleSettingsRefresh)

async function save(): Promise<void> {
  const names = { de: state.de.trim(), en: state.en.trim() }
  const functionIds = choices.value.map(entry => entry.id).filter(id => state.functionIds.includes(id))
  const cap = state.capMode === 'ALL' ? null : state.capMode === 'NONE' ? [] : props.projectRights.filter(right => state.capRights.includes(right))
  const withCap = state.scope === 'SYSTEM' ? { projectCap: cap } : {}
  const role = props.target?.role

  const result = await run<unknown>(() => (role
    ? updateRole({ id: role.id, input: { names, functionIds, ...withCap } })
    : createRole({ input: { scope: state.scope, names, functionIds, ...withCap } })))

  if (result) {
    toast.add({ title: t('roleSettings.saved'), color: 'success' })
    emit('close')
  }
}
</script>

<template>
  <AppDialog
    v-model:open="open"
    :label="isNew ? t('roleSettings.createRole') : t('roleSettings.editRole')"
    class="role-dialog"
  >
    <form
      id="role-form"
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

      <FormField
        :label="t('roleSettings.functionsOf')"
        :error="errors.functionIds"
      >
        <ul class="role-choices wa-list-plain">
          <li
            v-for="entry in choices"
            :key="entry.id"
          >
            <FormCheckbox
              :model-value="state.functionIds.includes(entry.id)"
              :label="roleName({ key: entry.id, names: entry.names })"
              :hint="rightNames(entry.rights)"
              @update:model-value="toggle(state.functionIds, entry.id, $event)"
            />
          </li>
        </ul>
      </FormField>

      <template v-if="state.scope === 'SYSTEM'">
        <FormSelect
          v-model="state.capMode"
          :items="capItems"
          :label="t('roleSettings.cap')"
          :hint="t('roleSettings.capHint')"
          :error="errors.projectCap"
        />
        <FormField
          v-if="state.capMode === 'SOME'"
          :label="t('roleSettings.capRights')"
        >
          <RoleRightGroups
            :model-value="state.capRights"
            :rights="projectRights"
            selectable
            @update:model-value="value => (state.capRights = value as Right[])"
          />
        </FormField>
      </template>
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
        form="role-form"
        variant="brand"
        :loading="pending"
      >
        {{ t('common.save') }}
      </wa-button>
    </template>
  </AppDialog>
</template>

<style scoped>
.role-dialog {
  --width: 48rem;
}

.role-choices {
  display: grid;
  gap: var(--wa-space-s);
  inline-size: 100%;
  margin: 0;
  padding: 0;
}
</style>
