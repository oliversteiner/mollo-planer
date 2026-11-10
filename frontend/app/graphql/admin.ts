import { graphql } from '~/graphql/generated'

export const adminUsersQuery = graphql(`
  query AdminUsers {
    users {
      id
      name
      email
      role
      jobFunction
      avatarUrl
      twoFactorConfirmed
      hasPasskey
      createdAt
    }
  }
`)

export const createUserMutation = graphql(`
  mutation CreateUser($input: CreateUserInput!) {
    createUser(input: $input) { id name }
  }
`)

export const updateUserMutation = graphql(`
  mutation UpdateUser($id: ID!, $input: UpdateUserInput!) {
    updateUser(id: $id, input: $input) { id name email role jobFunction }
  }
`)

export const resetUserTwoFactorMutation = graphql(`
  mutation ResetUserTwoFactor($id: ID!) {
    resetUserTwoFactor(id: $id) { id twoFactorConfirmed hasPasskey }
  }
`)

export const deleteUserMutation = graphql(`
  mutation DeleteUser($id: ID!) {
    deleteUser(id: $id) { id }
  }
`)

export const appSettingsQuery = graphql(`
  query AppSettings {
    appSettings { twoFactorEnabled twoFactorSwitchable }
  }
`)

export const updateAppSettingsMutation = graphql(`
  mutation UpdateAppSettings($twoFactorEnabled: Boolean!) {
    updateAppSettings(twoFactorEnabled: $twoFactorEnabled) { twoFactorEnabled twoFactorSwitchable }
  }
`)
