import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import FormCheckbox from '~/components/form/Checkbox.vue'
import FormInput from '~/components/form/Input.vue'
import FormSelect from '~/components/form/Select.vue'
import FormSwitch from '~/components/form/Switch.vue'
import FormTextarea from '~/components/form/Textarea.vue'

/** A wa-* element as Vue sets it: properties, not attributes. */
type Field = HTMLElement & { value: string, label: string, hint: string, checked: boolean, required: boolean, type: string, name: string }

const field = (wrapper: { element: Element }) => wrapper.element as Field

describe('FormInput', () => {
  it('shows value and label and passes other attributes to the wa-input', async () => {
    const input = await mountSuspended(FormInput, {
      props: { modelValue: 'ada@example.org', label: 'E-Mail', hint: 'Dienstadresse' },
      attrs: { type: 'email', name: 'email', required: true }
    })

    expect(input.element.tagName).toBe('WA-INPUT')
    expect(field(input).value).toBe('ada@example.org')
    expect(field(input).label).toBe('E-Mail')
    expect(field(input).hint).toBe('Dienstadresse')
    expect(field(input).type).toBe('email')
    expect(field(input).name).toBe('email')
    expect(field(input).required).toBe(true)
  })

  it('writes what the user types back with v-model', async () => {
    const input = await mountSuspended(FormInput, { props: { modelValue: '' } })

    field(input).value = 'Hallo'
    input.element.dispatchEvent(new Event('input'))

    expect(input.emitted('update:modelValue')).toEqual([['Hallo']])
  })

  it('shows the message from the server instead of the hint and marks the field invalid', async () => {
    const input = await mountSuspended(FormInput, { props: { modelValue: '', hint: 'Dienstadresse', error: 'Die E-Mail ist ungültig.' } })

    expect(input.find('[slot="hint"][data-form-error]').text()).toBe('Die E-Mail ist ungültig.')
    expect(field(input).hint).toBeFalsy()
    expect(input.attributes('aria-invalid')).toBe('true')
    expect(input.attributes('data-invalid')).toBe('')

    await input.setProps({ error: undefined })

    expect(input.find('[data-form-error]').exists()).toBe(false)
    expect(input.attributes('aria-invalid')).toBeUndefined()
    expect(field(input).hint).toBe('Dienstadresse')
  })

  it('shows the message as text, never as HTML', async () => {
    const input = await mountSuspended(FormInput, { props: { modelValue: '', error: '<img src=x onerror=alert(1)>' } })

    expect(input.find('img').exists()).toBe(false)
    expect(input.find('[data-form-error]').text()).toBe('<img src=x onerror=alert(1)>')
  })
})

describe('FormTextarea', () => {
  it('writes what the user types back with v-model and shows a message', async () => {
    const area = await mountSuspended(FormTextarea, { props: { modelValue: 'a', error: 'Zu lang.' } })

    field(area).value = 'ab'
    area.element.dispatchEvent(new Event('input'))

    expect(area.element.tagName).toBe('WA-TEXTAREA')
    expect(area.emitted('update:modelValue')).toEqual([['ab']])
    expect(area.find('[data-form-error]').text()).toBe('Zu lang.')
  })
})

describe('FormSelect', () => {
  const items = [{ label: 'Admin', value: 'ADMIN' }, { label: 'Mitglied', value: 'MEMBER', disabled: true }]

  it('lists the items as options and selects the value', async () => {
    const select = await mountSuspended(FormSelect, { props: { modelValue: 'ADMIN', items, label: 'Rolle' } })
    const options = select.findAll('wa-option')

    expect(select.element.tagName).toBe('WA-SELECT')
    expect(options.map(option => option.text())).toEqual(['Admin', 'Mitglied'])
    expect((options[1]!.element as unknown as { disabled: boolean }).disabled).toBe(true)
    expect(field(select).value).toBe('ADMIN')
  })

  it('writes the chosen value back with v-model', async () => {
    const select = await mountSuspended(FormSelect, { props: { modelValue: 'ADMIN', items: items.map(item => ({ ...item, disabled: false })) } })

    field(select).value = 'MEMBER'
    select.element.dispatchEvent(new Event('change'))

    expect(select.emitted('update:modelValue')).toEqual([['MEMBER']])
  })
})

describe.each([
  ['FormCheckbox', FormCheckbox, 'WA-CHECKBOX'],
  ['FormSwitch', FormSwitch, 'WA-SWITCH']
] as const)('%s', (_name, component, tag) => {
  it('shows the label and the state and writes a change back with v-model', async () => {
    const box = await mountSuspended(component, { props: { modelValue: false, label: 'Mails senden', hint: 'Nur bei Zuweisung' } })

    expect(box.element.tagName).toBe(tag)
    expect(box.text()).toBe('Mails senden')
    expect(field(box).hint).toBe('Nur bei Zuweisung')
    expect(field(box).checked).toBe(false)

    field(box).checked = true
    box.element.dispatchEvent(new Event('change'))

    expect(box.emitted('update:modelValue')).toEqual([[true]])
  })

  it('takes the label from the slot as well', async () => {
    const box = await mountSuspended(component, { props: { modelValue: true }, slots: { default: 'Eigener <b>Text</b>' } })

    expect(box.find('b').text()).toBe('Text')
    expect(field(box).checked).toBe(true)
  })
})

describe('hidden label', () => {
  it('keeps the label for screen readers and hides it from view', async () => {
    const input = await mountSuspended(FormInput, { props: { modelValue: '', label: 'Suchen', hideLabel: true } })
    const select = await mountSuspended(FormSelect, { props: { modelValue: null, items: [], label: 'Status', hideLabel: true } })

    expect(field(input).label).toBe('Suchen')
    expect(input.classes()).toContain('wa-visually-hidden-label')
    expect(field(select).label).toBe('Status')
    expect(select.classes()).toContain('wa-visually-hidden-label')
  })
})
