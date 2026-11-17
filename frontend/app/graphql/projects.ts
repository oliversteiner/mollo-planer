import { graphql } from '~/graphql/generated'

export const projectsQuery = graphql(`
  query Projects($archived: Boolean = false) {
    projects(archived: $archived) {
      id
      name
      description
      viewerRole { key names { de en } }
      progress { done total percent }
      members {
        role { key names { de en } }
        user { id name avatarUrl }
      }
    }
  }
`)

export const projectHeaderQuery = graphql(`
  query ProjectHeader($id: ID!) {
    project(id: $id) {
      id
      planerRevision
      name
      description
      icon
      archivedAt
      viewerRole { key names { de en } }
      viewerRights
      labelSet { id name labels { id name category color position } }
    }
  }
`)

/** Only the revision of the planer file, for the polling of timeline and kanban (S3f). */
export const projectRevisionQuery = graphql(`
  query ProjectRevision($id: ID!) {
    project(id: $id) {
      id
      planerRevision
    }
  }
`)

export const createProjectMutation = graphql(`
  mutation CreateProject($input: CreateProjectInput!) {
    createProject(input: $input) {
      id
      name
    }
  }
`)

/** Projects listed in the sidebar. */
export const sidebarProjectsQuery = graphql(`
  query SidebarProjects {
    projects { id name icon }
  }
`)
