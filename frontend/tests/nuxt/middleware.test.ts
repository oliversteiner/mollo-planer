import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { RouteLocationNormalized } from 'vue-router'
import adminMiddleware from '~/middleware/admin'
import authMiddleware from '~/middleware/auth.global'

const { viewerState, abilities, load, redirect, abort } = vi.hoisted(() => ({
  viewerState: { value: null as null | { role: string } },
  abilities: { value: [] as string[] },
  load: vi.fn(),
  redirect: vi.fn((to: unknown) => ({ redirectedTo: to })),
  abort: vi.fn((error: unknown) => ({ aborted: error }))
}))

mockNuxtImport('useViewer', () => () => ({ viewer: viewerState, load, can: (ability: string) => abilities.value.includes(ability) }))
mockNuxtImport('navigateTo', () => redirect)
mockNuxtImport('abortNavigation', () => abort)

function route(path: string, meta: Record<string, unknown> = {}): RouteLocationNormalized {
  return { path, fullPath: path, meta } as unknown as RouteLocationNormalized
}

beforeEach(() => {
  viewerState.value = null
  abilities.value = []
  vi.clearAllMocks()
})

describe('auth middleware', () => {
  it('lets public pages through without asking the API', async () => {
    expect(await authMiddleware(route('/login', { public: true }), route('/'))).toBeUndefined()
    expect(load).not.toHaveBeenCalled()
  })

  it('asks the API once and lets a logged-in viewer through', async () => {
    load.mockResolvedValue(null)

    expect(await authMiddleware(route('/projects/1'), route('/'))).toBeUndefined()
    expect(load).toHaveBeenCalledOnce()

    viewerState.value = { role: 'MITGLIED' }
    load.mockClear()
    await authMiddleware(route('/projects/2'), route('/'))
    expect(load).not.toHaveBeenCalled()
  })

  it('sends people who are not logged in to the login, with the target', async () => {
    load.mockResolvedValue('unauthenticated')

    expect(await authMiddleware(route('/projects/1/kanban?task=2'), route('/')))
      .toEqual({ redirectedTo: { path: '/login', query: { redirect: '/projects/1/kanban?task=2' } } })
  })

  it('sends a session that only used the password to the login again', async () => {
    load.mockResolvedValue('reauth-required')

    expect(await authMiddleware(route('/'), route('/'))).toEqual({ redirectedTo: { path: '/login', query: { redirect: '/' } } })
  })

  it('leads accounts without a second factor to the setup, and lets the setup page open', async () => {
    load.mockResolvedValue('setup-required')

    expect(await authMiddleware(route('/projects'), route('/'))).toEqual({ redirectedTo: '/setup-security' })
    expect(await authMiddleware(route('/setup-security'), route('/'))).toBeUndefined()
  })

  it('shows an error page when the backend cannot be reached', async () => {
    load.mockResolvedValue('network')

    const result = await authMiddleware(route('/'), route('/')) as { aborted: { statusCode: number } }
    expect(result.aborted.statusCode).toBe(503)
  })
})

describe('admin middleware', () => {
  it('lets admins in and sends everyone else to the dashboard', () => {
    abilities.value = ['USERS_MANAGE']
    expect(adminMiddleware(route('/admin/users'), route('/'))).toBeUndefined()

    abilities.value = ['PROJECTS_CREATE']
    expect(adminMiddleware(route('/admin/users'), route('/'))).toEqual({ redirectedTo: '/' })
  })
})
