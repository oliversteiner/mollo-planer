import { describe, expect, it } from 'vitest'
import { AI_CONTEXT_LIMIT, contextCharacters, fileProblem } from '../../app/utils/ai-context'

describe('AI context', () => {
  it('counts text and file together, by characters not bytes', () => {
    expect(contextCharacters('äö', 'ü😀')).toBe(4)
    expect(contextCharacters('abc', null)).toBe(3)
    expect(AI_CONTEXT_LIMIT).toBe(20_000)
  })

  it('takes .txt and .md up to 100 KB only', () => {
    expect(fileProblem({ name: 'Wissen.MD', size: 10 })).toBeNull()
    expect(fileProblem({ name: 'a.pdf', size: 10 })).toBe('type')
    expect(fileProblem({ name: 'a.txt', size: 100 * 1024 + 1 })).toBe('size')
  })
})
