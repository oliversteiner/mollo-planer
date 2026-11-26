import type { Ref } from 'vue'

/**
 * A copy of `source` that follows it after `delay` ms without a change (Auftrag 36 B: a search field that types should not
 * search with every key). `flush()` sets it at once, for example when the field is cleared by code.
 * Starter kit (docs/starter-kit.md): belongs to layers/core once that layer exists; no move before.
 */
export function useDebouncedRef<T>(source: Ref<T>, delay = 250): { value: Ref<T>, flush: () => void } {
  const value = ref(source.value) as Ref<T>
  let timer: ReturnType<typeof setTimeout> | undefined

  watch(source, (next) => {
    clearTimeout(timer)
    timer = setTimeout(() => {
      value.value = next
    }, delay)
  })

  onScopeDispose(() => clearTimeout(timer))

  return {
    value,
    flush: () => {
      clearTimeout(timer)
      value.value = source.value
    }
  }
}
