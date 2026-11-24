// Copies the format schema (spec/planer-v2.schema.json, the one description of the planer-v2 form) to
// public/mcp/format-v2.schema.json, where the MCP server's instructions and llms.txt point to. Never edited by hand:
// `npm run mcp:schema` (runs before every build); the test tests/unit/mcp-page.test.ts keeps the copy in step.
import { copyFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'

const from = fileURLToPath(new URL('../../spec/planer-v2.schema.json', import.meta.url))
const to = fileURLToPath(new URL('../public/mcp/format-v2.schema.json', import.meta.url))

copyFileSync(from, to)
