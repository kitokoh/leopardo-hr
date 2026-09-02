# Surfaces Offline — décision d'architecture (issue #6697)

> Décision PM (2026-09-02) : **deux surfaces offline distinctes, conservées
> telles quelles**, chacune avec un contrat de déploiement différent. La
> duplication constatée par l'audit est conceptuelle, pas fonctionnelle —
> fusionner casserait le service worker de la web app ou le build edge-ui.

## Les deux surfaces

| Surface | Emplacement | Rôle | Contrat |
|---|---|---|---|
| **PWA fallback** | `front/web/src/app/offline/` | Page affichée par le **service worker** de la web app principale quand le réseau est coupé (`front/web/public/sw.js` → `OFFLINE_URL = '/offline'`). robots:noindex, i18n `getCopy().offlinePage`, lien vers le nœud Edge si `NEXT_PUBLIC_EDGE_NODE_URL` est défini. | Doit vivre DANS la web app (le SW pré-cache la route) |
| **Edge UI** | `front/web-offline/` | Application Next.js **autonome** (port 3001) : dashboard offline-first du nœud Edge local (health-check `/api/v1/edge/health`, état de sync, copie localisée fr/en/tr/ar). Buildée en image `leopardo/edge-ui` (voir PR #6668) et installée chez le client via `edge/docker-compose.yml`. | Doit rester minimale (build rapide, image légère pour les installs client) |

## Pourquoi on ne fusionne pas (maintenant)

1. **`front/web/src/app/offline` ne peut pas être supprimé** : le service worker
   de la web app (`public/sw.js`) le précache comme page de repli hors-ligne.
   Le supprimer = PWA sans fallback.
2. **`front/web-offline` ne peut pas être plié dans `front/web` sans coût** :
   l'image edge-ui est buildée depuis une app minimale (692 packages dans
   `front/web` vs ~30 dans `front/web-offline`) — intégrer l'offline dans la
   web app gonflerait le build edge et la surface d'attaque des installs client.
3. Les **tests/CI sont déjà séparés** : `web-offline-ci.yml` (lint + vitest +
   build) pour `front/web-offline`, garde PWA côté web.

## Ce qui a été fait (issue #6697)

- Décision documentée (ce fichier) + pointeur dans `front/web-offline/README.md`.
- La page PWA `/offline` de la web app est **fonctionnelle** (repli réseau +
   lien nœud Edge) — pas un stub.
- **Réunification à reconsidérer** quand le build edge-ui sera rationalisé
   (ex. sortie d'un package `@leopardo/offline-ui` partagé, ou `next output:
   export` ciblé) — ticket séparé recommandé à ce moment-là.

## Références

- `front/web/public/sw.js` (OFFLINE_URL), `front/web/src/app/offline/`
- `front/web-offline/` (+ `web-offline-ci.yml`)
- `edge/docker-compose.yml`, `edge/publish.sh`, PR #6668 (image edge-ui)
- Issue #6697 (audit structuration 2026-09-01)
