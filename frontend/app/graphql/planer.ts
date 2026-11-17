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
      changes { kind path id fields }
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
