import { graphql } from '~/graphql/generated'

/** The next step of a plan from the chat: reply, buttons, the plan (canonical text) and the preview (dry run, appendOnly). */
export const draftPlanMutation = graphql(`
  mutation DraftPlan($messages: [PlanChatMessageInput!]!, $projectId: ID, $templateId: ID, $targetKind: PlanTargetKind, $currentPlan: String, $ideaId: ID) {
    draftPlan(messages: $messages, projectId: $projectId, templateId: $templateId, targetKind: $targetKind, currentPlan: $currentPlan, ideaId: $ideaId) {
      reply
      plan
      projectId
      templateId
      hints
      choices { kind label detail value recommended }
      preview {
        ok
        dryRun
        projectId
        changes { kind path id fields title }
        errors { path kind message }
        warnings { path email message }
      }
    }
  }
`)

/** Projects the person may add to, for the choice of the project. */
export const assistantProjectQuery = graphql(`
  query AssistantProject($id: ID!) {
    project(id: $id) { id name archivedAt }
  }
`)
