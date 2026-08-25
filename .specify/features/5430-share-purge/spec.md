# Feature Specification: Purge des partages de documents expirés (Closes #5430)

**Feature Branch**: `mod/accounting/5430-share-purge`
**Created**: 2026-08-25 | **Status**: In progress
**Issue**: #5430 (P2, backend, ops, compliance)
**Spec**: `.specify/features/5430-share-purge/spec.md`
**Anti-collision**: module `accounting` — sous-domaine **partages**. Dépend du socle partages #5225 (PR #5357) : la table `accounting_document_shares` et `AccountingDocumentShare` doivent être sur main avant le merge de cette spec.

## Contexte

`accounting_document_shares` (partage tokenisé des documents, #5225) porte `expires_at` (défaut 14 j). `DocumentShareService::resolve()` retourne `null` pour un partage expiré mais ne supprime jamais la ligne — les données RGPD inutiles s'accumulent. Aucune purge n'existait (l'audit log #5273 a SA propre purge ; les partages n'en avaient pas).

## User Stories & Testing

### US-1 — Purge des partages expirés au-delà du délai de grâce (P2)

En tant qu'opérateur, je veux purger les partages expirés au-delà d'un délai de grâce de rétention, par entreprise active, sans toucher aux partages valides ni récents.

**Acceptance Scenarios**:
1. Given un partage expiré il y a 40 j, When `accounting:purge-expired-shares --grace-days=30`, Then supprimé.
2. Given un partage expiré il y a 5 j, When la même commande, Then conservé (grâce non atteinte).
3. Given un partage non expiré, When la commande, Then conservé.
4. Given `--dry-run`, Then compteurs affichés, aucune suppression.
5. Given une entreprise inactive avec partage expiré, When la commande, Then son partage est conservé (isolation tenant / entreprises actives uniquement).
6. Given le schedule quotidien, Then la commande est enregistrée (`bootstrap/app.php`, 03:30).

## Requirements

### Functional Requirements

- FR-1 : commande `accounting:purge-expired-shares` (module Accounting, enregistrée dans `AccountingServiceProvider`).
- FR-2 : options `--grace-days` (défaut 30) et `--dry-run`.
- FR-3 : itération des entreprises actives via `TenantManager::withinTenant` (pattern `attendance:auto-close`/`resolveShare`) — jamais de fuite cross-tenant.
- FR-4 : critère : `expires_at < now() - graceDays` ; comptage + suppression par entreprise ; sortie ligne par entreprise + total.
- FR-5 : schedule quotidien `03:30` dans `api/bootstrap/app.php`.

### Non-Requirements

- Pas de changement du mécanisme de résolution (resolve reste inchangé).
- Pas de purge des partages non expirés ni dans la grâce.
- Pas d'UI.

## DoD

- [x] Commande + schedule quotidien, purge uniquement expiré + délai de grâce
- [x] Tests Feature (3 : grâce, dry-run, entreprise inactive) — verts en CI (PHPStan Strict inclus)
- [x] CHANGELOG + docs RUNBOOK (#5276) à jour
