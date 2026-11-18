import { graphql } from '~/graphql/generated'

/** The name of the project shown in the zen frame. */
export const zenProjectQuery = graphql(`
  query ZenProject($id: ID!) {
    project(id: $id) { id name }
  }
`)
