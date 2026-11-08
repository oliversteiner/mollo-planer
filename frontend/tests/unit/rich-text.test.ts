// @vitest-environment happy-dom
import { Editor } from '@tiptap/core'
import Document from '@tiptap/extension-document'
import HardBreak from '@tiptap/extension-hard-break'
import Paragraph from '@tiptap/extension-paragraph'
import Text from '@tiptap/extension-text'
import { UndoRedo } from '@tiptap/extensions'
import { afterEach, describe, expect, it } from 'vitest'
import { isAllowedLink, isEmptyDocument, RICH_TEXT_MARKS, RICH_TEXT_NODES, richTextExtensions } from '../../app/utils/rich-text'

/** The editor as UEditor builds it with starterKit: false plus our extensions. */
function makeEditor(content: string | Record<string, unknown> = ''): Editor {
  return new Editor({ extensions: [Document, Paragraph, Text, HardBreak, UndoRedo, ...richTextExtensions()], content })
}

let editor: Editor | null = null

afterEach(() => {
  editor?.destroy()
  editor = null
})

/** All node and mark types used in a TipTap JSON document. */
function typesIn(node: { type?: string, content?: unknown[], marks?: { type: string }[] }, found = new Set<string>()): Set<string> {
  if (node.type) {
    found.add(node.type)
  }
  node.marks?.forEach(mark => found.add(mark.type))
  node.content?.forEach(child => typesIn(child as typeof node, found))

  return found
}

describe('rich text editor', () => {
  it('has exactly the node and mark types the server allows', () => {
    editor = makeEditor()

    expect(Object.keys(editor.schema.nodes).sort()).toEqual([...RICH_TEXT_NODES].sort())
    expect(Object.keys(editor.schema.marks).sort()).toEqual([...RICH_TEXT_MARKS].sort())
  })

  it('drops what is not allowed when HTML is pasted or loaded', () => {
    editor = makeEditor(`
      <h1>Gross</h1><h2>Ebene 2</h2><h4>Ebene 4</h4>
      <p><strong>fett</strong> <em>kursiv</em> <s>durch</s> <u>unter</u> <code>code</code></p>
      <hr><pre><code>block</code></pre><img src="x.png">
      <ul><li><p>Punkt</p></li></ul>
      <p><a href="javascript:alert(1)">böse</a> <a href="https://fcadb.ch">gut</a> <a href="mailto:a@b.ch">Mail</a></p>
    `)
    const json = editor.getJSON()
    const allowed = new Set<string>([...RICH_TEXT_NODES, ...RICH_TEXT_MARKS])

    expect([...typesIn(json)].filter(type => !allowed.has(type))).toEqual([])
    expect(JSON.stringify(json)).not.toContain('javascript:')
    expect(JSON.stringify(json)).toContain('https://fcadb.ch')
    expect(JSON.stringify(json)).toContain('mailto:a@b.ch')

    const levels = (json.content ?? []).filter(node => node.type === 'heading').map(node => node.attrs?.level)
    expect(levels.every(level => level === 2 || level === 3)).toBe(true)
  })
})

describe('isAllowedLink', () => {
  it('accepts web and mail links only', () => {
    expect(isAllowedLink('https://fcadb.ch/seite')).toBe(true)
    expect(isAllowedLink('http://localhost:3000')).toBe(true)
    expect(isAllowedLink('mailto:info@fcadb.ch')).toBe(true)

    for (const url of ['javascript:alert(1)', 'ftp://x', 'data:text/html,x', 'fcadb.ch', 'https://', 'https://a b']) {
      expect(isAllowedLink(url)).toBe(false)
    }
  })
})

describe('isEmptyDocument', () => {
  it('sees a document without text as empty', () => {
    expect(isEmptyDocument(null)).toBe(true)
    expect(isEmptyDocument({ type: 'doc', content: [{ type: 'paragraph' }] })).toBe(true)
    expect(isEmptyDocument({ type: 'doc', content: [{ type: 'paragraph', content: [{ type: 'text', text: '  ' }] }] })).toBe(true)
    expect(isEmptyDocument({ type: 'doc', content: [{ type: 'paragraph', content: [{ type: 'text', text: 'Hallo' }] }] })).toBe(false)
  })
})
