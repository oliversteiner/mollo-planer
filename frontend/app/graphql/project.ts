import { graphql } from '~/graphql/generated'

export const projectOverviewQuery = graphql(`
  query ProjectOverview($id: ID!) {
    project(id: $id) {
      id
      milestones {
        id
        title
        description
        icon
        dueDate
        status
        progress { done total percent }
        todos {
          id
          title
          description
          icon
          dueDate
          status
          progress { done total percent }
        }
      }
    }
  }
`)

/** Milestones > todos > tasks of a project, for the timeline view. */
export const projectTimelineQuery = graphql(`
  query ProjectTimeline($id: ID!) {
    project(id: $id) {
      id
      milestones {
        id
        title
        description
        icon
        dueDate
        status
        progress { done total percent }
        todos {
          id
          title
          description
          icon
          dueDate
          status
          progress { done total percent }
          tasks {
            id
            title
            description
            icon
            status
            dueDate
            commentCount
            labels { id name color position }
            assignee { id name avatarUrl }
          }
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
      description
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
    updateMilestone(id: $id, input: $input) { id title description icon dueDate }
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
    updateTodo(id: $id, input: $input) { id title description icon dueDate }
  }
`)

export const deleteTodoMutation = graphql(`
  mutation DeleteTodo($id: ID!) {
    deleteTodo(id: $id) { id }
  }
`)

/**
 * After a change, reload every query on screen ('active'): statuses and
 * progress are derived by the backend. Queries not on screen use
 * cache-and-network and fetch fresh data when they are shown again.
 */
export const projectContentQueries = 'active' as const
