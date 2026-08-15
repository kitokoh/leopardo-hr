# ISSUE_3962 — web-offline : SW sans fallback offline pour les navigations

**Statut**: Fixed (PR `fix/3962-sw-offline-fallback`) · **Priorité**: P2 · **Module**: web-offline/PWA

## Correctif

`front/web-offline/public/sw.js` : `.catch()` ajouté sur la branche statique —
navigation hors-ligne vers une route non visitée → `caches.match('/index.html')`
(app shell pré-cachée), sinon 503 gracieux. Cache bumpé v1 → v2 (invalidation).
Vérifié : `node --check` OK.
