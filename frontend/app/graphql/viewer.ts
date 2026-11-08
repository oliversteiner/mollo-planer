import { graphql } from '~/graphql/generated'

/** The logged-in user and the app settings the shell needs. */
export const viewerQuery = graphql(`
  query Viewer {
    me {
      id
      name
      email
      isAdmin
      avatarUrl
    }
    appSettings {
      twoFactorEnabled
    }
  }
`)
