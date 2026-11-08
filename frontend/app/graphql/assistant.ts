import { graphql } from '~/graphql/generated'

export const draftProjectPlanMutation = graphql(`
  mutation DraftProjectPlan($messages: [PlanChatMessageInput!]!, $currentPlan: ProjectPlanInput) {
    draftProjectPlan(messages: $messages, currentPlan: $currentPlan) {
      reply
      plan {
        name
        description
        locale
        members { email role }
        milestones {
          title
          description
          dueDate
          todos {
            title
            description
            dueDate
            tasks { title description dueDate assigneeEmail }
          }
        }
      }
    }
  }
`)

export const importProjectPlanMutation = graphql(`
  mutation ImportProjectPlan($plan: ProjectPlanInput!, $notifyAssignees: Boolean) {
    importProjectPlan(plan: $plan, notifyAssignees: $notifyAssignees) {
      id
      name
    }
  }
`)

/** Accounts, to show names and avatars of the planned people. */
export const assistantPeopleQuery = graphql(`
  query AssistantPeople {
    users { id name email avatarUrl }
  }
`)
