import { graphql } from '~/graphql/generated'

/** The rights table (admins only): which role has which right. */
export const permissionMatrixQuery = graphql(`
  query PermissionMatrix {
    permissionMatrix {
      system { right roles { key } }
      project { right roles { key } }
    }
  }
`)
