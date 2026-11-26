import { mountSuspended } from '@nuxt/test-utils/runtime'
import { beforeAll, describe, expect, it } from 'vitest'
import VersionOrderDialog from '~/components/project/VersionOrderDialog.vue'

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

const plan = {
  revision: 12,
  canApply: true,
  condition: null,
  moveCount: 2,
  conditionMilestones: [],
  order: [
    { id: '2', title: 'Erste Folge online', version: '1.0', from: 1, to: 0, moved: true },
    { id: '1', title: 'Konzept steht', version: '1.1', from: 0, to: 1, moved: true },
    { id: '3', title: 'Backlog', version: null, from: 2, to: 2, moved: false }
  ]
}

describe('VersionOrderDialog (Auftrag 47 C)', () => {
  it('shows the new order, marks the moved milestones and says how many move', async () => {
    const dialog = await mountSuspended(VersionOrderDialog, { props: { open: true, plan } })

    expect(dialog.text()).toContain('2 Milestones werden verschoben.')
    expect(dialog.findAll('.order-row').map(row => row.text().replace(/\s+/g, ' '))).toEqual([
      'M1Erste Folge online1.0 war an Stelle 2',
      'M2Konzept steht1.1 war an Stelle 1',
      'M3BacklogOhne Version'
    ])
    expect(dialog.findAll('.order-row-moved')).toHaveLength(2)
  })

  it('says «Milestone» in the singular for one move', async () => {
    const dialog = await mountSuspended(VersionOrderDialog, { props: { open: true, plan: { ...plan, moveCount: 1 } } })

    expect(dialog.text()).toContain('1 Milestone wird verschoben.')
  })

  it('applies with «Neu ordnen», only when every condition is met; «Abbrechen» closes', async () => {
    const dialog = await mountSuspended(VersionOrderDialog, { props: { open: true, plan } })
    const buttons = dialog.findAll('wa-button')

    await buttons.find(button => button.text() === 'Neu ordnen')!.trigger('click')
    await buttons.find(button => button.text() === 'Abbrechen')!.trigger('click')

    expect(dialog.emitted('apply')).toHaveLength(1)
    expect(dialog.emitted('update:open')).toEqual([[false]])
  })

  it('turns «Neu ordnen» off when a condition is not met', async () => {
    const dialog = await mountSuspended(VersionOrderDialog, { props: { open: true, plan: { ...plan, canApply: false } } })
    const apply = dialog.findAll('wa-button').find(button => button.text() === 'Neu ordnen')!

    expect(apply.element.hasAttribute('disabled') || (apply.element as HTMLElement & { disabled?: boolean }).disabled === true).toBe(true)
  })
})
