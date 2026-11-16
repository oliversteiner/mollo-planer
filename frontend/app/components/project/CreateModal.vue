<script setup lang="ts">
import { useMutation, useQuery } from '@vue/apollo-composable'
import type { Locale } from '~/graphql/generated/graphql'
import { appSettingsQuery } from '~/graphql/admin'
import { createProjectMutation } from '~/graphql/projects'
import { memberCandidatesQuery } from '~/graphql/settings'

/**
 * New project (for people with projects.create; the backend checks it again),
 * optionally with its members right away (docs/fix-m1-mitglieder.md §2.2).
 */
const open = defineModel<boolean>('open', { default: false })

const { t } = useI18n()
const toast = useToast()
const { errors, pending, run } = useGraphqlForm()

const state = reactive<{ name: string, description: string, icon: string | null, locale: Locale }>({ name: '', description: '', icon: null, locale: 'DE' })

interface NewMember {
  userId: string
  name: string
  avatarUrl?: string | null
  role: string
}

const members = ref<NewMember[]>([])
const person = ref<string | null>(null)
const personRole = ref('')

const { viewer, can } = useViewer()
const roleName = useRoleName()
const { items: roleItems, defaultKey: defaultRole } = useRoles('PROJECT')
const { result: candidatesResult } = useQuery(memberCandidatesQuery, () => ({ projectId: null }), () => ({ enabled: open.value }))
const { result: settingsResult } = useQuery(appSettingsQuery, null, () => ({ enabled: open.value }))

/** The person who creates the project becomes a member with the creator role, unless they may manage every project. */
const creatorRole = computed(() => (can('PROJECTS_MANAGE_ANY') ? null : settingsResult.value?.appSettings.projectCreatorRole ?? null))

const personGroups = computed(() => {
  const taken = new Set([...members.value.map(member => member.userId), ...(creatorRole.value && viewer.value ? [viewer.value.id] : [])])

  return [{
    people: (candidatesResult.value?.memberCandidates ?? []).map(candidate => ({
      id: candidate.id,
      name: candidate.name,
      avatarUrl: candidate.avatarUrl,
      disabledReason: taken.has(candidate.id) ? t('projects.create.inList') : null
    }))
  }]
})

// The roles arrive with their query; preset the default role once it is known.
watch(defaultRole, (key) => {
  if (!personRole.value) {
    personRole.value = key
  }
}, { immediate: true })

function addMember(): void {
  const candidate = candidatesResult.value?.memberCandidates.find(entry => entry.id === person.value)

  if (candidate) {
    members.value = [...members.value, { userId: candidate.id, name: candidate.name, avatarUrl: candidate.avatarUrl, role: personRole.value || defaultRole.value }]
    person.value = null
  }
}

function removeMember(userId: string): void {
  members.value = members.value.filter(member => member.userId !== userId)
}

/** A message of the server for one row of the list (input.members.N.…). */
function memberError(index: number): string | undefined {
  return errors.value[`members.${index}.role`] ?? errors.value[`members.${index}.userId`]
}
const locales = computed(() => [
  { label: t('locale.DE'), value: 'DE' },
  { label: t('locale.EN'), value: 'EN' }
])

// 'active' includes the project choice in the sidebar.
const { mutate } = useMutation(createProjectMutation, { refetchQueries: 'active', awaitRefetchQueries: true })

async function submit(): Promise<void> {
  const result = await run(() => mutate({
    input: {
      name: state.name.trim(),
      description: state.description.trim() || null,
      icon: state.icon,
      locale: state.locale,
      members: members.value.map(member => ({ userId: member.userId, role: member.role }))
    }
  }))
  const project = result?.data?.createProject

  if (project) {
    open.value = false
    toast.add({ title: t('projects.create.done', { name: project.name }), color: 'success' })
    await navigateTo(`/projects/${project.id}`)
  }
}

watch(open, (isOpen) => {
  if (isOpen) {
    Object.assign(state, { name: '', description: '', icon: null, locale: 'DE' })
    members.value = []
    person.value = null
    personRole.value = defaultRole.value
    errors.value = {}
  }
})
</script>

<template>
  <AppDialog
    v-model:open="open"
    :label="t('projects.create.title')"
  >
    <form
      id="create-project"
      class="wa-stack wa-gap-m"
      @submit.prevent="submit"
    >
      <FormInput
        v-model="state.name"
        name="name"
        :label="t('projects.fields.name')"
        :error="errors.name"
        autofocus
        required
      />

      <FormTextarea
        v-model="state.description"
        name="description"
        :label="t('projects.fields.description')"
        :error="errors.description"
        :rows="3"
      />

      <FormField
        :label="t('item.fields.icon')"
        :hint="t('projects.fields.iconHint')"
        :error="errors.icon"
      >
        <IconPicker
          v-model="state.icon"
          fallback="folder"
        />
      </FormField>

      <FormSelect
        v-model="state.locale"
        name="locale"
        :items="locales"
        :label="t('projects.fields.locale')"
        :hint="t('projects.fields.localeHint')"
        :error="errors.locale"
      />

      <FormField
        :label="t('projects.create.members')"
        :hint="t('projects.create.membersHint')"
      >
        <div class="wa-stack wa-gap-s">
          <ul
            v-if="creatorRole || members.length"
            class="create-members wa-list-plain"
          >
            <li
              v-if="creatorRole && viewer"
              class="create-member"
            >
              <UserAvatar
                :name="viewer.name"
                :src="viewer.avatarUrl"
                size="xs"
              />
              <span class="create-member-name">{{ viewer.name }} <span class="create-member-quiet">({{ t('settings.members.you') }})</span></span>
              <wa-badge
                variant="neutral"
                appearance="outlined"
              >
                {{ roleName(creatorRole) }}
              </wa-badge>
            </li>
            <li
              v-for="(member, index) in members"
              :key="member.userId"
              class="create-member"
            >
              <UserAvatar
                :name="member.name"
                :src="member.avatarUrl"
                size="xs"
              />
              <span class="create-member-name">{{ member.name }}</span>
              <FormSelect
                v-model="member.role"
                :items="roleItems"
                :label="t('settings.members.roleOf', { name: member.name })"
                :error="memberError(index)"
                hide-label
                class="create-member-role"
              />
              <wa-button
                appearance="plain"
                variant="neutral"
                size="s"
                :aria-label="t('settings.members.remove', { name: member.name })"
                @click="removeMember(member.userId)"
              >
                <wa-icon name="user-minus"></wa-icon>
              </wa-button>
            </li>
          </ul>

          <div class="create-members-add">
            <FormPersonSelect
              v-model="person"
              :groups="personGroups"
              :label="t('settings.members.person')"
              class="create-members-person"
            />
            <FormSelect
              v-model="personRole"
              :items="roleItems"
              :label="t('settings.members.role')"
              class="create-member-role"
            />
            <wa-button
              appearance="outlined"
              variant="neutral"
              :disabled="!person"
              @click="addMember"
            >
              <wa-icon
                slot="start"
                name="user-plus"
              ></wa-icon>
              {{ t('settings.members.add') }}
            </wa-button>
          </div>
        </div>
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
        form="create-project"
        variant="brand"
        :loading="pending"
      >
        {{ t('projects.create.submit') }}
      </wa-button>
    </template>
  </AppDialog>
</template>

<style scoped>
.create-members {
  margin: 0;
  padding: 0;
  border: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  border-radius: var(--wa-border-radius-m);
}

.create-member {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-s);
  padding: var(--wa-space-xs) var(--wa-space-s);
}

.create-member + .create-member {
  border-block-start: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
}

.create-member-name {
  flex: 1;
  min-inline-size: 0;
}

.create-member-quiet {
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}

.create-member-role {
  inline-size: 12rem;
}

.create-members-add {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: var(--wa-space-s);
}

.create-members-person {
  flex: 1 1 14rem;
}
</style>
