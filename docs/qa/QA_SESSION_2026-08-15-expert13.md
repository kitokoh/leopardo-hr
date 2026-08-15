# QA Leopardo RH — Session expert #13 du 2026-08-15

Mission : implémenter le maximum d'issues ouvertes (spec-kit), rebaser/merger les branches ouvertes,
maintien de `main` vert, audit 360° et consignation Spec Kit.

## Méthode
1. Recon CI/PRs/issues/branches ; protocole anti-doublon #2400 appliqué systématiquement
   (self-assign + marker branch `fix/<issue>-<slug>` avant tout code).
2. Rebase + résolution de conflits des PRs ouvertes contre `origin/main` (le main bouge vite :
   ~20 PRs mergees pendant la session par les agents concurrents).
3. Implémentation web (testable localement : `npm run lint`, `tsc --noEmit`, `jest`, `next build`,
   `check-i18n-diff.js`) puis docs/CI.
4. Audit ciblé des surfaces restantes + réparation des gardes CI rouges sur main.

## PRs ouvertes par cet agent
| PR | Issue | Surface | Contenu |
|---|---|---|---|
| #3797 | #3732 | web | a11y FAQ + Navbar : label recherche, aria-expanded/aria-controls accordéons, drawer localisé |
| #3798 | #3731 | web | OG/Twitter guides ×3 + /demo via `generateSEOMetadata` + 5 og:image 1200×630 |
| #3799 | #3730 | web | /mobile piloté par `useVitrineLocale` (fin du useState FR en dur) |
| #3815 | — | ci/docs | main vert : checksum i18n fr resynchronisé + route edge Caddyfile documentée OpenAPI |

## PRs rebasées/mergées (déblocage du flux)
- #3761 (chore admin lint, changelog-only après merge du code par ailleurs) : rebase + push → merge.
- #3716 (canonical domains) : rebase avec résolution de conflits (garde déjà sur main via ff6287ad1,
  prise de la version main pour .env.example/DOMAINS.md) → merge.
- #3749 (edge UI) : fermée comme superseded par #3747 (même correctif #3719 déjà mergé) — évite une
  entrée CHANGELOG dupliquée.
- #3789 (queue bootstrap #3769) : PR créée depuis la branche orpheline, puis fermée (fix déjà sur main
  via #3779, et Vercel deploy limit).

## Blocage CI détecté et corrigé (régression #3734/#3735)
Deux tests mergés importent `from 'vitest'` (`navbar-locale-url.test.ts` #3785, `footer-links.test.ts`
#3734) alors que le projet utilise jest (vitest absent de package.json) → `TS2307` sur chaque
`next build`/`tsc` : check « Frontend — ESLint + TypeScript » rouge pour TOUTE PR. PR #3803 créée
(retrait des imports), fermée comme dupliquée au profit de #3802 (même correctif + CHANGELOG).

## Gardes CI rouges sur main réparées
1. `I18N Enterprise validate-and-sync` : `[fr] checksum mismatch in versions.json` → régénération
   via `node shared/i18n/sync/sync-backend.js` (PR #3815).
2. `check-openapi-route-coverage` : `GET /api/v1/edge/download/Caddyfile.edge` (route #3741) absente
   d'`openapi.yaml` → documentée (PR #3815).

## Constats d'audit (surfaces restantes, non dupliqués)
- #3727 : deux PRs concurrentes (#3801, #3804) sur le même trait `BelongsToCompany` — commentaire de
  coordination posté (fusion ou fermeture d'une des deux avant merge).
- #3272 : partie opérationnelle déjà mergée (b927157c3 unblock exports/fleet) ; volet résiduel (10
  routes tenant mortes) croise #2789/#3278 → commentaire de statut posté, à traiter après.
- #3248/#3250/#2740/#2755/#2638/#2675 : chantiers i18n/OpenAPI structurels — non re-créés (issues
  existantes), à traiter en vagues dédiées.

## Leçons pour les prochains agents
- Le CI est saturé (tous les agents poussent en parallèle) : les PRs restent « blocked » longtemps ;
  vérifier `actions/runs` plutôt que le badge PR avant de merger.
- Toujours rebaser sur `origin/main` frais juste avant de merger (main avance de ~1 merge/min).
- `check-i18n-diff.js` bloque les littéraux ajoutés dans `src/app/**` : passer par `pageMetadata`
  (`/vitrine/lib/seo.ts` est dans les ignores).
- Après tout merge touchant `shared/i18n/locales/*.json`, régénérer `versions.json`
  (`node shared/i18n/sync/sync-backend.js`) sinon validate-and-sync casse main.
