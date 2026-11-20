import Blockquote from '@tiptap/extension-blockquote'
import Code from '@tiptap/extension-code'
import Bold from '@tiptap/extension-bold'
import Document from '@tiptap/extension-document'
import HardBreak from '@tiptap/extension-hard-break'
import Heading from '@tiptap/extension-heading'
import Italic from '@tiptap/extension-italic'
import Link from '@tiptap/extension-link'
import CodeBlock from '@tiptap/extension-code-block'
import HorizontalRule from '@tiptap/extension-horizontal-rule'
import { BulletList, ListItem, ListKeymap, OrderedList, TaskItem, TaskList } from '@tiptap/extension-list'
import Strike from '@tiptap/extension-strike'
import { Table, TableCell, TableHeader, TableRow } from '@tiptap/extension-table'
import Paragraph from '@tiptap/extension-paragraph'
import Text from '@tiptap/extension-text'
import { Dropcursor, Gapcursor, UndoRedo } from '@tiptap/extensions'
import type { AnyExtension } from '@tiptap/core'

/**
 * The whitelist of rich text (descriptions and comments). It must match
 * the server: backend/app/Support/RichTextDocument.php. Anything else is
 * not in the editor schema, so pasted HTML loses it on the way in.
 */
export const RICH_TEXT_NODES = ['doc', 'paragraph', 'heading', 'bulletList', 'orderedList', 'listItem', 'blockquote', 'text', 'hardBreak'] as const
export const RICH_TEXT_MARKS = ['bold', 'italic', 'code', 'link'] as const
export const HEADING_LEVELS = [2, 3] as const

/** Which elements an editor or view allows: descriptions and comments, or whole documents (task 17, docs/dokumente-versuch.md §2). */
export type RichTextProfile = 'description' | 'document'

/** The profile "document" (server: backend/app/Support/DocumentContent.php). */
export const DOCUMENT_NODES = ['doc', 'paragraph', 'heading', 'bulletList', 'orderedList', 'listItem', 'taskList', 'taskItem', 'blockquote', 'codeBlock', 'horizontalRule', 'table', 'tableRow', 'tableHeader', 'tableCell', 'text', 'hardBreak'] as const
export const DOCUMENT_MARKS = ['bold', 'italic', 'strike', 'code', 'link'] as const
export const DOCUMENT_HEADING_LEVELS = [1, 2, 3, 4] as const

/** Same limit as RichTextDocument::MAX_TEXT_LENGTH. */
export const RICH_TEXT_MAX_LENGTH = 20000

/** Links only to web pages and e-mail addresses (server: LINK_SCHEMES). */
export function isAllowedLink(url: string): boolean {
  return /^(https?:\/\/[^\s]+|mailto:[^\s]+)$/i.test(url.trim())
}

/**
 * All extensions of the editor and of the read-only view: document,
 * paragraph, text, hard break and undo/redo, plus the allowed elements.
 * No StarterKit, so nothing the server refuses (code, rule, strike, ...)
 * ever gets into the schema.
 */
export function richTextExtensions(profile: RichTextProfile = 'description'): AnyExtension[] {
  const isDocument = profile === 'document'

  return [
    Document,
    Paragraph,
    Text,
    HardBreak,
    UndoRedo,
    Blockquote,
    Bold,
    Italic,
    Code,
    Heading.configure({ levels: isDocument ? [...DOCUMENT_HEADING_LEVELS] : [...HEADING_LEVELS] }),
    BulletList,
    OrderedList,
    ListItem,
    ListKeymap,
    Link.configure({
      openOnClick: false,
      autolink: true,
      linkOnPaste: true,
      defaultProtocol: 'https',
      HTMLAttributes: { rel: 'noopener noreferrer nofollow', target: '_blank' },
      isAllowedUri: url => isAllowedLink(url)
    }),
    Dropcursor.configure({ color: 'var(--wa-color-brand-fill-loud)', width: 2 }),
    Gapcursor,
    ...(isDocument ? documentExtensions() : [])
  ]
}

/**
 * What the profile "document" adds: strike-through, code block with a language, rule, task list that can nest,
 * and a table whose cells hold exactly one paragraph, with a fixed column width, no merging of cells and a head row
 * that cannot be switched off. The Markdown functions of the table package are not used: the PHP converter is the
 * one source of truth.
 */
function documentExtensions(): AnyExtension[] {
  return [
    Strike,
    CodeBlock,
    HorizontalRule,
    TaskList,
    TaskItem.configure({ nested: true }),
    Table.configure({ resizable: false }),
    TableRow,
    TableHeader.extend({ content: 'paragraph' }),
    TableCell.extend({ content: 'paragraph' })
  ]
}

/** A document is empty when it has no text (the server then stores null). */
export function isEmptyDocument(document: Record<string, unknown> | null | undefined): boolean {
  const hasText = (node: unknown): boolean => {
    if (!node || typeof node !== 'object') {
      return false
    }
    const { text, content } = node as { text?: string, content?: unknown[] }

    return Boolean(text?.trim()) || (Array.isArray(content) && content.some(hasText))
  }

  return !hasText(document)
}

/**
 * The text of a document on one line, for places that show a short
 * summary (the header of a project, its card): blocks are separated by a
 * space, formatting is left out.
 */
export function documentText(document: Record<string, unknown> | null | undefined): string {
  const inline = new Set(['paragraph', 'heading', 'codeBlock'])
  const textOf = (node: unknown): string => {
    if (!node || typeof node !== 'object') {
      return ''
    }
    const { type, text, content } = node as { type?: string, text?: string, content?: unknown[] }

    if (type === 'hardBreak') {
      return ' '
    }

    const parts = Array.isArray(content) ? content.map(textOf) : []

    return (text ?? '') + parts.join(inline.has(type ?? '') ? '' : ' ')
  }

  return textOf(document).replace(/\s+/g, ' ').trim()
}
