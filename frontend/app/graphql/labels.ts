import { graphql } from '~/graphql/generated'

/** All label sets (built-in first), for the project settings and the management page. */
export const labelSetsQuery = graphql(`
  query LabelSets {
    viewerSystemAbilities
    labelSets {
      id
      name
      description
      isBuiltin
      usedByProjects
      viewerCanUpdate
      viewerCanDelete
      labels { id name category color position }
    }
  }
`)

export const setProjectLabelSetMutation = graphql(`
  mutation SetProjectLabelSet($projectId: ID!, $labelSetId: ID) {
    setProjectLabelSet(projectId: $projectId, labelSetId: $labelSetId) {
      id
      labelSet { id name labels { id name category color position } }
    }
  }
`)

export const setTaskLabelsMutation = graphql(`
  mutation SetTaskLabels($taskId: ID!, $labelIds: [ID!]!) {
    setTaskLabels(taskId: $taskId, labelIds: $labelIds) {
      id
      labels { id name color position }
    }
  }
`)

export const createLabelSetMutation = graphql(`
  mutation CreateLabelSet($input: LabelSetInput!) {
    createLabelSet(input: $input) { id name }
  }
`)

export const duplicateLabelSetMutation = graphql(`
  mutation DuplicateLabelSet($id: ID!) {
    duplicateLabelSet(id: $id) { id name }
  }
`)

export const updateLabelSetMutation = graphql(`
  mutation UpdateLabelSet($id: ID!, $input: LabelSetInput!) {
    updateLabelSet(id: $id, input: $input) { id name }
  }
`)

export const deleteLabelSetMutation = graphql(`
  mutation DeleteLabelSet($id: ID!) {
    deleteLabelSet(id: $id) { id }
  }
`)
