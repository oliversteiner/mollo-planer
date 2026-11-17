import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import UserLine from '~/components/UserLine.vue'

describe('UserLine', () => {
  it('shows name, e-mail and role as three lines (sidebar)', async () => {
    const line = await mountSuspended(UserLine, { props: { name: 'Anna Keller', description: 'anna@example.ch', detail: 'Admin' } })

    expect(line.find('.user-line-name').text()).toBe('Anna Keller')
    expect(line.findAll('.user-line-description').map(part => part.text())).toEqual(['anna@example.ch', 'Admin'])
  })

  it('leaves out the lines it was not given', async () => {
    const line = await mountSuspended(UserLine, { props: { name: 'Anna Keller' } })

    expect(line.findAll('.user-line-description')).toHaveLength(0)
  })
})
