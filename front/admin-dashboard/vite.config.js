import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'node:path'

// #4715 (audit 360° 2026-08-16) : fail-closed — un build de production sans
// VITE_API_URL produirait un bundle dont les appels API et le fallback
// WebSocket (wss://<hôte API>) viseraient des défauts silencieux (ex.
// ws://localhost:6001 chez le visiteur). Le CI passe la variable
// (deploy-admin-dashboard.yml) ; sans elle, on échoue explicitement.
if (process.env.NODE_ENV === 'production' && !process.env.VITE_API_URL) {
  throw new Error(
    'VITE_API_URL manquant : le build production exige VITE_API_URL ' +
      '(issue #4715) — ex. https://gestionemployerbackend.onrender.com/api/v1'
  )
}

export default defineConfig({
  // Issue #2334 : `base: './'` (chemins relatifs) combiné à
  // `createWebHistory()` cassait le hard refresh / l'accès direct à une
  // sous-route sur Cloudflare Pages (les assets se résolvaient sous le
  // chemin courant → text/html au lieu des .js). L'app est déployée à la
  // racine du domaine (leo-admin.pages.dev) → base absolue '/'.
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