import { describe, expect, it } from 'vitest'
import { documentText } from '../../app/utils/rich-text'

describe('documentText', () => {
  it('puts the text of a document on one line, without formatting', () => {
    expect(documentText({
      type: 'doc',
      content: [
        { type: 'paragraph', content: [{ type: 'text', text: 'Fett', marks: [{ type: 'bold' }] }, { type: 'text', text: 'gedruckt' }] },
        { type: 'bulletList', content: [{ type: 'listItem', content: [{ type: 'paragraph', content: [{ type: 'text', text: 'Punkt' }] }] }] }
      ]
    })).toBe('Fettgedruckt Punkt')
  })

  it('is empty without a document', () => {
    expect(documentText(null)).toBe('')
  })
})
