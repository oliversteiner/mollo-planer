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
        role { key names { de en } }
        user { id name email avatarUrl isAgent }
      }
    }
  }
`)

/** People who can be taken into a project (names and avatars only, no e-mail). Without a project: for a new project. */
export const memberCandidatesQuery = graphql(`
  query MemberCandidates($projectId: ID) {
    memberCandidates(projectId: $projectId) { id name avatarUrl isMember isAgent }
  }
`)

export const updateProjectMutation = graphql(`
  mutation UpdateProject($id: ID!, $input: UpdateProjectInput!) {
    updateProject(id: $id, input: $input) { id name description icon locale }
  }
`)

export const setProjectMemberMutation = graphql(`
  mutation SetProjectMember($projectId: ID!, $userId: ID!, $role: String!) {
    setProjectMember(projectId: $projectId, userId: $userId, role: $role) {
      id
      members { role { key names { de en } } user { id name email avatarUrl isAgent } }
    }
  }
`)

export const removeProjectMemberMutation = graphql(`
  mutation RemoveProjectMember($projectId: ID!, $userId: ID!) {
    removeProjectMember(projectId: $projectId, userId: $userId) {
      id
      members { role { key names { de en } } user { id name email avatarUrl isAgent } }
    }
  }
`)

export const deleteProjectMutation = graphql(`
  mutation DeleteProject($id: ID!) {
    deleteProject(id: $id) { id deletionId }
  }
`)

export const archiveProjectMutation = graphql(`
  mutation ArchiveProject($id: ID!) {
    archiveProject(id: $id) { id archivedAt viewerRights }
  }
`)

export const unarchiveProjectMutation = graphql(`
  mutation UnarchiveProject($id: ID!) {
    unarchiveProject(id: $id) { id archivedAt viewerRights }
  }
`)
