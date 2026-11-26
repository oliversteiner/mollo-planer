import { graphql } from '~/graphql/generated'

/** Billing (Auftrag 39): customers, sender, invoices, PDF frame. Everything needs billing.manage. */

export const customersQuery = graphql(`
  query BillingCustomers($archived: Boolean) {
    customers(archived: $archived) {
      id company name street houseNumber postalCode city country email locale notes archived
      projects { id name }
    }
  }
`)

export const customerQuery = graphql(`
  query BillingCustomer($id: ID!) {
    customer(id: $id) {
      id company name street houseNumber postalCode city country email locale notes archived
      projects { id name archivedAt }
    }
  }
`)

export const createCustomerMutation = graphql(`
  mutation CreateCustomer($input: CustomerInput!) {
    createCustomer(input: $input) { id company name }
  }
`)

export const updateCustomerMutation = graphql(`
  mutation UpdateCustomer($id: ID!, $input: CustomerInput!) {
    updateCustomer(id: $id, input: $input) {
      id company name street houseNumber postalCode city country email locale notes archived
    }
  }
`)

export const archiveCustomerMutation = graphql(`
  mutation ArchiveCustomer($id: ID!, $archived: Boolean!) {
    archiveCustomer(id: $id, archived: $archived) { id archived }
  }
`)

export const billingSettingsQuery = graphql(`
  query BillingSettings {
    billingSettings {
      name street houseNumber postalCode city country email phone website iban uid paymentTermsDays numberFormat missingFields
    }
  }
`)

export const updateBillingSettingsMutation = graphql(`
  mutation UpdateBillingSettings($input: BillingSettingsInput!) {
    updateBillingSettings(input: $input) {
      name street houseNumber postalCode city country email phone website iban uid paymentTermsDays numberFormat missingFields
    }
  }
`)

export const billingOverviewQuery = graphql(`
  query BillingOverview {
    billingOverview {
      groups {
        customer { id company name }
        totalCents
        projects { projectId projectName totalCents milestones { id title priceCents dueDate releaseVersion } }
      }
      withoutCustomer { projectId projectName milestoneId milestoneTitle }
      withoutPrice { projectId projectName milestoneId milestoneTitle }
    }
  }
`)

export const invoicesQuery = graphql(`
  query BillingInvoices($status: InvoiceStatus, $customerId: ID, $year: Int) {
    invoices(status: $status, customerId: $customerId, year: $year) {
      id number status overdue issueDate dueDate totalCents
      customer { id company name }
    }
    invoiceTotals { openCount openCents overdueCount overdueCents }
  }
`)

export const invoiceQuery = graphql(`
  query BillingInvoice($id: ID!) {
    invoice(id: $id) {
      id number status overdue issueDate dueDate totalCents reference locale notes hasPdf
      paidAt cancelledAt cancelReason issuedAt
      customer { id company name street houseNumber postalCode city country }
      recipient { company name street houseNumber postalCode city country }
      items { id projectId projectName milestoneId title description amountCents }
    }
  }
`)

export const createInvoiceDraftMutation = graphql(`
  mutation CreateInvoiceDraft($customerId: ID!, $milestoneIds: [ID!]!) {
    createInvoiceDraft(customerId: $customerId, milestoneIds: $milestoneIds) { id }
  }
`)

export const updateInvoiceDraftMutation = graphql(`
  mutation UpdateInvoiceDraft($id: ID!, $notes: String, $items: [InvoiceItemInput!]) {
    updateInvoiceDraft(id: $id, notes: $notes, items: $items) {
      id totalCents notes
      items { id projectId projectName milestoneId title description amountCents }
    }
  }
`)

export const deleteInvoiceDraftMutation = graphql(`
  mutation DeleteInvoiceDraft($id: ID!) {
    deleteInvoiceDraft(id: $id)
  }
`)

export const issueInvoiceMutation = graphql(`
  mutation IssueInvoice($id: ID!) {
    issueInvoice(id: $id) { id number status issueDate dueDate hasPdf }
  }
`)

export const markInvoicePaidMutation = graphql(`
  mutation MarkInvoicePaid($id: ID!, $paidAt: Date!) {
    markInvoicePaid(id: $id, paidAt: $paidAt) { id status paidAt overdue }
  }
`)

export const cancelInvoiceMutation = graphql(`
  mutation CancelInvoice($id: ID!, $reason: String!) {
    cancelInvoice(id: $id, reason: $reason) { id status cancelledAt cancelReason }
  }
`)

export const exportInvoicePdfMutation = graphql(`
  mutation ExportInvoicePdf($id: ID!) {
    exportInvoicePdf(id: $id) { fileName mimeType contentBase64 }
  }
`)

export const previewInvoicePdfMutation = graphql(`
  mutation PreviewInvoicePdf($id: ID!) {
    previewInvoicePdf(id: $id) { fileName mimeType contentBase64 }
  }
`)

export const pdfFrameQuery = graphql(`
  query PdfFrame {
    pdfFrame {
      letterhead { fileName pages uploadedAt }
      layout { topFirst topFollowing bottom left right addressLeft addressTop addressWidth addressHeight }
      defaults { topFirst topFollowing bottom left right addressLeft addressTop addressWidth addressHeight }
      font
      fonts { key name }
      addressWindow
    }
  }
`)

export const uploadLetterheadMutation = graphql(`
  mutation UploadLetterhead($file: Upload!) {
    uploadLetterhead(file: $file) { letterhead { fileName pages uploadedAt } }
  }
`)

export const removeLetterheadMutation = graphql(`
  mutation RemoveLetterhead {
    removeLetterhead { letterhead { fileName } }
  }
`)

export const updatePdfFrameMutation = graphql(`
  mutation UpdatePdfFrame($input: PdfFrameInput!) {
    updatePdfFrame(input: $input) {
      layout { topFirst topFollowing bottom left right addressLeft addressTop addressWidth addressHeight }
      font
      addressWindow
    }
  }
`)

export const sampleInvoicePdfMutation = graphql(`
  mutation SampleInvoicePdf($locale: Locale) {
    sampleInvoicePdf(locale: $locale) { fileName mimeType contentBase64 }
  }
`)

export const setProjectCustomerMutation = graphql(`
  mutation SetProjectCustomer($projectId: ID!, $customerId: ID) {
    setProjectCustomer(projectId: $projectId, customerId: $customerId) { id customer { id company name } }
  }
`)

export const setMilestonePriceMutation = graphql(`
  mutation SetMilestonePrice($milestoneId: ID!, $priceCents: Int) {
    setMilestonePrice(milestoneId: $milestoneId, priceCents: $priceCents) { id priceCents }
  }
`)
