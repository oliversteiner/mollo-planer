import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { UNDO_DURATION, useDelete } from '~/composables/useDelete'
import { installElementInternals } from './support/element-internals'
import { standInDrawer } from './support/overlay'

installElementInternals()

const { restore } = vi.hoisted(() => ({ restore: vi.fn(async () => true) }))

mockNuxtImport('useTrashRestore', () => () => restore)

afterEach(() => {
  document.querySelectorAll('wa-toast, wa-drawer').forEach(element => element.remove())
  restore.mockClear()
})

async function setup(remove: (target: string) => Promise<unknown>, onDeleted?: (target: string) => void) {
  let deletion!: ReturnType<typeof useDelete<string>>
  await mountSuspended(defineComponent({
    setup() {
      deletion = useDelete<string>(remove, { onDeleted })

      return () => h('div')
    }
  }))

  return deletion
}

describe('useDelete', () => {
  it('deletes at once, without asking, and offers to undo for about 8 seconds', async () => {
    const remove = vi.fn(async () => ({ data: { deleteTask: { id: '7', deletionId: 'd-7' } } }))
    const onDeleted = vi.fn()
    const deletion = await setup(remove, onDeleted)

    await deletion.remove('7')

    expect(remove).toHaveBeenCalledWith('7')
    expect(onDeleted).toHaveBeenCalledWith('7')
    const item = document.querySelector('wa-toast-item')!
    expect(item.getAttribute('duration')).toBe(String(UNDO_DURATION))
    // The tests run in English: „Gelöscht“ / „Rückgängig“ in German.
    expect(item.textContent).toContain('Deleted')
    expect(item.querySelector('.toast-action')!.textContent).toBe('Undo')
  })

  it('undo restores exactly this deletion', async () => {
    const deletion = await setup(async () => ({ data: { deleteComment: { id: '3', deletionId: 'd-3' } } }))

    await deletion.remove('3')
    const item = document.querySelector('wa-toast-item') as HTMLElement & { hide: () => Promise<void> }
    item.hide = vi.fn(async () => {})
    item.querySelector<HTMLElement>('.toast-action')!.dispatchEvent(new MouseEvent('click'))

    expect(restore).toHaveBeenCalledWith('d-3')
  })

  it('moves the focus to the undo button when the deleted element held it', async () => {
    const deletion = await setup(async () => ({ data: { deleteTodo: { id: '1', deletionId: 'd-1' } } }))
    ;(document.activeElement as HTMLElement | null)?.blur()

    await deletion.remove('1')

    expect(document.activeElement?.classList.contains('toast-action')).toBe(true)
  })

  it('in an open panel, the message is inside it and the focus goes to undo when the comment is gone', async () => {
    const drawer = standInDrawer()
    const deleteButton = document.createElement('button')
    drawer.append(deleteButton)
    document.body.append(drawer)
    deleteButton.focus()
    const deletion = await setup(async () => {
      deleteButton.remove()

      return { data: { deleteComment: { id: '3', deletionId: 'd-3' } } }
    })

    await deletion.remove('3')

    const undo = drawer.querySelector('wa-toast .toast-action')
    expect(undo).not.toBeNull()
    expect(document.activeElement).toBe(undo)
  })

  it('when the panel closes on delete, waits for it and then puts the focus on undo', async () => {
    const drawer = standInDrawer()
    const deleteButton = document.createElement('button')
    const card = document.createElement('button')
    drawer.append(deleteButton)
    document.body.append(card, drawer)
    deleteButton.focus()
    const deletion = await setup(async () => ({ data: { deleteTask: { id: '7', deletionId: 'd-7' } } }), () => {
      drawer.open = false
      // The panel gives the focus back to what opened it once it has closed.
      setTimeout(() => {
        card.focus()
        drawer.dispatchEvent(new Event('wa-after-hide'))
      }, 10)
    })

    await deletion.remove('7')

    expect(drawer.querySelector('wa-toast')).toBeNull()
    expect(document.activeElement?.classList.contains('toast-action')).toBe(true)
    card.remove()
  })

  it('also waits for a panel that closed while the delete was reloading', async () => {
    const drawer = standInDrawer()
    const deleteButton = document.createElement('button')
    const card = document.createElement('button')
    drawer.append(deleteButton)
    document.body.append(card, drawer)
    deleteButton.focus()
    const deletion = await setup(async () => {
      // The task is gone after reloading: the details close themselves.
      drawer.open = false
      setTimeout(() => {
        card.focus()
        drawer.dispatchEvent(new Event('wa-after-hide'))
      }, 10)

      return { data: { deleteTask: { id: '7', deletionId: 'd-7' } } }
    })

    await deletion.remove('7')

    expect(document.activeElement?.classList.contains('toast-action')).toBe(true)
    card.remove()
  })

  it('takes a panel as closing from wa-hide, though it says open while it animates', async () => {
    const drawer = standInDrawer()
    const deleteButton = document.createElement('button')
    drawer.append(deleteButton)
    document.body.append(drawer)
    deleteButton.focus()
    const deletion = await setup(async () => ({ data: { deleteTask: { id: '7', deletionId: 'd-7' } } }), () => {
      // Like wa-drawer: `open` stays true until the hide animation is over.
      drawer.dispatchEvent(new Event('wa-hide'))
      setTimeout(() => {
        drawer.open = false
        drawer.dispatchEvent(new Event('wa-after-hide'))
      }, 10)
    })

    await deletion.remove('7')

    expect(drawer.querySelector('wa-toast')).toBeNull()
    expect(document.activeElement?.classList.contains('toast-action')).toBe(true)
  })

  it('leaves the focus where it is when the deleted element did not hold it', async () => {
    const other = document.createElement('button')
    document.body.append(other)
    other.focus()
    const deletion = await setup(async () => ({ data: { deleteTodo: { id: '1', deletionId: 'd-1' } } }))

    await deletion.remove('1')

    expect(document.activeElement).toBe(other)
    other.remove()
  })

  it('shows nothing when the delete failed', async () => {
    const onDeleted = vi.fn()
    const refuse = vi.fn(async () => Promise.reject(new Error('refused')))
    const deletion = await setup(refuse, onDeleted)

    await deletion.remove('9')

    expect(onDeleted).not.toHaveBeenCalled()
    expect(document.querySelector('wa-toast-item')).toBeNull()
  })
})
