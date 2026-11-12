import { describe, expect, it, vi } from 'vitest'
import { useConfirmedDelete } from '../../app/composables/useConfirmedDelete'

describe('useConfirmedDelete', () => {
  it('keeps the target until the user asks to close, without deleting', () => {
    const remove = vi.fn(async () => undefined)
    const deletion = useConfirmedDelete<string>(remove)

    expect(deletion.isOpen).toBe(false)
    deletion.ask('todo-1')
    expect(deletion.isOpen).toBe(true)
    expect(deletion.target).toBe('todo-1')

    deletion.close()
    expect(deletion.isOpen).toBe(false)
    expect(deletion.target).toBeNull()
    expect(remove).not.toHaveBeenCalled()
  })

  it('removes the target on confirm, calls onDeleted and closes', async () => {
    const remove = vi.fn(async (_id: string) => undefined)
    const onDeleted = vi.fn()
    const deletion = useConfirmedDelete<string>(remove, { onDeleted })

    deletion.ask('todo-1')
    await deletion.confirm()

    expect(remove).toHaveBeenCalledWith('todo-1')
    expect(onDeleted).toHaveBeenCalledWith('todo-1')
    expect(deletion.isOpen).toBe(false)
  })

  it('keeps the dialog open and skips onDeleted when the request fails', async () => {
    const onDeleted = vi.fn()
    const deletion = useConfirmedDelete<string>(async () => {
      throw new Error('denied')
    }, { onDeleted })

    deletion.ask('todo-1')
    await expect(deletion.confirm()).rejects.toThrow('denied')

    expect(onDeleted).not.toHaveBeenCalled()
    expect(deletion.isOpen).toBe(true)
    expect(deletion.target).toBe('todo-1')
  })

  it('does nothing on confirm without a target', async () => {
    const remove = vi.fn(async () => undefined)
    const deletion = useConfirmedDelete<string>(remove)

    await deletion.confirm()
    expect(remove).not.toHaveBeenCalled()
  })
})
