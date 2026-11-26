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
        release { version status date }
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
        release { version status date }
        priceCents
        billing { invoice { id number status } }
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
            userStory { role goal benefit }
            commentCount
            labels { id name color position }
            assignee { id name avatarUrl isAgent }
            viewerCanUpdate
            viewerCanDelete
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
    updateMilestone(id: $id, input: $input) { id title description icon dueDate release { version status date } }
  }
`)

export const moveMilestoneMutation = graphql(`
  mutation MoveMilestone($id: ID!, $position: Int!) {
    moveMilestone(id: $id, position: $position) { id }
  }
`)

/** The preview of «Nach Version ordnen»: the conditions and the order are decided by the backend, nothing is calculated here. */
export const milestoneVersionOrderQuery = graphql(`
  query MilestoneVersionOrder($projectId: ID!) {
    milestoneVersionOrder(projectId: $projectId) {
      revision
      canApply
      condition
      moveCount
      conditionMilestones { id title version }
      order { id title version from to moved }
    }
  }
`)

export const sortMilestonesMutation = graphql(`
  mutation SortMilestones($projectId: ID!, $expectedRevision: Int) {
    sortMilestones(projectId: $projectId, expectedRevision: $expectedRevision) { revision moveCount order before }
  }
`)

/** Puts the milestones back in an earlier order («Rückgängig»). */
export const reorderMilestonesMutation = graphql(`
  mutation ReorderMilestones($projectId: ID!, $ids: [ID!]!) {
    reorderSiblings(projectId: $projectId, level: MILESTONES, ids: $ids) { revision moveCount }
  }
`)

export const deleteMilestoneMutation = graphql(`
  mutation DeleteMilestone($id: ID!) {
    deleteMilestone(id: $id) { id deletionId }
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
    deleteTodo(id: $id) { id deletionId }
  }
`)
