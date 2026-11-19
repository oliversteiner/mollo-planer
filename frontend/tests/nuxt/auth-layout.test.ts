import { mountSuspended } from '@nuxt/test-utils/runtime'
import { beforeAll, describe, expect, it } from 'vitest'
import AuthLayout from '~/layouts/auth.vue'

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

describe('layout auth', () => {
  it('has the logo as a link to the start page with an accessible name', async () => {
    const layout = await mountSuspended(AuthLayout, { route: '/login' })
    const link = layout.find('header a.auth-logo')

    expect(link.exists()).toBe(true)
    expect(link.attributes('href')).toBe('/')
    expect(link.attributes('aria-label')).toBe('Mollo Planer, zur Startseite')
    expect(link.find('svg.app-logo').exists()).toBe(true)
  })
})
