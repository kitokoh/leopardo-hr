import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'node:path'

/**
 * Configuration Vitest du dashboard admin.
 *
 * ## Pourquoi ce fichier existe
 *
 * Le dashboard admin comptait 42 000 lignes de JavaScript et **zéro test
 * unitaire** : seuls 33 specs Playwright e2e couvraient l'application, et
 * uniquement à travers un navigateur réel contre un backend. Les stores
 * d'authentification et la couche API — c'est-à-dire le code qui décide qui est
 * connecté et ce qu'il a le droit de voir — n'étaient vérifiés par rien.
 *
 * ## Pourquoi un fichier séparé de `vite.config.js`
 *
 * `vite.config.js` porte des garde-fous de BUILD (fail-closed sur l'URL d'API,
 * injection de la CSP dans `dist/_headers`). Les charger en test ferait échouer
 * la suite sur une variable d'environnement absente, sans rapport avec le code
 * testé. La config de test ne reprend donc que ce qui est nécessaire : l'alias
 * `@` et le plugin Vue.
 *
 * ## Environnement
 *
 * `jsdom` : les modules testés touchent `sessionStorage`, `localStorage`,
 * `window.addEventListener` et `document`. Le store de token en particulier
 * s'appuie sur le cycle `pagehide`/`pageshow` — impossible à exercer sans DOM.
 */
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': resolve(import.meta.dirname, 'src'),
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    // Les specs e2e Playwright vivent dans `e2e/` et `e2e-staging/` : elles
    // exigent un navigateur et un backend, Vitest ne doit pas les ramasser.
    include: ['src/**/*.{test,spec}.{js,mjs}'],
    exclude: ['node_modules/**', 'dist/**', 'e2e/**', 'e2e-staging/**'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'lcov'],
      include: ['src/stores/**', 'src/services/**'],
    },
  },
})
