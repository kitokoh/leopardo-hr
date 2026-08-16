# ISSUE_3965 — Dockerfile standalone : PWA Next.js non buildée

**Statut**: Fixed (PR `fix/3965-edge-standalone-pwa-build`) · **Priorité**: P3 · **Module**: edge-sync

## Correctif

`edge/Dockerfile` : stage `pwa-build` (node:20-alpine) exécute `npm ci` +
`next build` (web-offline est en `output: 'export'` → `out/`), puis
`COPY --from=pwa-build /pwa/out/ /app/public/edge-web/`. Vérifié localement :
le build produit `out/index.html`, `manifest.json`, icônes et `_next/`.
