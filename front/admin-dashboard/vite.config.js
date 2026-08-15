import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'node:path'

export default defineConfig({
  // #2334 : base racine — `./` + createWebHistory cassait le hard refresh
  // sur les sous-routes (assets relatifs résolus sous le chemin courant →
  // index.html servi comme JS → page blanche). Déploiement à la racine du
  // domaine (leo-admin.pages.dev) ; le fallback SPA vit dans public/_redirects.
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