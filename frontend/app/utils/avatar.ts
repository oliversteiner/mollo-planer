/** Same limits as the backend (App\Services\UserAvatar). */
export const AVATAR_MAX_BYTES = 5 * 1024 * 1024
export const AVATAR_TYPES = ['image/jpeg', 'image/png'] as const

/** Why a file cannot be an avatar, checked before uploading; null if fine. */
export function avatarProblem(file: { type: string, size: number }): 'type' | 'size' | null {
  if (!(AVATAR_TYPES as readonly string[]).includes(file.type)) {
    return 'type'
  }

  return file.size > AVATAR_MAX_BYTES ? 'size' : null
}
