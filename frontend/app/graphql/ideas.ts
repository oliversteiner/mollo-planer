import { graphql } from '~/graphql/generated'

export const ideasQuery = graphql(`
  query Ideas($status: IdeaStatus, $first: Int!) {
    ideas(status: $status, first: $first) {
      data {
        id
        title
        description
        status
        draft
        createdAt
        adoptedAt
        viewerCanUpdate
        project { id name }
        author { id name isAgent }
        todos { id title project { id } }
      }
    }
  }
`)

export const createIdeaMutation = graphql(`
  mutation CreateIdea($input: CreateIdeaInput!) {
    createIdea(input: $input) { id title status }
  }
`)

export const updateIdeaMutation = graphql(`
  mutation UpdateIdea($id: ID!, $input: UpdateIdeaInput!) {
    updateIdea(id: $id, input: $input) { id title description status draft project { id name } }
  }
`)

export const setIdeaStatusMutation = graphql(`
  mutation SetIdeaStatus($id: ID!, $status: IdeaStatus!) {
    setIdeaStatus(id: $id, status: $status) { id status adoptedAt }
  }
`)

export const deleteIdeaMutation = graphql(`
  mutation DeleteIdea($id: ID!) {
    deleteIdea(id: $id) { id deletionId }
  }
`)
