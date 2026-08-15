# Plan: Vague QA Web & OpenAPI 2026-08-14

**Input**: spec.md (US1-US5) + registre findings (findings-registry.md)

## Architecture / Décisions techniques

- **Ancres /docs** : ajout des `id` manquants sur les sections existantes de `docs/page.tsx` (les cartes d'accès rapide pointent vers des sections à créer/renommer) ; retirer les cartes dont la section n'a pas de sens. Simple, pas de changement de route.
- **Player /videos** : iframe YouTube nocookie en remplacement du placeholder — composant local, `loading="lazy"`, `allowFullScreen`, `title` accessible. Les `youtubeId` existants dans la data sont conservés.
- **Sitemap** : bascule de `getAllPosts()` (mdx) vers `getBlogPosts()` (`data/blog.ts`) — une ligne de source + éventuellement nettoyage des mdx obsolètes ou conservation (hors sitemap). Vérifier que `getBlogPosts` est exporté et type-compatible avec `MetadataRoute.Sitemap`.
- **share_target** : décision → créer `src/app/share/route.ts` (POST, accepte `multipart/form-data` avec `title`, `text`, `url`, répond 302 vers `/signup?source=pwa_share`) pour honorer le manifeste ; sinon retrait. Option retenue après relecture du manifeste.
- **Skip-link** : `id="main-content"` ajouté dans le layout racine `src/app/layout.tsx` autour du `{children}` (vérifier qu'aucun layout ne casse le HTML imbriqué).
- **OpenAPI** : aligner la spec sur les routes réelles (la spec = contrat documenté, les routes = runtime ; ici les routes sont l'intention — les verbes documentés datent d'avant les implémentations). Régénérer le miroir `dev-hub/openapi/v1.yaml` via `generate-openapi-sdk.mjs`. Supprimer les 3 méthodes mortes `EdgeController` après vérification des tests (`ZktecoControllerTest`, tests edge).
- **Drift 16 ops** : documenter dans une issue dédiée (ou ajouter les routes manquantes simples : `/i18n/{locale}` déjà couvert par `/i18n/catalog/{locale}` — mise à jour spec seulement).

## Phases

### Phase 1 — Vitrine (US1-US2)
T001 ancres /docs, T002 player /videos.

### Phase 2 — Vitrine (US3-US4)
T003 sitemap, T004 share_target, T005 skip-link.

### Phase 3 — OpenAPI (US5)
T006 verbes, T007 méthodes mortes, T008 drift documenté.

### Phase 4 — Convergence
T009 CHANGELOG + registres.

## Fichiers touchés (référence)

- `front/web/src/app/(landing)/docs/page.tsx`
- `front/web/src/app/(landing)/videos/page.tsx`
- `front/web/src/app/sitemap.ts`
- `front/web/src/app/share/route.ts` (nouveau) ou `front/web/public/manifest.json`
- `front/web/src/app/layout.tsx`
- `api/openapi.yaml`, `dev-hub/openapi/v1.yaml`
- `api/app/Modules/EdgeSync/Interfaces/Api/V1/Controllers/EdgeController.php`
- `CHANGELOG.md`, `.specify/memory/project-state.md`

## Contraintes

- Build Next.js vert (`npm run build`), lint TypeScript vert.
- Tests PHP touchés (edge) verts.
- Ne pas dupliquer les issues #2174-#2180 / spec qa-hardening (agent parallèle).
