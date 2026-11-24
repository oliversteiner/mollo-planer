import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import type { Page, Request } from '@playwright/test'
import { expect, test } from '@playwright/test'
import { SUGGESTED_ICONS } from '../app/utils/icon-search'
import { enterCode, freshTotp, login, PASSWORD, resetThrottles, TOTP_SECRET, unexpected, watch } from './support/helpers'

/**
 * The icon picker (Auftrag 36) in the four places where it is used: project settings, milestone, todo and task form.
 *
 * Two things in one file, both on the same flow (open the picker, type "sta", scroll to the bottom):
 * - the MEASUREMENT: every request to an icon path (/icons/...) is counted per place, in a fresh session. The numbers go to
 *   MESSUNG_DATEI (a file, not the repository), so the same file can be run once on origin/dev ("vorher") and once on the
 *   branch ("nachher"). The guard (the upper limit ICON_REQUEST_CAP) is checked after the numbers are written.
 * - the SCREENSHOTS in light and dark (SCREENSHOTS_DIR), for the pull request, never for the repository.
 * The tooltip test checks what the code of Picker.vue promises: it follows the focus, closes when the list scrolls, and Escape.
 */

/**
 * The most icon files the flow may ask for: the catalogue (one file), the suggestions shown before anything is typed, and
 * at most the 120 hits of the search. More than that means a search per keystroke or icons that are not on screen.
 */
const ICON_REQUEST_CAP = 1 + SUGGESTED_ICONS.length + 120

const MEASURE_FILE = process.env.MESSUNG_DATEI
const SCREENSHOT_DIR = process.env.SCREENSHOTS_DIR ?? join(tmpdir(), 'mollo-planer-icon-picker')

interface Measurement {
  place: string
  /** every request to an icon path, catalogue included */
  requests: number
  /** the different paths among them */
  unique: number
  /** the catalogue (catalog.json) */
  catalogue: number
}

/** The project "E2E Dokumente" of the seeder: its id is read from the list, the seeder itself does not fix it. */
async function projectId(page: Page): Promise<string> {
  await page.goto('/projects')
  await page.getByRole('link', { name: 'E2E Dokumente' }).first().click()
  await page.waitForURL(/\/projects\/\d+/)

  return /\/projects\/(\d+)/.exec(page.url())![1]!
}

async function signIn(page: Page): Promise<string> {
  resetThrottles()
  await login(page, 'admin@e2e.test', PASSWORD)
  await expect(page.getByRole('heading', { name: 'Zweiter Faktor' })).toBeVisible()
  await enterCode(page, await freshTotp(TOTP_SECRET))
  await expect(page).toHaveURL(/\/$/)

  return projectId(page)
}

const PLACES: { place: string, open: (page: Page, id: string) => Promise<void> }[] = [
  {
    place: 'Projekt',
    open: async (page, id) => {
      await page.goto(`/projects/${id}/settings`)
    }
  },
  {
    place: 'Milestone',
    open: async (page, id) => {
      await page.goto(`/projects/${id}`)
      await page.locator('wa-button', { hasText: 'Milestone anlegen' }).click()
    }
  },
  {
    place: 'ToDo',
    open: async (page, id) => {
      await page.goto(`/projects/${id}/todos`)
      await page.locator('wa-button', { hasText: 'ToDo anlegen' }).click()
    }
  },
  {
    place: 'Task',
    open: async (page, id) => {
      await page.goto(`/projects/${id}/kanban`)
      await page.locator('wa-button', { hasText: 'Task anlegen' }).click()
    }
  }
]

/** Opens the picker of the form that is open now. */
async function openPicker(page: Page): Promise<void> {
  await page.locator('wa-button[aria-label="Icon wählen"]').first().click()
  await expect(page.locator('.icon-picker-grid').first()).toBeVisible()
}

/** The search field of the picker: the one in the dialog that opened last (the page may have search fields of its own). */
function pickerSearch(page: Page) {
  return page.locator('wa-dialog[open]').last().locator('wa-input[type="search"]')
}

async function typeSearch(page: Page, text: string): Promise<void> {
  await pickerSearch(page).click()
  await page.keyboard.type(text, { delay: 60 })
}

async function screenshot(page: Page, name: string): Promise<void> {
  mkdirSync(SCREENSHOT_DIR, { recursive: true })
  await page.screenshot({ path: join(SCREENSHOT_DIR, `${name}.png`) })
}

for (const { place, open } of PLACES) {
  test(`icon picker in ${place}: requests for "sta" and scrolling to the bottom`, async ({ page }) => {
    // The first visit of a page compiles it in the development server (the kanban page took more than two minutes once).
    test.setTimeout(240_000)
    const problems = watch(page)
    const id = await signIn(page)
    await open(page, id)
    // The page of the form loads its own icons (the navigation); the count starts once it is still.
    await page.waitForTimeout(1500)

    const paths: string[] = []
    const onRequest = (request: Request) => {
      if (request.url().includes('/icons/')) {
        paths.push(request.url())
      }
    }
    page.on('request', onRequest)

    await openPicker(page)
    await typeSearch(page, 'sta')
    await expect(page.locator('.icon-picker-count').first()).toContainText('Treffer')
    await page.waitForTimeout(500)
    await page.locator('.icon-picker-grid').first().evaluate((grid) => {
      grid.scrollTop = grid.scrollHeight
    })
    await page.waitForTimeout(1500)
    page.off('request', onRequest)

    const measurement: Measurement = {
      place,
      requests: paths.length,
      unique: new Set(paths).size,
      catalogue: paths.filter(path => path.endsWith('catalog.json')).length
    }
    console.log(`ICON-MESSUNG ${JSON.stringify(measurement)}`)

    // Written into the file at once and merged with what is there: Playwright starts a new worker after a failed test,
    // so a list kept in memory would lose the places measured before.
    if (MEASURE_FILE) {
      const kept: Measurement[] = existsSync(MEASURE_FILE) ? JSON.parse(readFileSync(MEASURE_FILE, 'utf8')) : []
      writeFileSync(MEASURE_FILE, JSON.stringify([...kept.filter(item => item.place !== place), measurement], null, 2))
    }

    expect(unexpected(problems)).toEqual([])
    expect(measurement.requests, `icon requests in ${place} (cap ${ICON_REQUEST_CAP})`).toBeLessThanOrEqual(ICON_REQUEST_CAP)
  })
}

for (const scheme of ['light', 'dark'] as const) {
  test.describe(`screenshots in ${scheme}`, () => {
    test.use({ colorScheme: scheme })

    for (const { place, open } of PLACES) {
      test(`${place} ${scheme}`, async ({ page }) => {
        const id = await signIn(page)
        await open(page, id)
        await page.waitForTimeout(1000)
        await openPicker(page)
        await typeSearch(page, 'sta')
        await expect(page.locator('.icon-picker-count').first()).toContainText('Treffer')
        await page.waitForTimeout(800)
        await screenshot(page, `${place}-${scheme}`)
      })
    }
  })
}

test.describe('tooltip of the picker', () => {
  test('follows the focus, stays with its tile while the list scrolls, closes when the tile leaves the list, and Escape closes it', async ({ page }) => {
    const problems = watch(page)
    const id = await signIn(page)
    await openProjectSettings(page, id)
    await page.waitForTimeout(1000)
    await openPicker(page)
    // The suggestions fit into the list without scrolling: the search gives the 120 hits, which do.
    await typeSearch(page, 'sta')
    await expect(page.locator('.icon-picker-count').first()).toContainText('Treffer')
    await page.waitForTimeout(800)

    const focusedTile = () => page.evaluate(() => {
      const tile = document.activeElement?.closest<HTMLElement>('[data-icon]')

      return tile ? { icon: tile.dataset.icon, label: tile.dataset.label, id: tile.id } : null
    })
    const tip = () => page.evaluate(() => {
      const element = document.querySelector('wa-dialog[open] wa-tooltip') as (HTMLElement & { for: string | null, open: boolean }) | null

      return element ? { open: element.open, for: element.for, text: element.textContent?.trim() ?? '' } : null
    })

    // Tab until a tile has the focus, then one more Tab: the tooltip has to move with the focus.
    for (let step = 0; step < 15 && (await focusedTile()) === null; step += 1) {
      await page.keyboard.press('Tab')
    }
    const first = await focusedTile()
    expect(first, 'a tile can be reached with Tab').not.toBeNull()
    expect(await tip()).toMatchObject({ open: true, for: first!.id })
    expect((await tip())!.text).toContain(first!.label!)

    await page.keyboard.press('Tab')
    const second = await focusedTile()
    expect(second?.id).not.toBe(first!.id)
    expect(await tip()).toMatchObject({ open: true, for: second!.id })

    // Scrolling the list: the tooltip must not stay at the old place.
    await page.locator('.icon-picker-grid').first().evaluate((grid) => {
      grid.scrollTop = grid.scrollHeight
    })
    await page.waitForTimeout(300)
    expect(await tip()).toMatchObject({ open: false })

    // Escape with the tooltip open: only the tooltip closes, the picker stays open. A second Escape closes the picker.
    await page.keyboard.press('Tab')
    await expect.poll(async () => (await tip())?.open).toBe(true)
    await page.keyboard.press('Escape')
    await expect.poll(async () => (await tip())?.open).toBe(false)
    await expect(page.locator('.icon-picker-grid').first()).toBeVisible()
    await page.keyboard.press('Escape')
    await expect(page.locator('.icon-picker-grid').first()).toBeHidden()

    expect(unexpected(problems)).toEqual([])
  })
})

async function openProjectSettings(page: Page, id: string): Promise<void> {
  await PLACES[0]!.open(page, id)
}
