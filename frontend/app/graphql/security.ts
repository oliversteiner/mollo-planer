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

export const apiTokensQuery = graphql(`
  query ApiTokens {
    me {
      id
      apiTokens { id name createdAt lastUsedAt expiresAt }
    }
  }
`)

export const createApiTokenMutation = graphql(`
  mutation CreateApiToken($name: String!) {
    createApiToken(name: $name) {
      plainTextToken
      token { id name }
    }
  }
`)

export const deleteApiTokenMutation = graphql(`
  mutation DeleteApiToken($id: ID!) {
    deleteApiToken(id: $id) { id }
  }
`)
