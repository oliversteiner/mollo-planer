import { describe, expect, it } from 'vitest'
import { resolveApiBase } from '../../app/utils/api-base'

describe('API base', () => {
  const page = { protocol: 'http:', hostname: '10.0.1.122' }

  it('a configured address wins, without a trailing slash', () => {
    expect(resolveApiBase('https://api.planer-test.example.com/', true, page)).toBe('https://api.planer-test.example.com')
    expect(resolveApiBase('http://localhost:8000', false)).toBe('http://localhost:8000')
  })

  it('development without an address uses the host of the page', () => {
    expect(resolveApiBase('', true, page)).toBe('http://10.0.1.122:8000')
    expect(resolveApiBase(undefined, true, { protocol: 'http:', hostname: 'localhost' })).toBe('http://localhost:8000')
  })

  it('a build without an address falls back to localhost', () => {
    expect(resolveApiBase('', false, page)).toBe('http://localhost:8000')
  })
})
