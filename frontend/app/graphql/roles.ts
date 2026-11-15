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
    }
  }
`)
