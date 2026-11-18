import { graphql } from '~/graphql/generated'

/** The planer file of a project as canonical text (planer.export). */
export const exportPlanQuery = graphql(`
  query ExportPlan($projectId: ID!) {
    exportPlan(projectId: $projectId)
  }
`)

/** Writes a planer file into a project, or into a new one (without projectId). dryRun: only report. */
export const importPlanMutation = graphql(`
  mutation ImportPlan($projectId: ID, $document: String!, $mode: ImportMode, $dryRun: Boolean) {
    importPlan(projectId: $projectId, document: $document, mode: $mode, dryRun: $dryRun) {
      ok
      dryRun
      projectId
      changes { kind path id fields title }
      errors { path kind message }
    }
  }
`)

export const createTemplateFromProjectMutation = graphql(`
  mutation CreateTemplateFromProject($projectId: ID!, $name: String!) {
    createTemplateFromProject(projectId: $projectId, name: $name) { id name }
  }
`)

export const createTemplateFromFileMutation = graphql(`
  mutation CreateTemplateFromFile($document: String!) {
    createTemplateFromFile(document: $document) { id name }
  }
`)

/** The notification of a project when its planer file changes (project.update; null without the right). */
export const projectWebhookQuery = graphql(`
  query ProjectWebhook($id: ID!) {
    project(id: $id) {
      id
      webhook { kind target hasSecret lastSentAt lastSentRevision lastFailedAt lastError }
    }
  }
`)

export const setProjectWebhookMutation = graphql(`
  mutation SetProjectWebhook($projectId: ID!, $input: ProjectWebhookInput!) {
    setProjectWebhook(projectId: $projectId, input: $input) {
      id
      webhook { kind target hasSecret lastSentAt lastSentRevision lastFailedAt lastError }
    }
  }
`)

export const removeProjectWebhookMutation = graphql(`
  mutation RemoveProjectWebhook($projectId: ID!) {
    removeProjectWebhook(projectId: $projectId) { id webhook { kind } }
  }
`)

export const testProjectWebhookMutation = graphql(`
  mutation TestProjectWebhook($projectId: ID!) {
    testProjectWebhook(projectId: $projectId) { ok message }
  }
`)
