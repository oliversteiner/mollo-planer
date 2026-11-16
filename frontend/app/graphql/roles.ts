import { graphql } from '~/graphql/generated'

/** The roles of one scope, for the role selection of users and members. */
export const rolesQuery = graphql(`
  query Roles($scope: RoleScope) {
    roles(scope: $scope) {
      id
      key
      scope
      isDefault
      names { de en }
      rights
    }
  }
`)

/** Everything the settings of roles and functions show (/admin/roles, right roles.manage). */
export const roleSettingsQuery = graphql(`
  query RoleSettings {
    roles {
      id
      key
      scope
      isDefault
      isStandard
      names { de en }
      functions { id key names { de en } }
      projectCap
      usageCount
    }
    roleFunctions {
      id
      key
      scope
      isStandard
      names { de en }
      rights
      usageCount
    }
    systemRights: rights(scope: SYSTEM)
    projectRights: rights(scope: PROJECT)
  }
`)

export const createRoleMutation = graphql(`
  mutation CreateRole($input: CreateRoleInput!) {
    createRole(input: $input) { id key names { de en } }
  }
`)

export const updateRoleMutation = graphql(`
  mutation UpdateRole($id: ID!, $input: UpdateRoleInput!) {
    updateRole(id: $id, input: $input) { id key names { de en } }
  }
`)

export const duplicateRoleMutation = graphql(`
  mutation DuplicateRole($id: ID!) {
    duplicateRole(id: $id) { id key names { de en } }
  }
`)

export const deleteRoleMutation = graphql(`
  mutation DeleteRole($id: ID!) {
    deleteRole(id: $id) { id }
  }
`)

export const setDefaultRoleMutation = graphql(`
  mutation SetDefaultRole($id: ID!) {
    setDefaultRole(id: $id) { id key names { de en } }
  }
`)

export const resetRoleMutation = graphql(`
  mutation ResetRole($id: ID!) {
    resetRole(id: $id) { id key names { de en } }
  }
`)

export const createRoleFunctionMutation = graphql(`
  mutation CreateRoleFunction($input: CreateRoleFunctionInput!) {
    createRoleFunction(input: $input) { id key names { de en } }
  }
`)

export const updateRoleFunctionMutation = graphql(`
  mutation UpdateRoleFunction($id: ID!, $input: UpdateRoleFunctionInput!) {
    updateRoleFunction(id: $id, input: $input) { id key names { de en } }
  }
`)

export const duplicateRoleFunctionMutation = graphql(`
  mutation DuplicateRoleFunction($id: ID!) {
    duplicateRoleFunction(id: $id) { id key names { de en } }
  }
`)

export const deleteRoleFunctionMutation = graphql(`
  mutation DeleteRoleFunction($id: ID!) {
    deleteRoleFunction(id: $id) { id }
  }
`)

export const resetRoleFunctionMutation = graphql(`
  mutation ResetRoleFunction($id: ID!) {
    resetRoleFunction(id: $id) { id key names { de en } }
  }
`)
