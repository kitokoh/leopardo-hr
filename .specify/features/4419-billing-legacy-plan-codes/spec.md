# Feature Specification: Billing dashboard — codes plans canoniques (Closes #4419)

**Feature Branch**: `fix/4419-billing-legacy-plan-codes`
**Created**: 2026-08-16 | **Status**: In progress
**Base**: `origin/main`

## Problème

`front/web/src/app/(dashboard)/billing/page.tsx` POSTe `plan: 'starter'|'business'`
vers `POST /billing/checkout`, validé côté API par `Rule::in(PlanCode::values())`
(`free|pilot|operations|enterprise`) → **422 permanent** sur 2 des 3 boutons
« Payer en ligne ». `StripeService` ne connaît que `pilot|operations|enterprise`.
Hérité du rename canonique #2977/#3919 : la vitrine a été réalignée (#4209), pas
le dashboard.

## User Stories & Testing

### User Story 1 — Upgrade payant fonctionnel depuis le dashboard (P1)

En tant que manager d'une entreprise, je veux payer en ligne un upgrade depuis
mon dashboard, quel que soit le plan visé.

**Acceptance Scenarios**:
1. Given un clic « Payer en ligne — Pilot », When POST /billing/checkout,
   Then plan=`pilot` (200 → session Stripe).
2. Given un clic « Payer en ligne — Operations », When POST, Then plan=`operations`.
3. Given un clic « Payer en ligne — Enterprise », When POST, Then plan=`enterprise`.
4. Given l'affichage de l'abonnement courant, When plan canonique
   (`free|pilot|operations|enterprise`), Then libellé lisible (Free/Pilot/Operations/Enterprise).

## Requirements

- **FR-001**: `PLAN_LABELS` mappe les 4 codes canoniques ; plus de `starter`/`business`.
- **FR-002**: `handleCheckout` typé `'pilot'|'operations'|'enterprise'` ; boutons alignés.
- **FR-003**: commentaire documentant l'aliasing legacy (#4209).
- **FR-004**: CHANGELOG `### Fixed` ; tsc/eslint/jest/mojibake verts.

## Critères de succès

- **SC-001**: `rg "starter|business"` sur `billing/page.tsx` → 0 hors commentaire.
- **SC-002**: les 3 boutons envoient un code de `PlanCode::values()`.
- **SC-003**: tests front verts.
