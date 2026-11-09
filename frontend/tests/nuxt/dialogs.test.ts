import { mountSuspended } from '@nuxt/test-utils/runtime'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import AppDialog from '~/components/AppDialog.vue'
import ConfirmModal from '~/components/ConfirmModal.vue'
import PasswordPromptModal from '~/components/PasswordPromptModal.vue'

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

const buttons = (wrapper: { findAll: (selector: string) => Array<{ text: () => string, trigger: (event: string) => Promise<void> }> }) => wrapper.findAll('wa-button')
const byText = (wrapper: Parameters<typeof buttons>[0], text: string) => buttons(wrapper).find(button => button.text() === text)!

describe('AppDialog', () => {
  it('shows title, description, content and footer in a wa-dialog', async () => {
    const dialog = await mountSuspended(AppDialog, {
      props: { open: true, label: 'Titel', description: 'Erklärung' },
      slots: { default: '<p class="body">Inhalt</p>', footer: '<button class="foot">OK</button>' }
    })
    const element = dialog.element as unknown as { open: boolean, label: string }

    expect(dialog.element.tagName).toBe('WA-DIALOG')
    expect(element.open).toBe(true)
    expect(element.label).toBe('Titel')
    expect(dialog.text()).toContain('Erklärung')
    expect(dialog.find('.body').exists()).toBe(true)
    expect(dialog.find('[slot="footer"] .foot').exists()).toBe(true)
  })

  it('reports closing by the dialog itself, not events of things inside it', async () => {
    const dialog = await mountSuspended(AppDialog, { props: { open: true, label: 'Titel' }, slots: { default: '<span class="inner"></span>' } })

    dialog.find('.inner').element.dispatchEvent(new Event('wa-hide', { bubbles: true }))

    expect(dialog.emitted('update:open')).toBeUndefined()

    dialog.element.dispatchEvent(new Event('wa-hide'))

    expect(dialog.emitted('update:open')).toEqual([[false]])
  })
})

describe('ConfirmModal', () => {
  const props = { open: true, title: 'Task löschen?', description: 'Das lässt sich nicht rückgängig machen.', confirmLabel: 'Löschen' }

  it('runs the action on confirm and closes afterwards', async () => {
    const action = vi.fn().mockResolvedValue(undefined)
    const modal = await mountSuspended(ConfirmModal, { props: { ...props, action } })

    expect((modal.element as unknown as { label: string }).label).toBe('Task löschen?')
    expect(modal.text()).toContain('Das lässt sich nicht rückgängig machen.')

    await byText(modal, 'Löschen').trigger('click')
    await vi.waitFor(() => expect(modal.emitted('close')).toHaveLength(1))

    expect(action).toHaveBeenCalledOnce()
  })

  it('stays open when the action fails', async () => {
    const action = vi.fn().mockRejectedValue(new Error('x'))
    const modal = await mountSuspended(ConfirmModal, { props: { ...props, action } })

    await byText(modal, 'Löschen').trigger('click')
    await vi.waitFor(() => expect(action).toHaveBeenCalled())
    await new Promise(resolve => setTimeout(resolve))

    expect(modal.emitted('close')).toBeUndefined()
  })

  it('closes on cancel without running the action', async () => {
    const action = vi.fn()
    const modal = await mountSuspended(ConfirmModal, { props: { ...props, action } })

    await byText(modal, 'Abbrechen').trigger('click')

    expect(modal.emitted('close')).toHaveLength(1)
    expect(action).not.toHaveBeenCalled()
  })
})

describe('PasswordPromptModal', () => {
  it('hands the typed password to the one who asked, and null on cancel', async () => {
    const { ask } = usePasswordPrompt()
    const modal = await mountSuspended(PasswordPromptModal)

    const answer = ask('Falsches Passwort.')
    await nextTick()

    expect(modal.text()).toContain('Falsches Passwort.')

    const field = modal.find('wa-input').element as unknown as { value: string }
    field.value = 'geheim'
    modal.find('wa-input').element.dispatchEvent(new Event('input'))
    await nextTick()
    await byText(modal, 'Bestätigen').trigger('click')

    await expect(answer).resolves.toBe('geheim')

    const cancelled = ask()
    await nextTick()
    await byText(modal, 'Abbrechen').trigger('click')

    await expect(cancelled).resolves.toBeNull()
  })
})
