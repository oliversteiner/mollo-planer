import { mountSuspended } from '@nuxt/test-utils/runtime'
import { ref } from 'vue'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import DefaultLayout from '~/layouts/default.vue'

vi.mock('@vue/apollo-composable', () => ({
  useQuery: () => ({ result: ref({ projects: [], trash: [] }), loading: ref(false) }),
  useMutation: () => ({ mutate: vi.fn(), loading: ref(false) })
}))

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

describe('layout default: the band of the test environment', () => {
  it('shows "Testumgebung" when the backend is not production', async () => {
    useState('testEnvironment').value = true
    const layout = await mountSuspended(DefaultLayout, { route: '/login' })
    const badge = layout.find('wa-badge.app-test-badge')

    expect(badge.exists()).toBe(true)
    expect(badge.text()).toBe('Testumgebung')
  })

  it('shows nothing in production', async () => {
    useState('testEnvironment').value = false
    const layout = await mountSuspended(DefaultLayout, { route: '/login' })

    expect(layout.find('wa-badge.app-test-badge').exists()).toBe(false)
  })
})
