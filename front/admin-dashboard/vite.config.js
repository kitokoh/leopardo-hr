import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'node:path'

export default defineConfig({
  // QA #2334 : `base: './'` + createWebHistory → sur une sous-route, les
  // assets relatifs se résolvent sous le chemin courant (hard refresh /
  // deep link = page blanche). Le déploiement est à la racine du domaine.
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