// Builds the manual (Auftrag 54, next-work/54-handbuch-konzept.md) from its one source, the chapters in handbuch/de and
// handbuch/en: the data of the help in the app (app/data/manual/<lang>.json), the output for agents
// (public/manual/ in English, public/manual/de/: one HTML page and one Markdown file per chapter, a page with all,
// llms.txt) and the pictures of the help (public/help-images/, never in the output for agents).
//
// `npm run manual` runs before every build and before `npm run dev`. The JSON data and public/manual/ are in the
// repository, like the files of /mcp (tests/unit/manual.test.ts keeps them in step with the chapters); the pictures in
// public/help-images/ are copies made here and are not in the repository (the originals are in handbuch/bilder/).
//
// Markdown is read by the parser of tools/planer (the subset of the planer: paragraphs, headings, lists, quotes, bold,
// italic, inline code, links); raw HTML, tables and code blocks are errors. Links: http and https only.
import { createHash } from 'node:crypto'
import { copyFileSync, existsSync, mkdirSync, readdirSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { canonicalMarkdown, parseDescription } from '../../tools/planer/core/richtext.mjs'

const here = dirname(fileURLToPath(import.meta.url))
export const ROOT = join(here, '../..')
export const LANGUAGES = ['de', 'en']

/** The sections of a chapter, in this order, with their heading per language and where they go. */
export const SECTIONS = [
  { key: 'purpose', de: 'Wozu', en: 'Purpose', human: true, agent: true },
  { key: 'steps', de: 'So geht\'s', en: 'How to', human: true, agent: false },
  { key: 'notes', de: 'Gut zu wissen', en: 'Good to know', human: true, agent: true },
  { key: 'agents', de: 'Für Agenten', en: 'For agents', human: false, agent: true },
  { key: 'prompts', de: 'Beispielprompts', en: 'Example prompts', human: true, agent: true }
]

export const FIELDS = ['id', 'title', 'area', 'scope', 'right', 'pages', 'tools', 'commands', 'since']
const LIST_FIELDS = ['pages', 'tools', 'commands']

/** Areas of the manual, in the order of the overview (next-work/54-handbuch-konzept.md, «Kapitel»). */
export const AREAS = ['first-steps', 'projects', 'plan', 'kanban', 'my-tasks', 'comments', 'documents', 'ideas', 'assistant', 'templates', 'trash', 'statistics', 'calm', 'planer-file', 'agents', 'admin']

const UI = /\{\{ui:([a-zA-Z0-9_.-]+)\}\}/g
const PICTURE = /^( *)\{\{bild:([a-z0-9-]+)\|([^}]+)\}\}\s*$/
const MARKER = '§§bild:'

/** @param {string} lang */
function translations(lang) {
  return JSON.parse(readFileSync(join(ROOT, `frontend/i18n/locales/${lang}.json`), 'utf8'))
}

/** The text of a key of the language file, or null. */
export function uiText(messages, key) {
  const value = key.split('.').reduce((node, part) => (node && typeof node === 'object' ? node[part] : undefined), messages)

  return typeof value === 'string' && !/[{|@]/.test(value) ? value : null
}

/** Escapes a label for Markdown, so a * or _ in it stays text. */
function escapeMarkdown(text) {
  return text.replace(/([\\`*_[\]<>#])/g, '\\$1')
}

/** @returns {{ fields: Record<string, string | string[]>, body: string }} */
export function readFrontmatter(text) {
  const match = /^---\n([\s\S]*?)\n---\n?([\s\S]*)$/.exec(text.replace(/\r\n?/g, '\n'))

  if (!match) {
    return { fields: {}, body: text }
  }

  const fields = {}

  for (const line of match[1].split('\n')) {
    const pair = /^([a-z]+):\s*(.*)$/.exec(line)

    if (pair) {
      const value = pair[2].trim()
      fields[pair[1]] = LIST_FIELDS.includes(pair[1]) ? value.split(',').map(item => item.trim()).filter(Boolean) : value
    }
  }

  return { fields, body: match[2] }
}

/**
 * Reads one chapter of one language.
 *
 * @returns {{ file: string, lang: string, fields: Record<string, any>, sections: Record<string, any>, pictures: {name: string, alt: string}[], uiKeys: string[], errors: string[] }}
 */
export function readChapter(lang, file, messages = translations(lang)) {
  const path = join(ROOT, 'handbuch', lang, file)
  const { fields, body } = readFrontmatter(readFileSync(path, 'utf8'))
  const errors = []
  const uiKeys = []
  const pictures = []

  // Labels of the interface come from the language file: a renamed button changes the manual with the next build.
  const withLabels = body.replace(UI, (_, key) => {
    uiKeys.push(key)
    const text = uiText(messages, key)

    if (text === null) {
      errors.push(`${lang}/${file}: unknown or not plain key ${key}`)

      return key
    }

    return escapeMarkdown(text)
  })

  // A picture is a line of its own; it becomes a marker paragraph the parser keeps where it stands (under its step).
  const lines = withLabels.split('\n').map((line) => {
    const picture = PICTURE.exec(line)

    if (!picture) {
      return line
    }

    pictures.push({ name: picture[2], alt: picture[3].trim() })

    return `${picture[1]}${MARKER}${picture[2]}`
  })

  const sections = {}
  const parts = lines.join('\n').split(/^## (.+)$/m)
  const headings = []

  for (let i = 1; i < parts.length; i += 2) {
    const heading = parts[i].trim()
    const section = SECTIONS.find(candidate => candidate[lang] === heading)

    if (!section) {
      errors.push(`${lang}/${file}: unknown section «${heading}»`)
      continue
    }

    headings.push(section.key)
    const { doc, errors: parseErrors } = parseDescription(parts[i + 1].trim())
    parseErrors.forEach(error => errors.push(`${lang}/${file} (${heading}): ${error}`))
    sections[section.key] = doc
  }

  const order = SECTIONS.map(section => section.key).filter(key => headings.includes(key))

  if (order.join() !== headings.join()) {
    errors.push(`${lang}/${file}: sections out of order (${headings.join(', ')})`)
  }

  for (const field of ['id', 'title', 'area', 'scope', 'since']) {
    if (!fields[field]) {
      errors.push(`${lang}/${file}: field ${field} is missing`)
    }
  }

  if (fields.scope && !['core', 'app'].includes(fields.scope)) {
    errors.push(`${lang}/${file}: scope must be core or app`)
  }

  if (fields.area && !AREAS.includes(fields.area)) {
    errors.push(`${lang}/${file}: unknown area ${fields.area}`)
  }

  for (const key of Object.keys(fields)) {
    if (!FIELDS.includes(key)) {
      errors.push(`${lang}/${file}: unknown field ${key}`)
    }
  }

  walk(Object.values(sections), (node) => {
    for (const mark of node.marks ?? []) {
      if (mark.type === 'link' && !/^https?:\/\//i.test(mark.attrs?.href ?? '')) {
        errors.push(`${lang}/${file}: link ${mark.attrs?.href} (only http and https)`)
      }
    }
  })

  return { file, lang, fields: { right: '', pages: [], tools: [], commands: [], ...fields }, sections, pictures, uiKeys, errors }
}

/** Calls fn for every node of the trees. */
function walk(nodes, fn) {
  for (const node of nodes) {
    fn(node)
    walk(node.content ?? [], fn)
  }
}

/** @returns {string[]} chapter files of a language, sorted */
export function chapterFiles(lang) {
  const dir = join(ROOT, 'handbuch', lang)

  return existsSync(dir) ? readdirSync(dir).filter(file => file.endsWith('.md')).sort() : []
}

/** The paragraph is the marker of a picture: its name, else null. */
function pictureOf(node) {
  const text = node.type === 'paragraph' && node.content?.length === 1 ? node.content[0].text ?? '' : ''

  return text.startsWith(MARKER) ? text.slice(MARKER.length) : null
}

/** Without the markers of pictures (for agents, and for the text of a step). */
function withoutPictures(doc) {
  const strip = node => node.content ? { ...node, content: node.content.filter(child => pictureOf(child) === null).map(strip) } : node

  return strip(doc)
}

/**
 * Where a picture of a chapter is: an own picture of the language, an own one for all languages, else the automatic one.
 *
 * @returns {string | null} path relative to the repository
 */
export function picturePath(lang, chapter, name) {
  const candidates = [
    `handbuch/bilder/eigen/${lang}/${chapter}/${name}.webp`,
    `handbuch/bilder/eigen/${lang}/${chapter}/${name}.png`,
    `handbuch/bilder/eigen/alle/${chapter}/${name}.webp`,
    `handbuch/bilder/eigen/alle/${chapter}/${name}.png`,
    `handbuch/bilder/auto/${lang}/${chapter}/${name}.webp`
  ]

  return candidates.find(path => existsSync(join(ROOT, path))) ?? null
}

/** The address of a picture in the app: the content hash is in the name, so a new picture is never an old cached one. */
function pictureUrl(path, lang, chapter, name) {
  const hash = createHash('sha256').update(readFileSync(join(ROOT, path))).digest('hex').slice(0, 10)
  const extension = path.slice(path.lastIndexOf('.'))

  return `/help-images/${chapter}/${name}.${lang}.${hash}${extension}`
}

/**
 * The steps of «So geht's»: the items of the ordered lists, in order (a blank line before a picture ends a list for the
 * parser, the next step starts a new one), each with its text without pictures and its pictures.
 */
function stepsOf(doc, lang, chapter, pictures, images) {
  const items = (doc?.content ?? []).filter(node => node.type === 'orderedList').flatMap(list => list.content ?? [])

  return items.map((item) => {
    const shown = []

    for (const child of item.content ?? []) {
      const name = pictureOf(child)

      if (name !== null) {
        const path = picturePath(lang, chapter, name)
        const alt = pictures.find(picture => picture.name === name)?.alt ?? ''

        if (path) {
          const src = pictureUrl(path, lang, chapter, name)
          images.push({ from: path, to: `frontend/public${src}` })
          shown.push({ src, alt })
        }
      }
    }

    return { doc: withoutPictures({ type: 'doc', content: item.content ?? [] }), images: shown }
  })
}

/** The prompts: one item of the bullet lists each, as plain text. */
function promptsOf(doc) {
  return (doc?.content ?? []).filter(node => node.type === 'bulletList').flatMap(list => list.content ?? []).map(item => plainText(item).trim())
}

function plainText(node) {
  if (node.type === 'text') {
    return node.text
  }

  const inner = (node.content ?? []).map(plainText)

  return node.type === 'paragraph' ? `${inner.join('')}\n` : inner.join('')
}

// ─── HTML for agents ────────────────────────────────────────────────────────

function escapeHtml(text) {
  return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')
}

function inlineHtml(nodes) {
  return (nodes ?? []).map((node) => {
    if (node.type === 'hardBreak') {
      return '<br>'
    }

    let html = escapeHtml(node.text ?? '')

    for (const mark of node.marks ?? []) {
      if (mark.type === 'bold') html = `<strong>${html}</strong>`
      if (mark.type === 'italic') html = `<em>${html}</em>`
      if (mark.type === 'code') html = `<code>${html}</code>`
      if (mark.type === 'link') html = `<a href="${escapeHtml(mark.attrs.href)}">${html}</a>`
    }

    return html
  }).join('')
}

function blocksHtml(nodes, headingOffset = 2) {
  return (nodes ?? []).map((node) => {
    switch (node.type) {
      case 'paragraph':
        return `<p>${inlineHtml(node.content)}</p>`
      case 'heading': {
        const level = Math.min(6, (node.attrs?.level ?? 3) + headingOffset - 2)

        return `<h${level}>${inlineHtml(node.content)}</h${level}>`
      }
      case 'blockquote':
        return `<blockquote>${blocksHtml(node.content, headingOffset)}</blockquote>`
      case 'bulletList':
      case 'orderedList': {
        const tag = node.type === 'bulletList' ? 'ul' : 'ol'

        return `<${tag}>${(node.content ?? []).map(item => `<li>${blocksHtml(item.content, headingOffset)}</li>`).join('')}</${tag}>`
      }
      default:
        return ''
    }
  }).join('\n')
}

const TEXTS = {
  en: { title: 'Mollo Planer manual for agents', intro: 'What each function does, its limits, the tools of the MCP server and prompts to hand it over. Without the steps for people (those are in the help of the app).', right: 'Right', tools: 'Tools', pages: 'Pages of the app', all: 'All chapters on one page', none: '–', markdown: 'Markdown', humanOnly: 'This task is done by people in the app. The steps are in the help of the app (Help).' },
  de: { title: 'Mollo Planer: Handbuch für Agenten', intro: 'Was jede Funktion bringt, ihre Grenzen, die Werkzeuge des MCP-Servers und Prompts, um sie zu übergeben. Ohne die Schritte für Menschen (die stehen in der Hilfe der App).', right: 'Recht', tools: 'Werkzeuge', pages: 'Seiten der App', all: 'Alle Kapitel auf einer Seite', none: '–', markdown: 'Markdown', humanOnly: 'Diese Aufgabe erledigen Menschen in der App. Die Schritte stehen in der Hilfe der App (Hilfe).' }
}

function agentSections(chapter) {
  return SECTIONS.filter(section => section.agent && chapter.sections[section.key])
}

function chapterHtmlBody(chapter, level) {
  const t = TEXTS[chapter.lang]
  const facts = [
    `<li>${t.right}: ${chapter.fields.right ? `<code>${escapeHtml(chapter.fields.right)}</code>` : t.none}</li>`,
    `<li>${t.tools}: ${chapter.fields.tools.length ? chapter.fields.tools.map(tool => `<code>${escapeHtml(tool)}</code>`).join(', ') : t.none}</li>`,
    `<li>${t.pages}: ${chapter.fields.pages.length ? chapter.fields.pages.map(page => `<code>${escapeHtml(page)}</code>`).join(', ') : t.none}</li>`
  ].join('')
  const parts = agentSections(chapter).map(section => `<h${level + 1}>${escapeHtml(section[chapter.lang])}</h${level + 1}>\n${blocksHtml(withoutPictures(chapter.sections[section.key]).content, level + 2)}`)

  // A chapter without «For agents» is a task for people: one sentence points to the help of the app.
  const humanOnly = chapter.sections.agents ? '' : `<p>${escapeHtml(t.humanOnly)}</p>\n`

  return `<h${level} id="${escapeHtml(chapter.fields.id)}">${escapeHtml(chapter.fields.title)}</h${level}>\n<ul>${facts}</ul>\n${humanOnly}${parts.join('\n')}`
}

function page(lang, title, body) {
  return `<!doctype html>\n<html lang="${lang === 'de' ? 'de-CH' : 'en-GB'}">\n<head>\n<meta charset="utf-8">\n<meta name="viewport" content="width=device-width, initial-scale=1">\n<title>${escapeHtml(title)}</title>\n<link rel="alternate" type="text/markdown" href="llms.txt">\n</head>\n<body>\n<main>\n${body}\n</main>\n</body>\n</html>\n`
}

function chapterMarkdown(chapter) {
  const t = TEXTS[chapter.lang]
  const lines = [`# ${chapter.fields.title}`, '', `- ${t.right}: ${chapter.fields.right || t.none}`, `- ${t.tools}: ${chapter.fields.tools.join(', ') || t.none}`, `- ${t.pages}: ${chapter.fields.pages.join(', ') || t.none}`]

  if (!chapter.sections.agents) {
    lines.push('', t.humanOnly)
  }

  for (const section of agentSections(chapter)) {
    lines.push('', `## ${section[chapter.lang]}`, '', canonicalMarkdown(withoutPictures(chapter.sections[section.key])))
  }

  return `${lines.join('\n')}\n`
}

// ─── The whole build ────────────────────────────────────────────────────────

/**
 * Everything the build writes, without writing it.
 *
 * @returns {{ files: Map<string, string>, images: {from: string, to: string}[], chapters: Record<string, any[]>, errors: string[] }}
 */
export function buildManual() {
  const files = new Map()
  const images = []
  const chapters = {}
  const errors = []

  for (const lang of LANGUAGES) {
    const messages = translations(lang)
    const read = chapterFiles(lang).map(file => readChapter(lang, file, messages))
    read.forEach(chapter => errors.push(...chapter.errors))
    chapters[lang] = read

    const data = read.map(chapter => ({
      ...chapter.fields,
      purpose: chapter.sections.purpose ?? null,
      steps: stepsOf(chapter.sections.steps, lang, chapter.fields.id, chapter.pictures, images),
      notes: chapter.sections.notes ? withoutPictures(chapter.sections.notes) : null,
      prompts: promptsOf(chapter.sections.prompts)
    }))
    files.set(`frontend/app/data/manual/${lang}.json`, `${JSON.stringify(data, null, 2)}\n`)

    const base = lang === 'en' ? 'frontend/public/manual' : 'frontend/public/manual/de'
    const t = TEXTS[lang]
    const sorted = [...read].sort((a, b) => AREAS.indexOf(a.fields.area) - AREAS.indexOf(b.fields.area) || a.fields.title.localeCompare(b.fields.title))

    for (const chapter of sorted) {
      files.set(`${base}/${chapter.fields.id}.html`, page(lang, chapter.fields.title, chapterHtmlBody(chapter, 1)))
      files.set(`${base}/${chapter.fields.id}.md`, chapterMarkdown(chapter))
    }

    const toc = `<ul>${sorted.map(chapter => `<li><a href="${escapeHtml(chapter.fields.id)}.html">${escapeHtml(chapter.fields.title)}</a> (<a href="${escapeHtml(chapter.fields.id)}.md">${t.markdown}</a>)</li>`).join('')}</ul>`
    files.set(`${base}/index.html`, page(lang, t.title, `<h1>${escapeHtml(t.title)}</h1>\n<p>${escapeHtml(t.intro)}</p>\n${toc}\n${sorted.map(chapter => chapterHtmlBody(chapter, 2)).join('\n')}`))
    files.set(`${base}/llms.txt`, [`# ${t.title}`, '', t.intro, '', `- ${t.all}: index.html`, ...sorted.map(chapter => `- [${chapter.fields.title}](${chapter.fields.id}.md): ${chapter.fields.id}.html`), ''].join('\n'))
  }

  return { files, images, chapters, errors }
}

/** Removes files of public/manual/ and app/data/manual/ the build does not make any more (a chapter was removed). */
function removeStale(files) {
  for (const dir of ['frontend/public/manual', 'frontend/public/manual/de', 'frontend/app/data/manual']) {
    const full = join(ROOT, dir)

    if (!existsSync(full)) continue

    for (const name of readdirSync(full)) {
      const path = `${dir}/${name}`

      if (statSync(join(full, name)).isFile() && !files.has(path)) {
        rmSync(join(full, name))
      }
    }
  }
}

if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]) {
  const { files, images, errors } = buildManual()

  if (errors.length > 0) {
    console.error('The manual has errors:')
    errors.forEach(error => console.error(`  ${error}`))
    process.exit(1)
  }

  for (const [path, content] of files) {
    mkdirSync(dirname(join(ROOT, path)), { recursive: true })
    writeFileSync(join(ROOT, path), content)
  }

  removeStale(files)
  rmSync(join(ROOT, 'frontend/public/help-images'), { recursive: true, force: true })

  for (const image of images) {
    mkdirSync(dirname(join(ROOT, image.to)), { recursive: true })
    copyFileSync(join(ROOT, image.from), join(ROOT, image.to))
  }

  console.log(`Manual: ${files.size} files, ${images.length} pictures.`)
}
