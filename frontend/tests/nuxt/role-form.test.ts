import { mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { RoleFormTarget } from '~/components/role/FormModal.vue'
import RoleFormModal from '~/components/role/FormModal.vue'
import type { Right } from '~/graphql/generated/graphql'

const { createRole, updateRole } = vi.hoisted(() => ({ createRole: vi.fn(), updateRole: vi.fn() }))

vi.mock('@vue/apollo-composable', () => ({
  useMutation: (document: { definitions: { name?: { value: string } }[] }) => ({
    mutate: document.definitions[0]?.name?.value === 'CreateRole' ? createRole : updateRole
  })
}))

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

// happy-dom has no form internals; the submit button (form="…") of Web Awesome reads them.
const prototype = HTMLElement.prototype as unknown as { attachInternals?: () => unknown }
const original = prototype.attachInternals

beforeEach(() => {
  prototype.attachInternals = function () {
    return original?.call(this) ?? { form: null, states: new Set(), setFormValue() {}, setValidity() {}, checkValidity: () => true, reportValidity: () => true, validity: { valid: true }, validationMessage: '', labels: [] }
  }
  createRole.mockReset().mockResolvedValue({ data: {} })
  updateRole.mockReset().mockResolvedValue({ data: {} })
})

afterEach(() => {
  prototype.attachInternals = original
})

const functions = [
  { id: '1', scope: 'SYSTEM' as const, names: { de: 'Status', en: 'Status' }, rights: ['SYSTEM_VIEW'] as Right[] },
  { id: '2', scope: 'SYSTEM' as const, names: { de: 'Projekte anlegen', en: 'Create projects' }, rights: ['PROJECTS_CREATE'] as Right[] },
  { id: '10', scope: 'PROJECT' as const, names: { de: 'Planen', en: 'Plan' }, rights: ['TODO_CREATE'] as Right[] },
  { id: '11', scope: 'PROJECT' as const, names: { de: 'Mitarbeiten', en: 'Contribute' }, rights: ['TASK_CREATE'] as Right[] }
]
const projectRights = ['PROJECT_UPDATE', 'TASK_MOVE', 'COMMENT_CREATE'] as Right[]

function systemRole(projectCap: Right[] | null) {
  return { id: '5', scope: 'SYSTEM' as const, names: { de: 'Lesend', en: 'Read only' }, functions: [{ id: '1' }], projectCap }
}

async function submit(target: RoleFormTarget) {
  const form = await mountSuspended(RoleFormModal, { props: { target, functions, projectRights } })

  await form.find('form').trigger('submit')
  await flushPromises()

  return form
}

describe('RoleFormModal', () => {
  it('offers only the functions of the scope of the role', async () => {
    const form = await mountSuspended(RoleFormModal, { props: { target: { role: systemRole(null) }, functions, projectRights } })

    expect(form.findAll('wa-checkbox').map(box => box.text())).toEqual(['Status', 'Projekte anlegen'])
  })

  it('keeps a cap that lets nothing through as an empty list, and an open one as null', async () => {
    await submit({ role: systemRole([]) })
    expect(updateRole).toHaveBeenLastCalledWith({ id: '5', input: { names: { de: 'Lesend', en: 'Read only' }, functionIds: ['1'], projectCap: [] } })

    await submit({ role: systemRole(null) })
    expect(updateRole).toHaveBeenLastCalledWith({ id: '5', input: { names: { de: 'Lesend', en: 'Read only' }, functionIds: ['1'], projectCap: null } })
  })

  it('sends the chosen rights of a limited cap in the order of the list', async () => {
    const form = await submit({ role: systemRole(['COMMENT_CREATE', 'PROJECT_UPDATE']) })

    expect(updateRole).toHaveBeenLastCalledWith(expect.objectContaining({ input: expect.objectContaining({ projectCap: ['PROJECT_UPDATE', 'COMMENT_CREATE'] }) }))
    expect(form.findAll('wa-checkbox')).toHaveLength(2 + projectRights.length)
  })

  it('sends no cap for a project role', async () => {
    await submit({ role: { id: '9', scope: 'PROJECT', names: { de: 'Gast', en: 'Guest' }, functions: [{ id: '11' }, { id: '10' }], projectCap: null } })

    expect(updateRole).toHaveBeenLastCalledWith({ id: '9', input: { names: { de: 'Gast', en: 'Guest' }, functionIds: ['10', '11'] } })
  })

  it('creates a project role when nothing else is chosen', async () => {
    await submit({})

    expect(createRole).toHaveBeenCalledWith({ input: { scope: 'PROJECT', names: { de: '', en: '' }, functionIds: [] } })
  })
})
