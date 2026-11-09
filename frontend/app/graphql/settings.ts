import { graphql } from '~/graphql/generated'

export const projectSettingsQuery = graphql(`
  query ProjectSettings($id: ID!) {
    project(id: $id) {
      id
      name
      description
      icon
      locale
      members {
        role
        user { id name email avatarUrl }
      }
    }
  }
`)

/** All users (for admins and people who manage members somewhere). */
export const usersQuery = graphql(`
  query Users {
    users { id name email avatarUrl }
  }
`)

export const updateProjectMutation = graphql(`
  mutation UpdateProject($id: ID!, $input: UpdateProjectInput!) {
    updateProject(id: $id, input: $input) { id name description icon locale }
  }
`)

export const setProjectMemberMutation = graphql(`
  mutation SetProjectMember($projectId: ID!, $userId: ID!, $role: ProjectRole!) {
    setProjectMember(projectId: $projectId, userId: $userId, role: $role) {
      id
      members { role user { id name email avatarUrl } }
    }
  }
`)

export const removeProjectMemberMutation = graphql(`
  mutation RemoveProjectMember($projectId: ID!, $userId: ID!) {
    removeProjectMember(projectId: $projectId, userId: $userId) {
      id
      members { role user { id name email avatarUrl } }
    }
  }
`)

export const deleteProjectMutation = graphql(`
  mutation DeleteProject($id: ID!) {
    deleteProject(id: $id) { id }
  }
`)
