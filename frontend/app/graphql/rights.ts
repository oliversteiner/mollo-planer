import { graphql } from '~/graphql/generated'

/** The rights table (admins only): which role has which ability. */
export const permissionMatrixQuery = graphql(`
  query PermissionMatrix {
    permissionMatrix {
      system { ability roles }
      project { ability roles }
    }
  }
`)
