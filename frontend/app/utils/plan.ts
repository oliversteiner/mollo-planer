import type { DraftProjectPlanMutation, ProjectPlanInput } from '~/graphql/generated/graphql'

/** A draft of the AI assistant as the preview shows it. */
export type ProjectPlan = NonNullable<DraftProjectPlanMutation['draftProjectPlan']['plan']>

/**
 * The draft as mutation input: only the fields of ProjectPlanInput, so
 * Apollo's __typename does not end up in the request.
 */
export function toPlanInput(plan: ProjectPlan): ProjectPlanInput {
  const item = (source: { title: string, description: string | null, dueDate: string | null }) => ({
    title: source.title,
    description: source.description,
    dueDate: source.dueDate
  })

  return {
    name: plan.name,
    description: plan.description,
    locale: plan.locale,
    members: plan.members.map(member => ({ email: member.email, role: member.role })),
    milestones: plan.milestones.map(milestone => ({
      ...item(milestone),
      todos: milestone.todos.map(todo => ({
        ...item(todo),
        tasks: todo.tasks.map(task => ({ ...item(task), assigneeEmail: task.assigneeEmail }))
      }))
    }))
  }
}

/** Number of milestones, todos, tasks and assigned tasks. */
export function planCounts(plan: ProjectPlan) {
  const todos = plan.milestones.flatMap(milestone => milestone.todos)
  const tasks = todos.flatMap(todo => todo.tasks)

  return {
    milestones: plan.milestones.length,
    todos: todos.length,
    tasks: tasks.length,
    assigned: tasks.filter(task => task.assigneeEmail).length
  }
}

export type TextBlock = { kind: 'paragraph', text: string } | { kind: 'list', items: string[] }

/**
 * Plain-text description as the backend reads it: blank lines separate
 * paragraphs, a block of "- " lines is a list. Shown as text, never HTML.
 */
export function plainTextBlocks(text: string | null | undefined): TextBlock[] {
  return (text ?? '')
    .replace(/\r\n/g, '\n')
    .split(/\n\s*\n/)
    .map(block => block.split('\n').map(line => line.trim()).filter(Boolean))
    .filter(lines => lines.length > 0)
    .map(lines => lines.every(line => /^[-*]\s+/.test(line))
      ? { kind: 'list' as const, items: lines.map(line => line.replace(/^[-*]\s+/, '')) }
      : { kind: 'paragraph' as const, text: lines.join(' ') })
}
