import { describe, expect, it } from 'vitest'
import { planCounts, plainTextBlocks, toPlanInput, type ProjectPlan } from '../../app/utils/plan'

const plan = {
  __typename: 'ProjectPlan',
  name: 'Podcast',
  description: null,
  locale: 'DE',
  members: [{ __typename: 'ProjectPlanMember', email: 'lindita@example.ch', role: 'MEMBER' }],
  milestones: [{
    __typename: 'ProjectPlanMilestone',
    title: 'Konzept steht',
    description: 'Text',
    dueDate: '2027-01-31',
    todos: [{
      __typename: 'ProjectPlanTodo',
      title: 'Format',
      description: null,
      dueDate: null,
      tasks: [
        { __typename: 'ProjectPlanTask', title: 'Folgen skizzieren', description: null, dueDate: null, assigneeEmail: 'lindita@example.ch' },
        { __typename: 'ProjectPlanTask', title: 'Namen sammeln', description: null, dueDate: null, assigneeEmail: null }
      ]
    }]
  }]
} as unknown as ProjectPlan

describe('toPlanInput', () => {
  it('drops __typename everywhere, so the draft is valid mutation input', () => {
    expect(JSON.stringify(toPlanInput(plan))).not.toContain('__typename')
    expect(toPlanInput(plan).milestones[0]!.todos[0]!.tasks[0]!.assigneeEmail).toBe('lindita@example.ch')
  })
})

describe('planCounts', () => {
  it('counts all levels and the assigned tasks', () => {
    expect(planCounts(plan)).toEqual({ milestones: 1, todos: 1, tasks: 2, assigned: 1 })
  })
})

describe('plainTextBlocks', () => {
  it('reads paragraphs and "- " lists like the backend', () => {
    expect(plainTextBlocks('Erster\nAbsatz\n\n- Eins\n- Zwei')).toEqual([
      { kind: 'paragraph', text: 'Erster Absatz' },
      { kind: 'list', items: ['Eins', 'Zwei'] }
    ])
    expect(plainTextBlocks(null)).toEqual([])
  })
})
