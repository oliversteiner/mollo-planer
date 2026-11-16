import { describe, expect, it } from 'vitest'

describe('/system/rechte (Fix F1)', () => {
  it('leads to the overview of the role page', () => {
    const route = useRouter().getRoutes().find(entry => entry.path === '/system/rechte')

    expect(route?.redirect).toEqual({ path: '/admin/roles', query: { view: 'matrix' } })
  })
})
