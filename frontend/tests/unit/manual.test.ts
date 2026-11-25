import { copyFileSync, existsSync, mkdirSync, readdirSync, readFileSync, rmSync, statSync } from 'node:fs'
import { join, relative } from 'node:path'
import { describe, expect, it } from 'vitest'
// @ts-expect-error plain JavaScript module of the scripts folder
import { buildManual, LANGUAGES, picturePath, readChapter, ROOT, SECTIONS } from '../../scripts/manual.mjs'
// @ts-expect-error plain JavaScript module of the scripts folder
import { commands, inventory, mentioned, pages, rights, tools, withoutChapter } from '../../scripts/manual-inventory.mjs'

/**
 * The manual (Auftrag 54 C, F): one source in handbuch/, the outputs made from it, and the chapters checked against the
 * code. What has no chapter yet is in handbuch/ohne-kapitel.txt, which only gets shorter.
 */
type Chapter = { file: string, lang: string, fields: Record<string, string & string[]>, sections: Record<string, unknown>, pictures: { name: string, alt: string }[], uiKeys: string[] }

const built = buildManual() as { files: Map<string, string>, chapters: Record<string, Chapter[]>, errors: string[] }
const byId = (lang: string) => new Map(built.chapters[lang]!.map(chapter => [chapter.fields.id, chapter]))

/** Ordered list items of a section (the build counts the same way, see stepsOf in scripts/manual.mjs). */
function count(doc: unknown, type: string): number {
  const content = (doc as { content?: { type: string, content?: unknown[] }[] } | undefined)?.content ?? []

  return content.filter(node => node.type === type).reduce((sum, node) => sum + (node.content?.length ?? 0), 0)
}

function filesBelow(dir: string): string[] {
  if (!existsSync(dir)) return []

  return readdirSync(dir).flatMap((name) => {
    const path = join(dir, name)

    return statSync(path).isDirectory() ? filesBelow(path) : [path]
  })
}

describe('source and output', () => {
  it('builds without errors (sections, fields, keys, allowed Markdown, links)', () => {
    expect(built.errors).toEqual([])
  })

  it('has the data of the help and the output for agents as the build makes them (npm run manual)', () => {
    for (const [path, content] of built.files) {
      expect(readFileSync(join(ROOT, path), 'utf8'), `${path} is not up to date: npm run manual`).toBe(content)
    }

    const onDisk = [...filesBelow(join(ROOT, 'frontend/public/manual')), ...filesBelow(join(ROOT, 'frontend/app/data/manual'))].map(path => relative(ROOT, path))
    expect(onDisk.filter(path => !built.files.has(path))).toEqual([])
  })
})

describe('completeness against the code', () => {
  it('reads the pages, rights, command entries and tools of the code', () => {
    expect(pages()).toContain('/admin/users')
    expect(pages()).toContain('/projects/[id]/kanban')
    expect(rights()).toContain('task.create')
    expect(commands()).toContain('page-users')
    expect(tools()).toContain('update_tasks')
  })

  it('lists every page, right, command and tool either in a chapter or in handbuch/ohne-kapitel.txt, and nothing else', () => {
    const all = new Set(inventory() as string[])
    const done = mentioned() as Set<string>
    const open = withoutChapter() as string[]

    expect([...done].filter(line => !all.has(line)), 'a chapter names something the code does not have').toEqual([])
    expect(open.filter(line => !all.has(line)), 'handbuch/ohne-kapitel.txt names something the code does not have').toEqual([])
    expect(open.filter(line => done.has(line)), 'has a chapter now: remove it from handbuch/ohne-kapitel.txt').toEqual([])
    expect([...all].filter(line => !done.has(line) && !open.includes(line)), 'neither in a chapter nor in handbuch/ohne-kapitel.txt').toEqual([])
  })
})

describe('both languages', () => {
  it('has every chapter in both languages with the same id, fields, sections, steps, prompts, keys and pictures', () => {
    const de = byId('de')
    const en = byId('en')

    expect([...en.keys()].sort()).toEqual([...de.keys()].sort())

    for (const [id, german] of de) {
      const english = en.get(id)!
      expect(english.file, id).toBe(german.file)
      expect(german.file, id).toBe(`${id}.md`)

      for (const field of ['area', 'scope', 'right', 'pages', 'tools', 'commands', 'since']) {
        expect(english.fields[field], `${id}: ${field}`).toEqual(german.fields[field])
      }

      expect(Object.keys(english.sections), `${id}: sections`).toEqual(Object.keys(german.sections))
      expect(count(english.sections.steps, 'orderedList'), `${id}: steps`).toBe(count(german.sections.steps, 'orderedList'))
      expect(count(english.sections.prompts, 'bulletList'), `${id}: prompts`).toBe(count(german.sections.prompts, 'bulletList'))
      expect([...new Set(english.uiKeys)].sort(), `${id}: keys`).toEqual([...new Set(german.uiKeys)].sort())
      expect(english.pictures.map(picture => picture.name), `${id}: pictures`).toEqual(german.pictures.map(picture => picture.name))
    }
  })

  it('uses only keys that exist in de.json and en.json, as plain text', () => {
    const keys = LANGUAGES.flatMap((lang: string) => built.chapters[lang]!.flatMap(chapter => chapter.uiKeys))
    expect(keys.length).toBeGreaterThan(0)
    // An unknown key or one with {…} or plural forms is an error of the build (first test).
    expect(built.errors.filter(error => error.includes('key'))).toEqual([])
  })

  it('has only the sections of the guide, in their order', () => {
    for (const lang of LANGUAGES) {
      for (const chapter of built.chapters[lang]!) {
        const order = SECTIONS.map((section: { key: string }) => section.key)
        const keys = Object.keys(chapter.sections)
        expect(keys, chapter.file).toEqual(order.filter((key: string) => keys.includes(key)))
      }
    }
  })
})

describe('pictures', () => {
  it('has every picture of a chapter in both languages (automatic, own or for all), with a text in both', () => {
    for (const lang of LANGUAGES) {
      for (const chapter of built.chapters[lang]!) {
        for (const picture of chapter.pictures) {
          expect(picturePath(lang, chapter.fields.id, picture.name), `${lang}/${chapter.fields.id}/${picture.name}`).not.toBeNull()
          expect(picture.alt.length, `${lang}/${chapter.fields.id}/${picture.name}: alternative text`).toBeGreaterThan(5)
        }
      }
    }
  })

  it('has no picture file without a reference and none larger than 300 KB', () => {
    const used = new Set(LANGUAGES.flatMap((lang: string) => built.chapters[lang]!.flatMap(chapter => chapter.pictures.map(picture => `${chapter.fields.id}/${picture.name}`))))
    const pictures = filesBelow(join(ROOT, 'handbuch/bilder')).filter(path => /\.(webp|png)$/.test(path))

    for (const path of pictures) {
      const [chapter, file] = relative(join(ROOT, 'handbuch/bilder'), path).split('/').slice(-2)
      expect(used.has(`${chapter}/${file!.replace(/\.(webp|png)$/, '')}`), `${relative(ROOT, path)} is not used by a chapter`).toBe(true)
      expect(statSync(path).size, `${relative(ROOT, path)} is larger than 300 KB`).toBeLessThanOrEqual(300 * 1024)
    }
  })

  it('has the list of doubtful pictures in the repository', () => {
    expect(existsSync(join(ROOT, 'handbuch/bilder/pruefen.md'))).toBe(true)
  })
})

describe('output for agents', () => {
  const output = [...built.files].filter(([path]) => path.startsWith('frontend/public/manual/'))

  it('has no script, no picture, no style and no navigation', () => {
    for (const [path, content] of output.filter(([path]) => path.endsWith('.html'))) {
      expect(content, path).not.toMatch(/<script\b|<img\b|<style\b|<nav\b|style=/i)
    }
  })

  it('names every chapter exactly once in llms.txt, in both languages', () => {
    for (const lang of LANGUAGES) {
      const llms = built.files.get(lang === 'en' ? 'frontend/public/manual/llms.txt' : 'frontend/public/manual/de/llms.txt')!

      for (const chapter of built.chapters[lang]!) {
        expect(llms.split(`(${chapter.fields.id}.md)`).length - 1, `${lang} ${chapter.fields.id}`).toBe(1)
      }
    }
  })

  it('leaves out the steps for people', () => {
    const html = built.files.get('frontend/public/manual/de/task-anlegen.html')!
    expect(html).not.toContain('So geht')
    expect(html).toContain('Für Agenten')
  })

  it('holds no secret and no real address', () => {
    for (const [path, content] of output) {
      expect(content, path).not.toMatch(/\b\d+\|[A-Za-z0-9]{20,}/)
      expect(content, path).not.toMatch(/Bearer [A-Za-z0-9]{10,}/)
      expect(content, path).not.toMatch(/https?:\/\/(?!(www\.)?example\.(com|org)\b)[a-z0-9.-]+\.[a-z]{2,}/i)
    }
  })
})

describe('labels and own pictures', () => {
  it('takes the label of a button from the language file: a renamed button is renamed in the chapter', () => {
    const messages = JSON.parse(readFileSync(join(ROOT, 'frontend/i18n/locales/de.json'), 'utf8'))
    messages.task.create = 'Neuen Task erfassen'

    const chapter = readChapter('de', 'task-anlegen.md', messages) as Chapter
    expect(JSON.stringify(chapter.sections.steps)).toContain('Neuen Task erfassen')
    expect(JSON.stringify(chapter.sections.steps)).not.toContain('Task anlegen')
  })

  it('prefers an own picture of the same name to the automatic one', () => {
    const automatic = picturePath('de', 'task-anlegen', 'board')
    expect(automatic).toBe('handbuch/bilder/auto/de/task-anlegen/board.webp')

    const own = join(ROOT, 'handbuch/bilder/eigen/de/task-anlegen/board.webp')
    mkdirSync(join(own, '..'), { recursive: true })
    copyFileSync(join(ROOT, automatic!), own)

    try {
      expect(picturePath('de', 'task-anlegen', 'board')).toBe('handbuch/bilder/eigen/de/task-anlegen/board.webp')
      expect(picturePath('en', 'task-anlegen', 'board')).toBe('handbuch/bilder/auto/en/task-anlegen/board.webp')
    } finally {
      rmSync(join(ROOT, 'handbuch/bilder/eigen/de'), { recursive: true, force: true })
    }
  })
})
