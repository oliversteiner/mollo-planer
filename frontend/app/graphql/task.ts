import { graphql } from '~/graphql/generated'

export const kanbanQuery = graphql(`
  query Kanban($projectId: ID!) {
    kanban(projectId: $projectId) {
      status
      tasks {
        id
        title
        icon
        status
        boardPosition
        dueDate
        userStory { role goal benefit }
        commentCount
        labels { id name color position }
        assignee { id name avatarUrl }
        todo { id title }
      }
    }
  }
`)

export const moveTaskToTodoMutation = graphql(`
  mutation MoveTaskToTodo($id: ID!, $todoId: ID!) {
    moveTaskToTodo(id: $id, todoId: $todoId) { id }
  }
`)

export const projectMembersQuery = graphql(`
  query ProjectMembers($id: ID!) {
    project(id: $id) {
      id
      members {
        role
        user { id name avatarUrl }
      }
    }
  }
`)

export const taskQuery = graphql(`
  query TaskDetail($id: ID!) {
    task(id: $id) {
      id
      title
      description
      icon
      status
      boardPosition
      dueDate
      userStory { role goal benefit }
      assignee { id name avatarUrl }
      labels { id name color position }
      todo {
        id
        title
        milestone {
          id
          title
          project { id name labelSet { id labels { id name category color position } } }
        }
      }
      comments {
        id
        body
        createdAt
        updatedAt
        viewerCanEdit
        viewerCanDelete
        author { id name avatarUrl }
      }
    }
  }
`)

export const createTaskMutation = graphql(`
  mutation CreateTask($input: CreateTaskInput!) {
    createTask(input: $input) { id }
  }
`)

export const updateTaskMutation = graphql(`
  mutation UpdateTask($id: ID!, $input: UpdateTaskInput!) {
    updateTask(id: $id, input: $input) { id title description icon dueDate userStory { role goal benefit } }
  }
`)

export const moveTaskMutation = graphql(`
  mutation MoveTask($id: ID!, $status: TaskStatus!, $position: Int!) {
    moveTask(id: $id, status: $status, position: $position) { id status boardPosition }
  }
`)

export const assignTaskMutation = graphql(`
  mutation AssignTask($id: ID!, $assigneeId: ID) {
    assignTask(id: $id, assigneeId: $assigneeId) { id assignee { id name avatarUrl } }
  }
`)

export const deleteTaskMutation = graphql(`
  mutation DeleteTask($id: ID!) {
    deleteTask(id: $id) { id }
  }
`)

export const addCommentMutation = graphql(`
  mutation AddComment($input: AddCommentInput!) {
    addComment(input: $input) { id }
  }
`)

export const updateCommentMutation = graphql(`
  mutation UpdateComment($id: ID!, $input: UpdateCommentInput!) {
    updateComment(id: $id, input: $input) { id body updatedAt }
  }
`)

export const deleteCommentMutation = graphql(`
  mutation DeleteComment($id: ID!) {
    deleteComment(id: $id) { id }
  }
`)

/** Kanban columns in board order (same as the backend's TaskStatus). */
export const TASK_STATUSES = ['IDEA', 'PLANNED', 'OPEN', 'IN_PROGRESS', 'REVIEW', 'DONE'] as const
