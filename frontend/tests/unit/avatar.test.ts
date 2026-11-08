import { describe, expect, it } from 'vitest'
import { AVATAR_MAX_BYTES, avatarProblem } from '../../app/utils/avatar'

describe('avatarProblem', () => {
  it('accepts JPG and PNG up to exactly 5 MB', () => {
    expect(avatarProblem({ type: 'image/jpeg', size: AVATAR_MAX_BYTES })).toBeNull()
    expect(avatarProblem({ type: 'image/png', size: 1000 })).toBeNull()
  })

  it('rejects other types and larger files', () => {
    expect(avatarProblem({ type: 'image/webp', size: 1000 })).toBe('type')
    expect(avatarProblem({ type: 'image/gif', size: 1000 })).toBe('type')
    expect(avatarProblem({ type: 'image/png', size: AVATAR_MAX_BYTES + 1 })).toBe('size')
  })
})
