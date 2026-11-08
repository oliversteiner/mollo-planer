import { graphql } from '~/graphql/generated'

export const projectOverviewQuery = graphql(`
  query ProjectOverview($id: ID!) {
    project(id: $id) {
      id
      milestones {
        id
        title
        icon
        dueDate
        status
        progress { done total percent }
        todos {
          id
          title
          icon
          dueDate
          status
          progress { done total percent }
        }
      }
    }
  }
`)

export const projectTodosQuery = graphql(`
  query ProjectTodos($projectId: ID!, $milestoneId: ID, $status: ItemStatus) {
    todos(projectId: $projectId, milestoneId: $milestoneId, status: $status) {
      id
      title
      icon
      dueDate
      status
      progress { done total percent }
      milestone { id title }
    }
  }
`)

export const createMilestoneMutation = graphql(`
  mutation CreateMilestone($input: CreateMilestoneInput!) {
    createMilestone(input: $input) { id }
  }
`)

export const updateMilestoneMutation = graphql(`
  mutation UpdateMilestone($id: ID!, $input: UpdateMilestoneInput!) {
    updateMilestone(id: $id, input: $input) { id title dueDate }
  }
`)

export const deleteMilestoneMutation = graphql(`
  mutation DeleteMilestone($id: ID!) {
    deleteMilestone(id: $id) { id }
  }
`)

export const createTodoMutation = graphql(`
  mutation CreateTodo($input: CreateTodoInput!) {
    createTodo(input: $input) { id }
  }
`)

export const updateTodoMutation = graphql(`
  mutation UpdateTodo($id: ID!, $input: UpdateTodoInput!) {
    updateTodo(id: $id, input: $input) { id title dueDate }
  }
`)

export const deleteTodoMutation = graphql(`
  mutation DeleteTodo($id: ID!) {
    deleteTodo(id: $id) { id }
  }
`)

/** Lists that show milestones or todos and must reload after a change. */
export const projectContentQueries = ['ProjectOverview', 'ProjectTodos', 'Dashboard', 'Projects']
