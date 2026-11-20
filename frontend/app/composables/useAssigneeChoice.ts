import { useQuery } from '@vue/apollo-composable'
import type { PersonGroup } from '~/components/form/PersonSelect.vue'
import { memberCandidatesQuery } from '~/graphql/settings'

interface Member {
  id: string
  name: string
  avatarUrl?: string | null
  isAgent?: boolean
}

/**
 * The choice "assignee" of a task (docs/fix-m1-mitglieder.md §2.3): the
 * members first and, for people who may manage the members, everybody else
 * below; choosing one of them takes the person into the project. Who may
 * only assign themselves (task.assignSelf without task.assign) sees only
 * themselves (docs/fix-m1-abnahme.md F3.9).
 */
export function useAssigneeChoice(members: () => readonly Member[], mayAssignOthers?: () => boolean) {
  const { t } = useI18n()
  const toast = useToast()
  const { projectId, can } = useProject()
  const { viewer } = useViewer()
  const roleName = useRoleName()
  const { roles } = useRoles('PROJECT')

  const canBringIn = computed(() => can('MEMBERS_MANAGE'))
  const { result } = useQuery(memberCandidatesQuery, () => ({ projectId: projectId.value }), () => ({ enabled: canBringIn.value }))

  /** An existing task answers itself (Task.viewerCanAssign); a new one asks the project. */
  const onlySelf = computed(() => !(mayAssignOthers ? mayAssignOthers() : can('TASK_ASSIGN')))

  const groups = computed<PersonGroup[]>(() => {
    const people = onlySelf.value
      ? members().filter(member => member.id === viewer.value?.id)
      : members()
    const own: PersonGroup = { label: t('task.assigneeGroups.members'), people: people.map(member => ({ ...member })) }

    if (onlySelf.value) {
      return [own]
    }

    if (!canBringIn.value) {
      return [own]
    }

    const memberIds = new Set(members().map(member => member.id))
    const others = (result.value?.memberCandidates ?? [])
      .filter(person => !person.isMember && !memberIds.has(person.id))
      .map(person => ({ id: person.id, name: person.name, avatarUrl: person.avatarUrl, isAgent: person.isAgent }))

    return [own, { label: t('task.assigneeGroups.others'), people: others }]
  })

  const hint = computed(() => {
    if (onlySelf.value) {
      return t('task.onlySelf')
    }

    return canBringIn.value ? undefined : t('task.onlyMembers')
  })

  /** Asked before saving: the members are loaded again afterwards. */
  function willJoin(userId: string | null): boolean {
    return userId !== null && !members().some(member => member.id === userId)
  }

  /** After saving: "Sven is a member now (role Member)." */
  function announceJoined(userId: string): void {
    const person = result.value?.memberCandidates.find(candidate => candidate.id === userId)
    const role = roles.value.find(entry => entry.isDefault)

    if (person) {
      toast.add({ title: t('task.joined', { name: person.name, role: role ? roleName(role) : '' }), color: 'info' })
    }
  }

  return { groups, hint, willJoin, announceJoined }
}
