import type { Command } from '~/utils/commands'

/**
 * The actions of the page in the command palette (docs/zen-modus.md §4.4). A page calls this once; the entries are
 * there while the page is and leave with it. Entries without the right are filtered by the palette.
 */
export function useCommands(commands: Command[] | (() => Command[])): void {
  const registry = useState<{ owner: number, commands: Command[] }[]>('commands', () => [])
  const owner = getCurrentInstance()?.uid ?? Math.random()

  watchEffect(() => {
    const list = typeof commands === 'function' ? commands() : commands

    registry.value = [...registry.value.filter(entry => entry.owner !== owner), { owner, commands: list }]
  })

  onBeforeUnmount(() => {
    registry.value = registry.value.filter(entry => entry.owner !== owner)
  })
}

/** Everything the pages have registered. */
export function usePageCommands() {
  const registry = useState<{ owner: number, commands: Command[] }[]>('commands', () => [])

  return computed(() => registry.value.flatMap(entry => entry.commands))
}

/** Open state of the palette, shared by the shortcut, the zen frame and the palette itself. */
export function useCommandPalette() {
  const open = useState<boolean>('command-palette', () => false)

  return {
    open,
    show: () => {
      open.value = true
    },
    hide: () => {
      open.value = false
    }
  }
}
