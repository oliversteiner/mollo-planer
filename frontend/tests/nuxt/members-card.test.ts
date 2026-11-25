import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { computed, ref } from 'vue'
import MembersCard from '~/components/project/MembersCard.vue'
import { installElementInternals } from './support/element-internals'

const { roles, candidates, setMember } = vi.hoisted(() => ({
  roles: [
    { id: '1', key: 'owner', scope: 'PROJECT', isDefault: false, names: { de: 'Owner', en: 'Owner' }, rights: ['PROJECT_DELETE', 'OWNER_MANAGE'] },
    { id: '2', key: 'member', scope: 'PROJECT', isDefault: true, names: { de: 'Mitglied', en: 'Member' }, rights: [] },
    { id: '3', key: 'agent', scope: 'PROJECT', isDefault: false, names: { de: 'Agent', en: 'Agent' }, rights: [] }
  ],
  candidates: [
    { id: '5', name: 'Ben Müller', avatarUrl: null, isMember: false, isAgent: false },
    { id: '6', name: 'Claude', avatarUrl: null, isMember: false, isAgent: true }
  ],
  setMember: vi.fn()
}))

vi.mock('@vue/apollo-composable', () => ({
  useQuery: (document: { definitions?: { name?: { value: string } }[] }) => {
    const name = document?.definitions?.[0]?.name?.value

    return { result: ref(name === 'Roles' ? { roles } : { memberCandidates: candidates }), loading: ref(false), refetch: vi.fn() }
  },
  useMutation: (document: { definitions?: { name?: { value: string } }[] }) => ({
    mutate: document?.definitions?.[0]?.name?.value === 'SetProjectMember' ? setMember : vi.fn()
  }),
  useApolloClient: () => ({ resolveClient: () => ({ readQuery: () => null, query: async () => ({ data: null }), refetchQueries: async () => [] }) })
}))
mockNuxtImport('useProject', () => () => ({ projectId: computed(() => '7'), project: computed(() => ({ id: '7', viewerRights: ['MEMBERS_MANAGE', 'OWNER_MANAGE'] })), loaded: computed(() => true), can: () => true }))
mockNuxtImport('useViewer', () => () => ({ viewer: ref({ id: '1' }), load: vi.fn(), logout: vi.fn(), can: () => true, pendingEmail: ref(null) }))

installElementInternals()

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

beforeEach(() => {
  setMember.mockReset().mockResolvedValue({ data: {} })
})

const members = [
  { role: { key: 'owner', names: { de: 'Owner', en: 'Owner' } }, user: { id: '1', name: 'Anna', email: 'anna@example.ch', isAgent: false } },
  { role: { key: 'agent', names: { de: 'Agent', en: 'Agent' } }, user: { id: '8', name: 'Kevin', email: 'kevin@agent.invalid', isAgent: true } }
]

async function mountCard() {
  const card = await mountSuspended(MembersCard, { props: { projectId: '7', members, canManage: true } })
  const selects = () => card.findAllComponents({ name: 'FormSelect' })
  const newRole = () => selects().find(select => select.props('label') === 'Projektrolle')!
  const labels = (select: ReturnType<typeof newRole>) => (select.props('items') as { label: string }[]).map(item => item.label)
  const choose = async (userId: string) => {
    card.findComponent({ name: 'FormPersonSelect' }).vm.$emit('update:modelValue', userId)
    await nextTick()
  }

  return { card, selects, newRole, labels, choose }
}

describe('MembersCard: the role Agent (Auftrag 58)', () => {
  it('offers no role Agent for a person', async () => {
    const { newRole, labels, choose } = await mountCard()

    await choose('5')

    expect(labels(newRole())).toEqual(['Owner', 'Mitglied'])
    expect(newRole().props('disabled')).toBe(false)
  })

  it('fixes the role Agent for an agent, says why, and adds it with that role', async () => {
    const { card, newRole, labels, choose } = await mountCard()

    await choose('6')

    expect(labels(newRole())).toEqual(['Agent'])
    expect(newRole().props('disabled')).toBe(true)
    expect(newRole().props('hint')).toBe('Agenten haben immer die Rolle Agent.')

    await card.find('form').trigger('submit')
    expect(setMember).toHaveBeenCalledWith({ projectId: '7', userId: '6', role: 'agent' })
  })

  it('goes back to the default role when a person is chosen after an agent', async () => {
    const { newRole, choose } = await mountCard()

    await choose('6')
    await choose('5')

    expect(newRole().props('modelValue')).toBe('member')
  })

  it('shows the role of an agent member fixed and its identifier with a button to copy it', async () => {
    const { card, selects } = await mountCard()
    const agentRow = selects().find(select => select.props('label') === 'Projektrolle von Kevin')!
    const personRow = selects().find(select => select.props('label') === 'Projektrolle von Anna')!

    expect(agentRow.props('disabled')).toBe(true)
    expect((personRow.props('items') as { value: string }[]).map(item => item.value)).not.toContain('agent')
    expect(card.text()).toContain('Kennung: kevin@agent.invalid')
    expect(card.find('wa-button[aria-label="Kennung von Kevin kopieren"]').exists()).toBe(true)
  })
})
