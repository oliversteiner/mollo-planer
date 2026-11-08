import type { CodegenConfig } from '@graphql-codegen/cli'

/**
 * Types for every GraphQL operation, from the compiled backend schema
 * (graphql/schema.graphql, written by "composer schema:frontend" in
 * backend/). Operations are written with graphql(`...`) from
 * ~/graphql/generated.
 */
const config: CodegenConfig = {
  schema: 'graphql/schema.graphql',
  documents: ['app/**/*.{ts,vue}', '!app/graphql/generated/**'],
  ignoreNoDocuments: true,
  generates: {
    'app/graphql/generated/': {
      preset: 'client',
      presetConfig: {
        fragmentMasking: false
      },
      config: {
        useTypeImports: true,
        enumsAsTypes: true,
        scalars: {
          ID: { input: 'string | number', output: 'string' },
          Date: 'string',
          DateTime: 'string',
          RichText: 'Record<string, unknown>',
          Upload: 'File'
        }
      }
    }
  }
}

export default config
