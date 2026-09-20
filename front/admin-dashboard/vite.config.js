import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'node:path'
import { readFileSync, writeFileSync } from 'node:fs'
import { buildCsp, assertCspHardened, CSP_PLACEHOLDER } from './scripts/csp.mjs'

// #4715 (audit 360° 2026-08-16) : fail-closed — un build de production sans
// VITE_API_URL produirait un bundle dont les appels API et le fallback
// WebSocket (wss://<hôte API>) viseraient des défauts silencieux (ex.
// ws://localhost:6001 chez le visiteur). Le CI passe la variable
// (deploy-admin-dashboard.yml) ; sans elle, on échoue explicitement.
if (process.env.NODE_ENV === 'production' && !process.env.VITE_API_URL) {
  throw new Error(
    'VITE_API_URL manquant : le build production exige VITE_API_URL ' +
      '(issue #4715) — ex. https://api.example.com/api/v1'
  )
}

export default defineConfig({
  // Issue #2334 : `base: './'` (chemins relatifs) combiné à
  // `createWebHistory()` cassait le hard refresh / l'accès direct à une
  // sous-route sur Cloudflare Pages (les assets se résolvaient sous le
  // chemin courant → text/html au lieu des .js). L'app est déployée à la
  // racine du domaine (leo-admin.pages.dev) → base absolue '/'.
  base: '/',
  plugins: [
    vue(),
    // #7695 : injecte la CSP générée (connect-src explicite dérivé de
    // VITE_API_URL/VITE_WEBSOCKET_URL, script-src sans 'unsafe-inline')
    // dans dist/_headers après la copie de public/. Fail-closed : lève si
    // le placeholder est absent ou si la CSP produite régresse.
    {
      name: 'csp-headers',
      apply: 'build',
      closeBundle() {
        const headersPath = resolve(import.meta.dirname, 'dist', '_headers')
        const template = readFileSync(headersPath, 'utf8')
        if (!template.includes(CSP_PLACEHOLDER)) {
          throw new Error(
            `public/_headers ne contient plus le placeholder ${CSP_PLACEHOLDER} (#7695).`,
          )
        }
        const csp = buildCsp(process.env)
        assertCspHardened(csp)
        writeFileSync(headersPath, template.replace(CSP_PLACEHOLDER, csp))
      },
    },
  ],
  resolve: {
    alias: {
      '@': resolve(import.meta.dirname, 'src'),
    },
  },
  server: {
    port: 3001,
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
  build: {
    outDir: 'dist',
    assetsDir: 'assets',
    // Audit #1701 : pas de sourcemaps en production (exposition du code
    // source + fuite de la logique métier) — dev uniquement.
    sourcemap: process.env.NODE_ENV !== 'production',
  },
})