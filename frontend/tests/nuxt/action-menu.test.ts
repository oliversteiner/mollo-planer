import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it, vi } from 'vitest'
import ActionMenu from '~/components/ActionMenu.vue'
import FormField from '~/components/form/Field.vue'

function choose(menu: { element: Element }, value: string) {
  menu.element.dispatchEvent(new CustomEvent('wa-select', { detail: { item: { value } } }))
}

describe('ActionMenu', () => {
  it('has a named button and one menu item per entry', async () => {
    const menu = await mountSuspended(ActionMenu, {
      props: { label: 'Aktionen für Milestone «A»', items: [{ label: 'Ändern', icon: 'pen' }, { label: 'Löschen', icon: 'trash', color: 'error' }] }
    })

    expect(menu.find('wa-button').attributes('aria-label')).toBe('Aktionen für Milestone «A»')
    expect(menu.findAll('wa-dropdown-item').map(item => item.text())).toEqual(['Ändern', 'Löschen'])
    expect(menu.findAll('wa-dropdown-item').map(item => (item.element as unknown as { variant: string }).variant)).toEqual(['default', 'danger'])
  })

  it('runs the action of the chosen entry', async () => {
    const edit = vi.fn()
    const remove = vi.fn()
    const menu = await mountSuspended(ActionMenu, {
      props: { label: 'Aktionen', items: [{ label: 'Ändern', onSelect: edit }, { label: 'Löschen', onSelect: remove }] }
    })

    choose(menu, '1')

    expect(remove).toHaveBeenCalledOnce()
    expect(edit).not.toHaveBeenCalled()
  })

  it('numbers across groups and separates them with a line', async () => {
    const first = vi.fn()
    const second = vi.fn()
    const menu = await mountSuspended(ActionMenu, {
      props: { label: 'Aktionen', items: [[{ label: 'Ändern', onSelect: first }], [{ label: 'Löschen', onSelect: second }], []] }
    })

    expect(menu.findAll('wa-divider')).toHaveLength(1)

    choose(menu, '1')

    expect(second).toHaveBeenCalledOnce()
    expect(first).not.toHaveBeenCalled()
  })
})

describe('FormField', () => {
  it('names its content by the label and shows the message instead of the hint', async () => {
    const field = await mountSuspended(FormField, { props: { label: 'Icon', hint: 'Optional', error: 'Unbekannt.' }, slots: { default: '<button class="inner">x</button>' } })
    const labelId = field.find('[role="group"]').attributes('aria-labelledby')!

    expect(field.find(`#${CSS.escape(labelId)}`).text()).toBe('Icon')
    expect(field.find('.inner').exists()).toBe(true)
    expect(field.find('[data-form-error]').text()).toBe('Unbekannt.')
    expect(field.text()).not.toContain('Optional')

    await field.setProps({ error: undefined })

    expect(field.text()).toContain('Optional')
  })
})
