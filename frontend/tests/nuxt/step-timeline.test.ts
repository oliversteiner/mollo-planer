import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import StepTimeline from '~/components/StepTimeline.vue'

const items = [
  { value: 'a', title: 'Konzept', date: '31. Januar 2027', completed: true },
  { value: 'b', title: 'Umsetzung' }
]

describe('StepTimeline', () => {
  it('is a vertical stepper that is only a display (not clickable)', async () => {
    const timeline = await mountSuspended(StepTimeline, { props: { items, active: 'b', label: 'Milestones' } })
    const stepper = timeline.find('wa-stepper').element as unknown as { orientation: string, active: string, label: string, clickable: boolean }

    expect(stepper.orientation).toBe('vertical')
    expect(stepper.active).toBe('b')
    expect(stepper.label).toBe('Milestones')
    expect(stepper.clickable).toBeFalsy()
  })

  it('shows title and date, and puts marker and content into the slots of the step', async () => {
    const timeline = await mountSuspended(StepTimeline, {
      props: { items },
      slots: {
        indicator: '<b class="mark">!</b>',
        description: '<p class="content">Inhalt</p>'
      }
    })
    const [first, second] = timeline.findAll('wa-step')

    expect(first!.find('.timeline-title').text()).toBe('Konzept')
    expect(first!.find('.timeline-date').text()).toBe('31. Januar 2027')
    expect(second!.find('.timeline-date').exists()).toBe(false)
    expect((first!.element as unknown as { completed: boolean }).completed).toBe(true)
    expect(first!.find('[slot="icon"] .mark').exists()).toBe(true)
    expect(first!.find('[slot="description"] .content').exists()).toBe(true)
  })

  it('has no marker or content slot without those slots', async () => {
    const timeline = await mountSuspended(StepTimeline, { props: { items } })

    expect(timeline.find('[slot="icon"]').exists()).toBe(false)
    expect(timeline.find('[slot="description"]').exists()).toBe(false)
  })
})
