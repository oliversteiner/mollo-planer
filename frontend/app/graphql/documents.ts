import { graphql } from '~/graphql/generated'

/** The documents of a project, newest change first: no content (the list does not load it). */
export const documentsQuery = graphql(`
  query Documents($projectId: ID!, $kind: DocumentKind, $search: String) {
    documents(projectId: $projectId, kind: $kind, search: $search) {
      id
      title
      kind
      projectAccess
      viewerAccess
      revision
      updatedAt
      owner { id name avatarUrl isAgent }
      updatedBy { id name }
    }
  }
`)

/** One document with its content (Markdown and TipTap JSON) and what the person may do with it. */
export const documentQuery = graphql(`
  query Document($id: ID!) {
    document(id: $id) {
      id
      title
      kind
      content
      contentJson
      projectAccess
      viewerAccess
      revision
      createdAt
      updatedAt
      deletionId
      project { id name }
      owner { id name avatarUrl isAgent }
      updatedBy { id name }
      shares { user { id name avatarUrl isAgent } access }
    }
  }
`)

export const createDocumentMutation = graphql(`
  mutation CreateDocument($input: CreateDocumentInput!) {
    createDocument(input: $input) { id revision }
  }
`)

export const updateDocumentMutation = graphql(`
  mutation UpdateDocument($id: ID!, $revision: Int!, $input: UpdateDocumentInput!) {
    updateDocument(id: $id, revision: $revision, input: $input) {
      id
      title
      kind
      content
      contentJson
      revision
      updatedAt
      updatedBy { id name }
    }
  }
`)

export const deleteDocumentMutation = graphql(`
  mutation DeleteDocument($id: ID!) {
    deleteDocument(id: $id) { id deletionId }
  }
`)

export const setDocumentSharingMutation = graphql(`
  mutation SetDocumentSharing($id: ID!, $input: DocumentSharingInput!) {
    setDocumentSharing(id: $id, input: $input) {
      id
      projectAccess
      viewerAccess
      shares { user { id name avatarUrl isAgent } access }
    }
  }
`)

export const transferDocumentMutation = graphql(`
  mutation TransferDocument($id: ID!, $ownerId: ID!) {
    transferDocument(id: $id, ownerId: $ownerId) {
      id
      viewerAccess
      owner { id name avatarUrl isAgent }
    }
  }
`)

export const exportDocumentPdfMutation = graphql(`
  mutation ExportDocumentPdf($id: ID!, $withLetterhead: Boolean) {
    exportDocumentPdf(id: $id, withLetterhead: $withLetterhead) { fileName mimeType contentBase64 }
  }
`)

/** Is a letterhead uploaded (Auftrag 39 F)? Then the export offers the PDF with it. */
export const letterheadAvailableQuery = graphql(`
  query LetterheadAvailable {
    letterheadAvailable
  }
`)

/** The saved versions of a document, newest first, without content. */
export const documentVersionsQuery = graphql(`
  query DocumentVersions($id: ID!) {
    document(id: $id) {
      id
      title
      revision
      viewerAccess
      versions {
        revision
        title
        kind
        restoredFrom
        createdAt
        createdBy { id name }
      }
    }
  }
`)

export const documentVersionQuery = graphql(`
  query DocumentVersion($documentId: ID!, $revision: Int!) {
    documentVersion(documentId: $documentId, revision: $revision) {
      revision
      title
      content
    }
  }
`)

export const restoreDocumentVersionMutation = graphql(`
  mutation RestoreDocumentVersion($id: ID!, $revision: Int!) {
    restoreDocumentVersion(id: $id, revision: $revision) { id revision }
  }
`)
