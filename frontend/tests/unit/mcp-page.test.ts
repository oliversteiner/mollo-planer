import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

const pages = {
  de: readFileSync(fileURLToPath(new URL('../../public/mcp/de/index.html', import.meta.url)), 'utf8'),
  en: readFileSync(fileURLToPath(new URL('../../public/mcp/index.html', import.meta.url)), 'utf8')
}
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
      expect(page).not.toMatch(/"Bearer (?!&lt;)/)
    }
    expect(pages.de).toContain('Bearer &lt;dein-agent-token&gt;')
    expect(pages.en).toContain('Bearer &lt;your-agent-token&gt;')
    expect(pages.de).toContain('nie in die projektweite <code>.junie/mcp/mcp.json</code>')
    expect(pages.en).toContain('never in the project-wide <code>.junie/mcp/mcp.json</code>')
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
