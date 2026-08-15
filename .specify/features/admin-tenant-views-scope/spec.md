# Feature Specification: Dashboard super-admin — masquer les vues tenant-scopées

**Feature Branch**: `fix/2272-admin-tenant-views-scope`
**Created**: 2026-08-14 | **Status**: Draft → Implemented
**Issue**: #2272

## Contexte
12+ routes du dashboard admin sont des fonctionnalités tenant (`auth:sanctum` + middleware tenant) → token super-admin = 401 systématique. Vues : payroll, leaves, contracts, recruitment, training, fleet, chat, webhooks, exports, reports, predictions, audit, settings/payroll/* (si tenant).

## User Stories & Testing

### User Story 1 — La nav super-admin ne montre que des surfaces fonctionnelles (P1)
**Acceptance Scenarios**:
1. Given la sidebar super-admin, When navigation, Then chaque entrée mène à une vue qui fonctionne avec un token super-admin.
2. Given les vues tenant (payroll, leaves, …), When on y accède directement par URL, Then redirection propre vers /dashboard (ou message explicite), jamais une page qui échoue en 401 muet.

### User Story 2 — Le code garde la trace des routes tenant (P2)
**Acceptance Scenarios**:
1. Given le router, When on lit les métadonnées de route, Then un marqueur `requiresTenant: true` (ou équivalent) documente le besoin tenant.
