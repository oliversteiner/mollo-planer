import { describe, expect, it, vi } from 'vitest'
import { useDelete } from '../../app/composables/useDelete'

describe('useDelete', () => {
  it('removes nothing before the user confirms', async () => {
    const remove = vi.fn(async () => {})
    const deletion = useDelete<string>(remove)

    deletion.ask('milestone-1')

    expect(deletion.isOpen).toBe(true)
    expect(remove).not.toHaveBeenCalled()
  })

  it('removes the asked target on confirm and reports it', async () => {
    const remove = vi.fn(async () => {})
    const onDeleted = vi.fn()
    const deletion = useDelete<string>(remove, { onDeleted })

    deletion.ask('task-7')
    await deletion.confirm()

    expect(remove).toHaveBeenCalledWith('task-7')
    expect(onDeleted).toHaveBeenCalledWith('task-7')
    expect(deletion.isOpen).toBe(false)
  })
})
