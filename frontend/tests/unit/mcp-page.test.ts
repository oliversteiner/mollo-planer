import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

const pages = {
  de: readFileSync(fileURLToPath(new URL('../../public/mcp/de/index.html', import.meta.url)), 'utf8'),
  en: readFileSync(fileURLToPath(new URL('../../public/mcp/index.html', import.meta.url)), 'utf8')
}
const llms = readFileSync(fileURLToPath(new URL('../../public/mcp/llms.txt', import.meta.url)), 'utf8')
const defaultLayout = readFileSync(fileURLToPath(new URL('../../app/layouts/default.vue', import.meta.url)), 'utf8')
const authLayout = readFileSync(fileURLToPath(new URL('../../app/layouts/auth.vue', import.meta.url)), 'utf8')

describe('static MCP guide', () => {
  it('provides both languages with direct, script-free HTML', () => {
    expect(pages.de).toContain('<html lang="de-CH">')
    expect(pages.en).toContain('<html lang="en-GB">')
    expect(pages.de).toContain('Agent-Konto')
    expect(pages.en).toContain('agent account')
    expect(pages.de).toContain('dry_run: true')
    expect(pages.en).toContain('dry_run: true')
    expect(pages.de).toContain('mode: "partial"')
    expect(pages.en).toContain('mode: "partial"')
    expect(pages.de).toContain('https://api.planer.mollo.dev/mcp')
    expect(pages.en).toContain('https://api.planer.mollo.ch/mcp')
    expect(pages.de).toContain('next_cursor')
    expect(pages.en).toContain('next_cursor')
    expect(pages.de).toContain('base_revision')
    expect(pages.en).toContain('base_revision')
    for (const tool of ['list_projects', 'list_templates', 'list_users', 'export_plan', 'import_plan', 'update_tasks', 'add_comment', 'list_comments', 'create_idea', 'list_ideas', 'get_project_ai_context']) {
      expect(pages.de).toContain(tool)
      expect(pages.en).toContain(tool)
    }
    expect(pages.de).not.toMatch(/<script\b/i)
    expect(pages.en).not.toMatch(/<script\b/i)
  })

  it('documents the JetBrains AI Assistant and Junie setup in chapter 02 with placeholders only', () => {
    for (const page of Object.values(pages)) {
      expect(page).toContain('<h3>JetBrains AI Assistant</h3>')
      expect(page).toContain('<h3>JetBrains Junie</h3>')
      expect(page).toContain('mcp-remote')
      expect(page).toContain('headers')
      expect(page).toContain('~/.junie/mcp/mcp.json')
      expect(page).not.toMatch(/"Bearer (?!(&lt;|\$\{))/)
    }
    expect(pages.de).toContain('Bearer &lt;dein-agent-token&gt;')
    expect(pages.en).toContain('Bearer &lt;your-agent-token&gt;')
    expect(pages.de).toContain('nie in die projektweite <code>.junie/mcp/mcp.json</code>')
    expect(pages.en).toContain('never in the project-wide <code>.junie/mcp/mcp.json</code>')
  })

  it('names OAuth, the token variable per account, the project .mcp.json and the title limit', () => {
    for (const page of Object.values(pages)) {
      expect(page).toContain('Custom Connector')
      expect(page).toContain('MOLLOPLANER_&lt;NAME&gt;_TOKEN')
      expect(page).toContain('"type": "http"')
      expect(page).toContain('.mcp.json')
      expect(page).toContain('160')
      expect(page).not.toMatch(/"Bearer (?!(\$\{|&lt;))/)
    }
    expect(llms).toContain('Custom Connector')
    expect(llms).toContain('MOLLOPLANER_<NAME>_TOKEN')
    expect(llms).toContain('.mcp.json')
    expect(llms).toContain('titles 2 to 160 characters')
  })

  it('links the language versions and uses a shared stylesheet without external assets', () => {
    expect(pages.de).toContain('href="/mcp/"')
    expect(pages.en).toContain('href="/mcp/de/"')
    expect(pages.de).toContain('href="../mcp.css"')
    expect(pages.en).toContain('href="./mcp.css"')
    for (const page of Object.values(pages)) {
      expect(page).not.toMatch(/(?:src|href)="https?:\/\//i)
    }
  })

  it('offers the guide from both footer layouts without adding a navigation item', () => {
    const mainItems = defaultLayout.match(/const mainItems = computed<NavItem\[]>\(\(\) => \[[\s\S]*?\.filter\(allowed\)\)/)?.[0]

    expect(defaultLayout).toContain('href="/mcp/"')
    expect(defaultLayout).toContain('class="app-mcp-link"')
    expect(defaultLayout).toMatch(/slot="navigation-footer"[\s\S]*?class="app-mcp-link"/)
    expect(authLayout).toContain('<footer')
    expect(authLayout).toContain('<a href="/mcp/">MCP</a>')
    expect(mainItems).toBeDefined()
    expect(mainItems).not.toContain('MCP')
  })
})

// The tool list of the server is the fixture backend/tests/Fixtures/mcp-tools-list.json (a backend test keeps it equal to the
// registry App\Tools\ToolRegistry). The three documents must name exactly these tools, so they cannot drift apart (Auftrag 43 E).
const toolNames: string[] = (JSON.parse(readFileSync(fileURLToPath(new URL('../../../backend/tests/Fixtures/mcp-tools-list.json', import.meta.url)), 'utf8')) as { name: string }[]).map(tool => tool.name)

describe('MCP documentation for agents', () => {
  it('names every tool of the server in llms.txt and in both pages', () => {
    expect(toolNames.length).toBeGreaterThan(10)

    for (const [file, text] of Object.entries({ 'llms.txt': llms, 'en': pages.en, 'de': pages.de })) {
      const missing = toolNames.filter(name => !text.includes(name))
      expect(missing, `${file} lacks tools`).toEqual([])
    }
  })

  it('names the tools in llms.txt only once each in the table, and no tool that does not exist', () => {
    const rows = llms.split('\n').filter(line => /^\| [a-z_]+ \|/.test(line)).map(line => line.split('|')[1]!.trim())

    expect(rows.sort()).toEqual([...toolNames].sort())
  })

  it('says the right number of tools', () => {
    const words: Record<number, [string, string]> = { 12: ['twelve', 'zwölf'], 13: ['thirteen', 'dreizehn'], 14: ['fourteen', 'vierzehn'], 15: ['fifteen', 'fünfzehn'] }
    const [en, de] = words[toolNames.length] ?? ['', '']

    expect(en, 'extend the table of number words').not.toBe('')
    expect(pages.en).toContain(`instructions and ${en} tools`)
    expect(pages.de).toContain(`Instruktionen und ${de} Werkzeuge`)
    expect(pages.en).not.toMatch(/\beleven tools\b/)
    expect(pages.de).not.toMatch(/\belf Werkzeuge\b/)
  })

  it('has a short Markdown file for agents that the pages point to', () => {
    expect(llms.length).toBeLessThanOrEqual(4000)
    expect(llms.startsWith('# Mollo Planer MCP')).toBe(true)
    expect(llms).toContain('https://api.planer.mollo.dev/mcp')
    expect(llms).toContain('https://api.planer.mollo.ch/mcp')
    expect(llms).toMatch(/## Work cheaply/)
    expect(llms).toMatch(/## Recipes/)
    expect(llms).toMatch(/## Do not/)

    for (const page of Object.values(pages)) {
      expect(page).toContain('<link rel="alternate" type="text/markdown" href="/mcp/llms.txt">')
      expect(page).toContain('<a href="/mcp/llms.txt">/mcp/llms.txt</a>')
    }
  })

  it('has no secret in llms.txt and no dry run duty for update_tasks', () => {
    expect(llms).not.toMatch(/Bearer [A-Za-z0-9|._-]{8,}/)
    expect(llms).toContain('No export, no dry run')
    expect(pages.en).not.toContain('requires <code>base_revision</code>')
    expect(pages.en).not.toContain('This repository already defines the MCP server')
    expect(pages.de).not.toContain('In diesem Repository ist der MCP-Server bereits')
  })
})
