# Feature Specification: Session QA agent 2026-08-15 (live + main)

**Feature Branch**: `fix/2909-pricing-trial-copy` (PR #2972) + branches par issue

**Created**: 2026-08-15 | **Status**: Draft → Implémentation en cours

**Input**: Mission du propriétaire (session 2026-08-15) — tester la plateforme dans tous les sens
(vitrine, web, admin, mobiles, workflows, APIs, logiques, onboarding, cohérence) ; tout manquement
→ spec + tasks (méthode Spec Kit) ; implémenter les manquements en fin de test ; implémenter le max
d'issues ouvertes ; merger le max de branches.

**Contexte**: La mission exhaustive 2026-08-15 (`qa-mission-exhaustive-2026-08-15`) couvre le socle.
Cette spec couvre les constats NOUVEAUX de la session live + audit main post-merges (#2944/#2891/#2936/#2935).

## User Stories & Testing

### User Story 1 — Le checkout n'expose plus de plans fantômes (P1)

Un visiteur qui clique « Start for free » (plan Free) ou un slug legacy arrive sur le checkout du bon plan ;
le JSON de `PLAN_CONFIG` ne contient que les clés canoniques `free/pilot/operations/enterprise`.

**Pourquoi P1** : la PR #2944 (Closes #2908) a été mergée SANS la résolution des conflits — les clés
`starter`/`business` sont réapparues dans main (constat sur `origin/main`), avec fallback `'business'`.

**Acceptance Scenarios**:
1. **Given** `PLAN_CONFIG` sur main, **When** inspection, **Then** seules les clés `free/pilot/operations/enterprise` existent (plus `starter`/`business`/`scale`).
2. **Given** l'URL `/checkout?plan=starter`, **When** navigation, **Then** le plan résolu est `pilot` (alias doux), jamais un plan inconnu.
3. **Given** le fichier checkout, **When** vérification octets, **Then** aucun BOM U+FEFF en tête (régression introduite au merge).

### User Story 2 — L'essai est de 30 jours sur TOUTES les surfaces et locales (P1)

Aucune chaîne « 14 jours »/« 14-day »/« 14 gün »/« 14 يوم » ne subsiste dans la vitrine (en/tr/ar incluses).

**Pourquoi P1** : #2753 n'a corrigé que les chaînes FR — les locales en/tr/ar et les stats hero
(`{ value: 14 }`) affichaient encore 14 jours (incohérence produit).

**Acceptance Scenarios**:
1. **Given** le code vitrine, **When** grep `14 jours|14-day|14 gün|14 يوم|14 يوما`, **Then** 0 occurrence (hors docs/comments historiques).
2. **Given** la page pricing, **When** lecture de la meta description, **Then** plans Pilot/Operations + « 30 jours » (plus Starter/Business/14 jours).

### User Story 3 — Les codes de plan sont cohérents entre backend et frontend (P2)

La matrice `feature_plan_matrix` (backend) et le checkout (frontend) utilisent le MÊME jeu de codes.

**Pourquoi P2** : le seeder backend utilise `trial/starter/business/enterprise` alors que le frontend
utilise `free/pilot/operations/enterprise` — toute logique de droits branchée sur les codes backend
ne correspond pas à l'UI (constat FeaturePlanMatrixSeeder.php + checkout PLAN_CONFIG).

**Acceptance Scenarios**:
1. **Given** `api/database/seeders/FeaturePlanMatrixSeeder.php`, **When** inspection, **Then** les codes de plan sont les canoniques (ou un mapping documenté vers eux).
2. **Given** le billing dashboard, **When** un abonnement avec code `pilot`/`operations` arrive, **Then** le label affiché est correct (Pilot/Operations) — jamais la clé brute.

### User Story 4 — Les tests vitrine reflètent la réalité (P2)

La suite jest de la vitrine est verte localement (ou chaque échec est tracé avec issue).

**Pourquoi P2** : `SignupForm.test.tsx` — 5 tests échouent sur main (flow OTP « vérifiez votre email »),
hors CI (seuls lint/build/playwright tournent) → dette invisible.

**Acceptance Scenarios**:
1. **Given** `npm run test` sur main, **Then** 0 échec OU chaque échec a une issue ouverte référencée.
2. **Given** la CI vitrine, **When** PR vitrine, **Then** lint + mojibake + build + playwright s'exécutent.

### User Story 5 — Les clés i18n SEO existent dans les catalogues (P3)

`seo.pricing.description` et les clés `t()` utilisées dans seo.ts sont présentes dans les 4 catalogues
ou documentées comme volontairement en fallback.

**Pourquoi P3** : le fallback FR est toujours utilisé (clé absente des catalogues) → les meta
descriptions en/tr/ar ne sont pas réellement traduites.

**Acceptance Scenarios**:
1. **Given** les catalogues i18n de la vitrine, **When** grep `seo.pricing.description`, **Then** la clé existe (4 locales) ou un commentaire explique le fallback assumé.

## Requirements

### Functional Requirements

- **FR-001**: Le checkout ne contient que les clés de plan canoniques ; les slugs legacy sont des alias doux.
- **FR-002**: Toute chaîne d'essai visible affiche 30 jours (toutes locales).
- **FR-003**: Les labels de plan du billing dashboard couvrent les codes canoniques + alias.
- **FR-004**: La matrice de plans backend et l'UI partagent le même vocabulaire de plans (ou mapping documenté).
- **FR-005**: La suite jest vitrine est verte ou ses échecs sont tracés.

## Success Criteria

- **SC-001**: 0 occurrence « 14 » dans les chaînes d'essai de la vitrine (grep reproductible).
- **SC-002**: Checkout `PLAN_CONFIG` = 4 clés canoniques, fallback `operations`, pas de BOM.
- **SC-003**: PR #2972 mergée avec CI verte (lint/build/mojibake).
- **SC-004**: Issues créées pour les constats non couverts (codes backend, i18n SEO, tests jest).
