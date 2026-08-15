# Feature Specification: QA Expert #3 — CI/Backend santé (2026-08-15)

**Feature**: `qa-expert3-backend-2026-08-15`
**Created**: 2026-08-15
**Status**: Livrée

## Problème
Main ROUGE au début de la session : PHPStan Strict (level 8) + PHPStan Modules + Module Structure Validator + I18N Enterprise + Web CI Vitrine en échec (accumulation de merges pendant saturation CI, issue #2488/#2131).

## Corrections
### PHPStan (PR #3207)
- App : Carbon avant isFuture/AccountLockedException, Socialite typé, KioskController ??, EdgeNodeController refresh.
- 29 fichiers de tests réalignés level 8 (collect routes, @var FQCN, Mockery Expectation, PendingCommand, casts).
- `AIGatewayAndAnalyticsTest` : 2 workflows (Closes #3118, #2808).

### Module Structure Validator (#2969)
- Préfixes de migrations dupliqués `2026_08_15_000001` (public + tenant) renommés — PR #2969.

### I18N Enterprise (#3110)
- Catalogues partagés alignés (clés impersonation/edit/companies.toast/errors ajoutées au canonique `shared/i18n`) — PR #3110.

### Constat suite complète locale
- `TrainingGlobalListTest` cassé sur main (end_date NOT NULL) — corrigé (PR #3420) ; le reste de la suite en cours de validation locale.

## Critères de succès
- Checks requis verts sur main (PHPStan strict/modules, Module Structure, Frontend ESLint+TS, actionlint).
- Main reste vert après chaque merge.
