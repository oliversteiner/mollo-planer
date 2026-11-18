import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import { nextTick } from 'vue'
import PasswordPromptModal from '~/components/PasswordPromptModal.vue'

describe('PasswordPromptModal', () => {
  it('has no password field while closed, so Chrome does not fill a search field as user name', async () => {
    const modal = await mountSuspended(PasswordPromptModal)
    const prompt = usePasswordPrompt()
    /** The dialog has one field, the password. */
    const passwordField = () => modal.find('wa-input')

    expect(passwordField().exists()).toBe(false)

    void prompt.ask()
    await nextTick()
    expect((passwordField().element as unknown as { type: string }).type).toBe('password')

    prompt.answer(null)
    await nextTick()
    modal.find('wa-dialog').element.dispatchEvent(new Event('wa-after-hide'))
    await nextTick()
    expect(passwordField().exists()).toBe(false)
  })
})
