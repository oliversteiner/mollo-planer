/**
 * Did someone else change the project (S3f, docs/planer-abgleich.md §10)?
 * `known` is the revision this tab has loaded. After an own change one step
 * is ours; anything beyond it, and every step seen by the polling, comes
 * from someone else. Unknown values never count as a change.
 */
export function isForeignChange(known: number | null, current: number | null, ownChange: boolean): boolean {
  if (known === null || current === null) {
    return false
  }

  return current > known + (ownChange ? 1 : 0)
}
