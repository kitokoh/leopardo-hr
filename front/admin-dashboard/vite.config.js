import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'node:path'

export default defineConfig({
  // Issue #2334 : `base: './'` cassait le hard refresh / deep link sur
  // Cloudflare Pages (assets relatifs résolus sous le chemin courant →
  // /companies/assets/*.js servait index.html en text/html). Le SPA est
  // déployé à la racine du domaine (leo-admin.pages.dev) : base absolue.
  base: '/',
  plugins: [vue()],
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