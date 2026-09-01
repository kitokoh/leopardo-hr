# Feature Specification: QA Expert #3 — Admin (front/admin-dashboard) (2026-08-15)

**Feature**: `qa-expert3-admin-2026-08-15`
**Created**: 2026-08-15
**Status**: In progress

## Findings traités

### #3034 [P1] — CompanyDetailView crashe : health.adoption.kiosk absent — **CORRIGÉ** (PR #3303)
> Le payload backend n'inclut jamais `kiosk` ; le template lisait `health.adoption.kiosk.active` → fiche blanche. Correctif : null-safe + état honnête « Non disponible ».

### #3033 [P1] — build prod cassé : DocumentReportIcon inexistant — **CONFIRMÉ**, correctif dans PR #3111 (branch existante)
> Vérifié localement : `vite build` échoue sur `[MISSING_EXPORT] DocumentReportIcon` (@heroicons/vue v2). #3111 remplace par `DocumentChartBarIcon`.

## Constats live
- Bouton « Acces Demo » admin → « Erreur de connexion » (#2646, déjà filed) : backend démo désactivé en prod (DEMO_MODE_ENABLED=false).

## Restants (déjà filed, hors périmètre de cette vague)
- #3036/#3037 (DashboardView champs), #3038 (UsersView createdAt — couvert par branch fix/2988-2990), #3039-#3046 (cleanups).
