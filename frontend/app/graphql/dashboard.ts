import { graphql } from '~/graphql/generated'

export const dashboardQuery = graphql(`
  query Dashboard($limit: Int) {
    dashboard {
      upcomingTodos(limit: $limit) {
        id
        title
        icon
        dueDate
        milestone {
          id
          title
          project { id name }
        }
      }
      myOpenTasks {
        id
        title
        icon
        status
        dueDate
        todo {
          id
          title
          milestone {
            project { id name }
          }
        }
      }
      projects {
        id
        name
        viewerRole
        progress { done total percent }
      }
    }
  }
`)
