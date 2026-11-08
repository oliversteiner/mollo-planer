import { graphql } from '~/graphql/generated'

export const securityQuery = graphql(`
  query Security {
    me {
      id
      twoFactorConfirmed
      hasPasskey
      passkeys {
        id
        name
        createdAt
        lastUsedAt
      }
    }
  }
`)
