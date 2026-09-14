import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'node:path'

// Tests unitaires de la console d'admin (#7433/#7434/#7402).
//
// Le projet n'avait QUE des tests Playwright (`e2e/`) : aucune vérification
// unitaire ne tournait sur la CI. Vitest réutilise la config Vite (alias `@`,
// plugin Vue) et tourne dans jsdom pour les composants.
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': resolve(import.meta.dirname, 'src'),
    },
  },
  test: {
    environment: 'jsdom',
    include: ['src/**/*.spec.js'],
    globals: true,
    restoreMocks: true,
  },
})
