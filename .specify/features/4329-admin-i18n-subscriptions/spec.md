# Feature Specification: Admin cockpit — SubscriptionsView localisée (slice #4329)

**Feature Branch**: `fix/4329-admin-i18n-subscriptions`
**Created**: 2026-08-16 | **Status**: Implemented
**Issue**: #4329 (P2, admin, i18n — lot 5, slice SubscriptionsView)

## Contexte

`front/admin-dashboard/src/views/subscriptions/SubscriptionsView.vue` était
100 % FR codé en dur (~40 chaînes : titres, KPI, catalogue des offres, labels
de features, niveaux de risque, erreurs) — résiduel de #4206/#4305. Les lots
1-4 (#4206) ont établi le pattern : helper `t(key, fallback)` basé sur
`translate(localeStore.current, key, fallback)` + catalogue
`src/i18n/locales/{fr,en,tr,ar}.json` (namespace par vue).

## User Stories & Testing

### User Story 1 — Un super-admin turc lit le cockpit dans sa langue (P2)

En tant que super-admin dont la locale admin est `tr`, je veux que la vue
Abonnements soit affichée en turc (pas en FR).

**Acceptance Scenarios**:
1. Given locale admin `en`, When la vue se charge, Then titres, KPI, boutons
   et labels de features sont en anglais.
2. Given locale admin `tr`/`ar`, When la vue se charge, Then la vue est en
   turc/arabe (RTL pour ar).
3. Given une clé de feature inconnue, When la vue se charge, Then le code de
   feature est affiché en majuscules (fallback, comportement conservé).
4. Given une erreur réseau, When le chargement échoue, Then le message
   d'erreur est localisé (plus de FR en dur).

### Edge Cases

- `max_employees` null (Enterprise) → « Illimité »/« Unlimited »/… localisé.
- `risk_level` inattendu → fallback sur la valeur brute.
- Plan sans nom (`item.plan` null) → « Sans plan » localisé.

## Requirements

### Functional Requirements

- **FR-001**: toutes les chaînes utilisateur de SubscriptionsView passent par
  `t('subscriptions.*')`.
- **FR-002**: le namespace `subscriptions` existe dans les 4 catalogues admin
  (fr/en/tr/ar) avec les mêmes clés (parité, garde drift).
- **FR-003**: `trialUnit` localisé (j/d/g/يوم) — pas de « 14j » FR en dur.
- **FR-004**: les labels de features (`subscriptions.features.*`) remplacent le
  mapping FR codé en dur.

## Success Criteria

- **SC-001**: `npm run lint` 0 erreur ; `vite build` vert.
- **SC-002**: zéro littéral FR utilisateur restant dans
  `SubscriptionsView.vue` (scan `check-i18n-diff.js` sur la PR).
- **SC-003**: parité de clés entre les 4 catalogues (JSON identiques en
  structure pour `subscriptions`).

## Assumptions

- Les 4 autres vues du lot 5 (#4329 : Fleet, System, Settings, Support) restent
  hors périmètre de cette branche (traitement séparé).
- Le pattern `translate(locale, key, fallback)` existant est conservé (pas de
  migration vue-i18n).
