export interface ProjectOption {
  id: string
  name: string
}

/** Keeps the items of the chosen project. No project chosen (null or empty) keeps all. */
export function filterByProject<T>(items: readonly T[], projectId: string | null, projectIdOf: (item: T) => string): T[] {
  return projectId ? items.filter(item => projectIdOf(item) === projectId) : [...items]
}

/** The distinct projects of the items, sorted by name. */
export function projectOptions<T>(items: readonly T[], projectOf: (item: T) => ProjectOption): ProjectOption[] {
  const byId = new Map<string, ProjectOption>()

  for (const item of items) {
    const project = projectOf(item)
    byId.set(project.id, { id: project.id, name: project.name })
  }

  return [...byId.values()].sort((a, b) => a.name.localeCompare(b.name))
}
