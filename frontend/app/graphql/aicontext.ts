import { graphql } from '~/graphql/generated'

/** Background knowledge of a project for the assistant (docs/auftrag-m6.md §3). */
export const projectAiContextQuery = graphql(`
  query ProjectAiContext($id: ID!) {
    project(id: $id) {
      id
      aiContext { text fileName fileText characters sharedWithTools updatedAt }
    }
  }
`)

export const setProjectAiContextMutation = graphql(`
  mutation SetProjectAiContext($projectId: ID!, $input: ProjectAiContextInput!) {
    setProjectAiContext(projectId: $projectId, input: $input) {
      id
      aiContext { text fileName fileText characters sharedWithTools updatedAt }
    }
  }
`)

export const uploadProjectAiContextFileMutation = graphql(`
  mutation UploadProjectAiContextFile($projectId: ID!, $file: Upload!) {
    uploadProjectAiContextFile(projectId: $projectId, file: $file) {
      id
      aiContext { text fileName fileText characters sharedWithTools updatedAt }
    }
  }
`)

export const removeProjectAiContextFileMutation = graphql(`
  mutation RemoveProjectAiContextFile($projectId: ID!) {
    removeProjectAiContextFile(projectId: $projectId) {
      id
      aiContext { text fileName fileText characters sharedWithTools updatedAt }
    }
  }
`)

export const clearProjectAiContextMutation = graphql(`
  mutation ClearProjectAiContext($projectId: ID!) {
    clearProjectAiContext(projectId: $projectId) {
      id
      aiContext { text fileName fileText characters sharedWithTools updatedAt }
    }
  }
`)
