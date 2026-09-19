/**
 * Test de garde post-build (#7695) — exécuté par `npm run build`.
 *
 * Vérifie que la CSP réellement embarquée dans `dist/_headers` (celle que
 * Cloudflare Pages servira) est durcie :
 *   - header Content-Security-Policy présent, placeholder remplacé ;
 *   - `script-src` sans 'unsafe-inline' ;
 *   - `connect-src` sans scheme nu (`https:`, `wss:`, …) ni wildcard.
 *
 * Échec = build rouge (CI web-build + workflow de déploiement).
 */
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { assertCspHardened } from './csp.mjs'

const headersPath = resolve(import.meta.dirname, '..', 'dist', '_headers')

let content
try {
  content = readFileSync(headersPath, 'utf8')
} catch {
  console.error(`[csp-guard] ${headersPath} introuvable — lancer \`vite build\` d'abord (#7695).`)
  process.exit(1)
}

const match = content.match(/^\s*Content-Security-Policy:\s*(.+)$/m)
if (!match) {
  console.error('[csp-guard] Aucun header Content-Security-Policy dans dist/_headers (#7695).')
  process.exit(1)
}

try {
  assertCspHardened(match[1].trim())
} catch (error) {
  console.error(`[csp-guard] ÉCHEC : ${error.message}`)
  process.exit(1)
}

console.log('[csp-guard] OK — CSP du build durcie (script-src sans unsafe-inline, connect-src explicite).')
console.log(`[csp-guard] ${match[1].trim()}`)
