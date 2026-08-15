# ISSUE_3963 — web-offline : icônes manifest inexistantes → PWA non installable

**Statut**: Fixed (PR `fix/3963-pwa-manifest-icons`) · **Priorité**: P2 · **Module**: web-offline/PWA

## Correctif

- Les icônes `icon-192.png` / `icon-512.png` existent désormais dans
  `front/web-offline/public/` (ajoutées en parallèle sur main).
- Ce PR ajoute la **garde CI** demandée par l'issue : le workflow
  `web-offline-ci.yml` vérifie que chaque `src` d'icône du `manifest.json`
  existe dans `public/` → la régression ne peut plus revenir silencieusement.
