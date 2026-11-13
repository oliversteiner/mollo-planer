import Blockquote from '@tiptap/extension-blockquote'
import Code from '@tiptap/extension-code'
import Bold from '@tiptap/extension-bold'
import Document from '@tiptap/extension-document'
import HardBreak from '@tiptap/extension-hard-break'
import Heading from '@tiptap/extension-heading'
import Italic from '@tiptap/extension-italic'
import Link from '@tiptap/extension-link'
import { BulletList, ListItem, ListKeymap, OrderedList } from '@tiptap/extension-list'
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
export function richTextExtensions(): AnyExtension[] {
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
    Heading.configure({ levels: [...HEADING_LEVELS] }),
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
    Gapcursor
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
