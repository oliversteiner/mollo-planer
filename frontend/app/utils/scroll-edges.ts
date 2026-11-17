/** Whether a box scrolled sideways can still go back (start) or on (end). */
export type ScrollEdges = { start: boolean, end: boolean }

/** One pixel of slack: zoomed pages report fractions. */
const SLACK = 1

export function scrollEdges(scrollLeft: number, clientWidth: number, scrollWidth: number): ScrollEdges {
  return {
    start: scrollLeft > SLACK,
    end: scrollLeft + clientWidth < scrollWidth - SLACK
  }
}
