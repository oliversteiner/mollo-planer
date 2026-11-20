import { graphql } from '~/graphql/generated'

/** The numbers of the page "Statistik" of a project (counted in the database, the trash left out). */
export const projectStatisticsQuery = graphql(`
  query ProjectStatistics($projectId: ID!) {
    projectStatistics(projectId: $projectId) {
      milestones { total released planned unversioned }
      todos
      tasks
      progress { total done percent }
      tasksByStatus { status count percent }
      tasksByLabel { label { id name color position } count }
      tasksByPerson { user { id name avatarUrl isAgent } open done }
      due { overdue soon undated }
      perMilestone {
        milestone { id title status release { version status date } }
        todos
        tasks
        progress { total done percent }
      }
      doneByWeek { weekStart count }
      withCommit
      comments
    }
  }
`)
