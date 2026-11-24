import type { Ref } from 'vue'

/**
 * Which items of a scrolling list have come into view at least once (Auftrag 36 B): a tile loads its icon only then, so a
 * list of 120 icons does not ask for 120 files when it opens. `track(element, key)` goes into a function ref of the tile;
 * `isSeen(key)` says whether to render the real content. Without IntersectionObserver (old browsers, tests) every tile
 * counts as seen at once.
 * Starter kit (docs/starter-kit.md): belongs to layers/core once that layer exists; no move before.
 */
export function useVisibleOnce(root: Ref<HTMLElement | null>, margin = '80px') {
  const seen = reactive(new Set<string>())
  const keys = new WeakMap<Element, string>()
  let observer: IntersectionObserver | null = null

  function observerOrNull(): IntersectionObserver | null {
    if (typeof IntersectionObserver === 'undefined') {
      return null
    }

    observer ??= new IntersectionObserver((entries) => {
      for (const entry of entries) {
        const key = keys.get(entry.target)

        if (entry.isIntersecting && key !== undefined) {
          seen.add(key)
          observer?.unobserve(entry.target)
        }
      }
    }, { root: root.value, rootMargin: margin })

    return observer
  }

  // The tiles register while the list is being drawn, possibly before the ref of the list itself is set. So the observer is
  // made one tick later, when `root` is there: otherwise the viewport would be its root and not the scrolling list.
  const queue: Array<[Element, string]> = []
  let scheduled = false

  function flush(): void {
    scheduled = false
    const active = observerOrNull()

    for (const [element, key] of queue.splice(0)) {
      if (!active) {
        seen.add(key)

        continue
      }

      keys.set(element, key)
      active.observe(element)
    }
  }

  function track(element: unknown, key: string): void {
    if (!(element instanceof Element) || seen.has(key)) {
      return
    }

    queue.push([element, key])

    if (!scheduled) {
      scheduled = true
      void nextTick(flush)
    }
  }

  /** Forget what was seen and stop watching (the list is closed). */
  function reset(): void {
    queue.length = 0
    observer?.disconnect()
    observer = null
    seen.clear()
  }

  onScopeDispose(() => observer?.disconnect())

  return { isSeen: (key: string): boolean => seen.has(key), track, reset }
}
