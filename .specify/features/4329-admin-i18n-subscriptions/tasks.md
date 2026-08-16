# Tasks — Admin SubscriptionsView i18n (issue #4329, slice)

## T1 — Catalogue

- [x] Ajouter le namespace `subscriptions` dans `fr.json`, `en.json`,
      `tr.json`, `ar.json` (clés identiques, structure paritaire).
- [x] `trialUnit` par locale (j/d/g/يوم).

## T2 — Vue

- [x] `SubscriptionsView.vue` : remplacer les ~40 littéraux FR par
      `t('subscriptions.*')`.
- [x] Helper `t(key, fallback)` (pattern #4206) + `planMeta()`, `riskLabel()`,
      `formatFeatureLabel()` via catalogue.

## T3 — Spec-kit & changelog

- [x] `.specify/features/4329-admin-i18n-subscriptions/spec.md` + `tasks.md`.
- [x] Entrée `CHANGELOG.md` sous `## [Unreleased]`.

## T4 — Validation

- [ ] `npm run lint` → 0 erreur.
- [ ] `vite build` → vert.
- [ ] `node dev-hub/tools/check-i18n-diff.js <main> <head>` → 0 nouveau
      littéral FR utilisateur.

## T5 — Livraison

- [ ] Push `fix/4329-admin-i18n-subscriptions` + PR avec `Closes #4329`.
- [ ] CI verte puis merge.
