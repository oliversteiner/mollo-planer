import { graphql } from '~/graphql/generated'

/** All templates (built-in first), for the templates page and the choice of a template. */
export const templatesQuery = graphql(`
  query Templates {
    viewerRights
    templates {
      id
      key
      name
      description
      locale
      isBuiltin
      projectCount
      viewerCanUpdate
      viewerCanDelete
      createdBy { id name }
      labels { key name names { locale name } category categoryLabel color }
      outline { title version todos { title tasks { title labels } } }
    }
  }
`)

export const createTemplateMutation = graphql(`
  mutation CreateTemplate($input: TemplateInput!) {
    createTemplate(input: $input) { id name }
  }
`)

export const duplicateTemplateMutation = graphql(`
  mutation DuplicateTemplate($id: ID!) {
    duplicateTemplate(id: $id) { id name }
  }
`)

export const updateTemplateMutation = graphql(`
  mutation UpdateTemplate($id: ID!, $input: UpdateTemplateInput!) {
    updateTemplate(id: $id, input: $input) { id name }
  }
`)

export const deleteTemplateMutation = graphql(`
  mutation DeleteTemplate($id: ID!) {
    deleteTemplate(id: $id)
  }
`)

/** The labels of a project for the settings: all names, the category as written, how many tasks carry each. */
export const projectLabelsQuery = graphql(`
  query ProjectLabels($id: ID!) {
    project(id: $id) {
      id
      locale
      labels { id key name names { locale name } category categoryLabel color position taskCount }
    }
  }
`)

export const updateProjectLabelsMutation = graphql(`
  mutation UpdateProjectLabels($projectId: ID!, $labels: [LabelInput!]!) {
    updateProjectLabels(projectId: $projectId, labels: $labels) {
      id
      labels { id key name names { locale name } category categoryLabel color position taskCount }
    }
  }
`)

export const applyTemplateLabelsMutation = graphql(`
  mutation ApplyTemplateLabels($projectId: ID!, $templateId: ID!, $mode: TemplateLabelMode!) {
    applyTemplateLabels(projectId: $projectId, templateId: $templateId, mode: $mode) {
      id
      labels { id key name names { locale name } category categoryLabel color position taskCount }
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
