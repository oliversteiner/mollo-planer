import type { Ref } from 'vue'
import { scrollEdges, type ScrollEdges } from '~/utils/scroll-edges'

/**
 * Follows a box that scrolls sideways: can it still scroll back or on? Updated
 * on scrolling and when the box or its content changes size.
 */
export function useScrollEdges(box: Ref<HTMLElement | null>) {
  const edges = ref<ScrollEdges>({ start: false, end: false })
  let observer: ResizeObserver | null = null

  function update(): void {
    const element = box.value
    edges.value = element ? scrollEdges(element.scrollLeft, element.clientWidth, element.scrollWidth) : { start: false, end: false }
  }

  watch(box, (element, previous) => {
    previous?.removeEventListener('scroll', update)
    observer?.disconnect()
    observer = null

    if (element) {
      element.addEventListener('scroll', update, { passive: true })
      if (typeof ResizeObserver !== 'undefined') {
        observer = new ResizeObserver(update)
        observer.observe(element)
        if (element.firstElementChild) {
          observer.observe(element.firstElementChild)
        }
      }
    }
    update()
  }, { immediate: true, flush: 'post' })

  onBeforeUnmount(() => {
    box.value?.removeEventListener('scroll', update)
    observer?.disconnect()
  })

  return { edges, update }
}
