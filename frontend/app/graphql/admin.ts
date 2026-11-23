import { graphql } from '~/graphql/generated'

export const adminUsersQuery = graphql(`
  query AdminUsers {
    users {
      id
      name
      email
      role { key isDefault names { de en } }
      activity
      avatarUrl
      isAgent
      apiTokens { id name createdAt lastUsedAt expiresAt }
      twoFactorConfirmed
      hasPasskey
      createdAt
      approved
      emailVerified
    }
  }
`)

export const approveUserMutation = graphql(`
  mutation ApproveUser($id: ID!) {
    approveUser(id: $id) { id approved }
  }
`)

export const rejectUserMutation = graphql(`
  mutation RejectUser($id: ID!) {
    rejectUser(id: $id) { id }
  }
`)

export const createUserMutation = graphql(`
  mutation CreateUser($input: CreateUserInput!) {
    createUser(input: $input) { id name }
  }
`)

export const updateUserMutation = graphql(`
  mutation UpdateUser($id: ID!, $input: UpdateUserInput!) {
    updateUser(id: $id, input: $input) { id name email role { key isDefault names { de en } } activity }
  }
`)

export const resetUserTwoFactorMutation = graphql(`
  mutation ResetUserTwoFactor($id: ID!) {
    resetUserTwoFactor(id: $id) { id twoFactorConfirmed hasPasskey }
  }
`)

export const createAgentTokenMutation = graphql(`
  mutation CreateAgentToken($userId: ID!, $name: String!) {
    createAgentToken(userId: $userId, name: $name) {
      plainTextToken
      token { id name }
    }
  }
`)

export const revokeAgentTokenMutation = graphql(`
  mutation RevokeAgentToken($userId: ID!, $tokenId: ID!) {
    revokeAgentToken(userId: $userId, tokenId: $tokenId) { id }
  }
`)

export const deleteUserMutation = graphql(`
  mutation DeleteUser($id: ID!) {
    deleteUser(id: $id) { id }
  }
`)

export const appSettingsQuery = graphql(`
  query AppSettings {
    appSettings { twoFactorEnabled twoFactorSwitchable projectCreatorRole { key names { de en } } }
  }
`)

export const setProjectCreatorRoleMutation = graphql(`
  mutation SetProjectCreatorRole($key: String!) {
    setProjectCreatorRole(roleKey: $key) { projectCreatorRole { key names { de en } } }
  }
`)

export const updateAppSettingsMutation = graphql(`
  mutation UpdateAppSettings($twoFactorEnabled: Boolean!) {
    updateAppSettings(twoFactorEnabled: $twoFactorEnabled) { twoFactorEnabled twoFactorSwitchable }
  }
`)
