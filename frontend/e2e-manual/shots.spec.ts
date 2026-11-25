import { mkdirSync, readdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { expect, test } from '@playwright/test'
import type { Browser, BrowserContext, Page } from '@playwright/test'
import { enterCode, freshTotp, login, PASSWORD, resetThrottles, TOTP_SECRET } from '../e2e/support/helpers'
import type { Recording } from './recording'

/**
 * Takes the pictures of the manual (Auftrag 54 F) from the recording files in handbuch/aufnahmen/: each picture in German
 * (de-CH) and English, light mode, 1280 × 800, no animations, after the page has stopped loading. Writes
 * handbuch/bilder/auto/<language>/<chapter>/<name>.webp (at most 1280 px wide) and rewrites handbuch/bilder/pruefen.md
 * with every picture it has a doubt about. Never writes into handbuch/bilder/eigen/.
 */
const ROOT = join(dirname(fileURLToPath(import.meta.url)), '../..')
const RECORDINGS = join(ROOT, 'handbuch/aufnahmen')
const AUTO = join(ROOT, 'handbuch/bilder/auto')
const MAX_BYTES = 300 * 1024
const LANGUAGES = ['de', 'en'] as const

const doubts: string[] = []
const sessions = new Map<string, Awaited<ReturnType<BrowserContext['storageState']>>>()
const run = Date.now().toString(36)

function messages(lang: string): Record<string, unknown> {
  return JSON.parse(readFileSync(join(ROOT, `frontend/i18n/locales/${lang}.json`), 'utf8')) as Record<string, unknown>
}

function translator(lang: string) {
  const all = messages(lang)

  return (key: string): string => {
    const value = key.split('.').reduce<unknown>((node, part) => (node && typeof node === 'object' ? (node as Record<string, unknown>)[part] : undefined), all)

    if (typeof value !== 'string') {
      throw new Error(`No text for ${key} in ${lang}.json`)
    }

    return value
  }
}

/** A signed-in admin in the language, once per language. */
async function context(browser: Browser, lang: 'de' | 'en'): Promise<BrowserContext> {
  const options = { viewport: { width: 1280, height: 800 }, colorScheme: 'light' as const, reducedMotion: 'reduce' as const }

  // Signed in once, in German (the helpers of the browser tests click the German buttons of the sign-in).
  if (!sessions.has('admin')) {
    const signing = await browser.newContext({ ...options, locale: 'de-CH' })
    await signing.addCookies([{ name: 'locale', value: 'de', domain: 'localhost', path: '/' }])
    const page = await signing.newPage()
    resetThrottles()
    await login(page, 'admin@e2e.test', PASSWORD)
    await page.locator('wa-otp-input').waitFor()
    await enterCode(page, await freshTotp(TOTP_SECRET))
    await expect(page).toHaveURL(/\/$/)
    sessions.set('admin', await signing.storageState())
    await signing.close()
  }

  // The language of the app comes from the cookie «locale» (nuxt.config.ts, i18n).
  const context = await browser.newContext({ ...options, locale: lang === 'de' ? 'de-CH' : 'en-GB', timezoneId: lang === 'de' ? 'Europe/Zurich' : 'Europe/London', storageState: sessions.get('admin') })
  await context.addCookies([{ name: 'locale', value: lang, domain: 'localhost', path: '/' }])

  return context
}

/** PNG to WebP in the browser (no extra package), at most 1280 px wide and at most 300 KB if the quality allows. */
async function toWebp(page: Page, png: Buffer): Promise<Buffer> {
  const dataUrl = await page.evaluate(async ({ base64, maxBytes }) => {
    const image = await createImageBitmap(await (await fetch(`data:image/png;base64,${base64}`)).blob())
    const scale = Math.min(1, 1280 / image.width)
    const canvas = document.createElement('canvas')
    canvas.width = Math.round(image.width * scale)
    canvas.height = Math.round(image.height * scale)
    canvas.getContext('2d')!.drawImage(image, 0, 0, canvas.width, canvas.height)
    let quality = 0.85
    let url = canvas.toDataURL('image/webp', quality)

    while (url.length * 0.75 > maxBytes && quality > 0.4) {
      quality -= 0.1
      url = canvas.toDataURL('image/webp', quality)
    }

    return url
  }, { base64: png.toString('base64'), maxBytes: MAX_BYTES })

  return Buffer.from(dataUrl.split(',')[1]!, 'base64')
}

const recordings = readdirSync(RECORDINGS).filter(file => file.endsWith('.mts')).sort()

for (const file of recordings) {
  test.describe(file, () => {
    for (const lang of LANGUAGES) {
      test(`${file} ${lang}`, async ({ browser }) => {
        const recording = (await import(join(RECORDINGS, file))).default as Recording
        const ctx = await context(browser, lang)
        const page = await ctx.newPage()
        const t = translator(lang)

        let failed: string | null = null

        for (const shot of recording.shots) {
          const where = `${recording.chapter}/${shot.name} (${lang})`

          // The shots of a chapter build on each other: after a failure the rest is not taken.
          if (failed) {
            doubts.push(`- ${where}: not taken (${failed} failed before)`)
            continue
          }

          try {
            const part = await shot.take(page, { lang, t, unique: label => `${label} ${run}` })
            await page.waitForLoadState('networkidle').catch(() => doubts.push(`- ${where}: the page did not stop loading`))
            await page.evaluate(() => document.fonts.ready)
            // Dialogs fade in even with reduced motion: wait until every animation has finished.
            await page.evaluate(() => Promise.all(document.getAnimations().map(animation => animation.finished.catch(() => undefined))))
            // An open dialog: until its panel is fully opaque (its fade is not always an animation the page can see).
            await page.waitForFunction(() => {
              const dialog = document.querySelector('wa-dialog[open]')
              const panel = dialog?.shadowRoot?.querySelector('[part~="dialog"]') ?? dialog?.shadowRoot?.querySelector('dialog')

              return !panel || getComputedStyle(panel).opacity === '1'
            }, undefined, { timeout: 5000 }).catch(() => doubts.push(`- ${where}: the dialog did not become fully visible`))
            await page.waitForTimeout(300)

            const loading = await page.evaluate(() => [...document.querySelectorAll('wa-skeleton, wa-spinner')]
              .filter((element) => {
                const box = element.getBoundingClientRect()
                const style = getComputedStyle(element)

                return box.width > 0 && box.height > 0 && box.bottom > 0 && box.top < innerHeight && style.visibility !== 'hidden' && style.opacity !== '0'
              })
              .map(element => `${element.tagName.toLowerCase()} in ${element.parentElement?.className || element.parentElement?.tagName.toLowerCase() || '?'}`))

            if (loading.length > 0) {
              doubts.push(`- ${where}: a loading indicator is still visible (${loading.join(', ')})`)
            }

            // A part is taken by its box (the host elements of Web Awesome have no box of their own, and the stability
            // check of an element picture waits for animations): a little air around it, inside the window.
            const box = part ? await part.boundingBox() : null
            const png = box
              ? await page.screenshot({
                  clip: {
                    x: Math.max(0, box.x - 12),
                    y: Math.max(0, box.y - 12),
                    width: Math.min((page.viewportSize()?.width ?? 1280) - Math.max(0, box.x - 12), box.width + 24),
                    height: Math.min((page.viewportSize()?.height ?? 800) - Math.max(0, box.y - 12), box.height + 24)
                  }
                })
              : await page.screenshot()
            if (part && !box) {
              doubts.push(`- ${where}: the part has no box, the whole window was taken`)
            }
            const webp = await toWebp(page, png)
            const target = join(AUTO, lang, recording.chapter, `${shot.name}.webp`)
            mkdirSync(dirname(target), { recursive: true })
            writeFileSync(target, webp)

            if (webp.length > MAX_BYTES) {
              doubts.push(`- ${where}: larger than 300 KB (${Math.round(webp.length / 1024)} KB)`)
            }

            if (webp.length < 2 * 1024) {
              doubts.push(`- ${where}: almost empty (${webp.length} bytes)`)
            }
          } catch (error) {
            failed = shot.name
            await page.screenshot({ path: join(ROOT, `frontend/test-results/manual-${recording.chapter}-${shot.name}-${lang}.png`) }).catch(() => undefined)
            doubts.push(`- ${where}: not taken (${(error as Error).message.split('\n')[0]})`)
          }
        }

        await ctx.close()
      })
    }
  })
}

test.afterAll(() => {
  const lines = [
    '# Bilder prüfen',
    '',
    'Von `npm run manual:shots` geschrieben (Auftrag 54 F): Aufnahmen, bei denen das Skript Zweifel hat. Ein solches Bild bei Bedarf durch ein eigenes ersetzen (`handbuch/bilder/eigen/`, siehe `handbuch/README.md`).',
    '',
    ...(doubts.length ? doubts : ['Keine Zweifel beim letzten Lauf.']),
    ''
  ]
  writeFileSync(join(ROOT, 'handbuch/bilder/pruefen.md'), lines.join('\n'))
})
