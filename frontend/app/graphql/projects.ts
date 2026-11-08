import { graphql } from '~/graphql/generated'

export const projectsQuery = graphql(`
  query Projects {
    projects {
      id
      name
      description
      viewerRole
      progress { done total percent }
      members {
        role
        user { id name avatarUrl }
      }
    }
  }
`)

export const projectHeaderQuery = graphql(`
  query ProjectHeader($id: ID!) {
    project(id: $id) {
      id
      name
      description
      viewerRole
      viewerAbilities
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

/** Projects for the choice in the sidebar. */
export const sidebarProjectsQuery = graphql(`
  query SidebarProjects {
    projects { id name }
  }
`)
