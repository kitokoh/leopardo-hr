# Feature Specification: PWA Edge web-offline — couverture de tests (issue #3971)

**Feature Branch**: `fix/3971-web-offline-tests`

**Created**: 2026-08-15

**Status**: Draft → Implemented

**Input**: Constat QA qa-expert14 2026-08-15 — `.github/workflows/web-offline-ci.yml` n'exécute que `npm run lint` + `npm run build` ; `front/web-offline/package.json` n'a ni script `test` ni dépendance de test. La logique critique (machine à états health de `page.tsx`, stratégies de cache du SW) n'a aucune couverture — #3719/#3772 ont montré que le contrat API peut régresser sans signal.

## Problème

- Zéro test sur la PWA Edge malgré un workflow intitulé « lint + build + tests ».
- `page.tsx` : la machine à états `checking → online / error / offline` (contrat `/api/v1/edge/health` versionné, timeout 4 s, poll 30 s) est embarquée dans le composant — non testable.
- `public/sw.js` : les stratégies Cache First / Network First / fallback navigation (#3962) sont embarquées dans le SW — non testables hors navigateur.

## Décision

Extraire la logique dans des modules purs testables, sans changer le comportement runtime :

1. `src/lib/edge-health.ts` : `checkEdgeHealth(baseUrl, fetcher?)` + constantes (`HEALTH_TIMEOUT_MS`, `HEALTH_POLL_INTERVAL_MS`, `EDGE_API_DEFAULT`). `page.tsx` consomme le module (comportement identique : URL versionnée, `AbortSignal.timeout(4000)`, poll 30 s).
2. `public/sw-strategies.js` : module CJS pur (`classifyRequest`, `offlineApiResponse`, `navigationFallback`, `isCacheable`), chargé par `sw.js` via `importScripts('/sw-strategies.js')` ET testable en Node (dual `module.exports` / `globalThis.LeopardoSwStrategies`).
3. Tests **Vitest** (léger, moderne, adapté Next 16 + React 19) :
   - `tests/edge-health.test.ts` (node) — 8 cas : online/error/offline/timeout/JSON malformé/jamais d'exception/URL par défaut/timeout borné ;
   - `tests/sw-strategies.test.mjs` (node, `createRequire`) — 12 cas : routage api vs static, payload 503, fallback navigation, cachable 200 non-opaque ;
   - `tests/page.test.tsx` (jsdom par pragma) — 6 cas : rendu checking/online/offline/error, champs optionnels `—`, re-check au clic Actualiser.
   - `tests/setup.ts` : cleanup RTL explicite (`globals: false` → pas d'auto-cleanup).
4. CI : étape `Test` ajoutée dans `web-offline-ci.yml` (job renommé « Lint + Test + Build »).

## User Scenarios & Testing

### User Story 1 — La PWA Edge a une suite de tests exécutée en CI (Priority: P1)

**Independent Test**: `npm test` (web-offline) → 22 tests verts ; workflow `web-offline-ci` exécute lint + test + build.

**Acceptance Scenarios**:

1. **Given** le dépôt, **When** `cd front/web-offline && npm test`, **Then** 22/22 tests verts (3 fichiers).
2. **Given** la CI, **When** une PR touche web-offline, **Then** le job exécute `npm run lint`, `npm test`, `npm run build`.
3. **Given** un changement de contrat santé (#3719/#3772), **When** le fetch se comporte différemment, **Then** les tests `edge-health` le détectent.
4. **Given** une modification des stratégies SW, **When** le routage cache-first/network-first dévie, **Then** les tests `sw-strategies` le détectent.
5. **Given** `npm run lint` + `npx tsc --noEmit` + `npm run build`, **When** exécutés, **Then** 0 erreur.

## Edge Cases

- `sw.js` reste un SW classique (pas de module) : `importScripts('/sw-strategies.js')` préserve la compatibilité.
- Vitest 4 : pas d'`environmentMatchGlobs` (option retirée) — pragma `// @vitest-environment jsdom` par fichier.
- `globals: false` : cleanup RTL manuel obligatoire (sinon composants fantômes entre tests).
- Le comportement runtime de `page.tsx` est strictement inchangé (mêmes URL, timeout, intervalle, textes).
