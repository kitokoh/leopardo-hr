# Feature Specification: Résolution O(1) du token de partage (Closes #5428)

**Feature Branch**: `mod/accounting/5428-share-lookup`
**Created**: 2026-08-25 | **Status**: In progress
**Issue**: #5428 (P1, backend, perf, security)
**Spec**: `.specify/features/5428-share-lookup/spec.md`
**Anti-collision**: module `accounting` — sous-domaine **partages**. Dépend du socle partages #5225 (PR #5357) et de la purge #5430 (PR #5469) : cette branche est issue de `mod/accounting/5430-share-purge`.

## Contexte

`PublicDocumentShareController::resolveShare()` (endpoints PUBLICS `GET /api/v1/accounting/documents/shared/{token}` et `/download`, #5225) résolvait le token en itérant TOUTES les compagnies actives (O(N) bascules de `search_path` par requête publique). La table `accounting_document_shares` étant tenant-scoped, il n'existait pas de lookup global.

## User Stories & Testing

### US-1 — Résolution en O(1) (P1)

En tant qu'API publique, je veux résoudre un token de partage sans itérer toutes les entreprises.

**Acceptance Scenarios**:
1. Given 5 entreprises actives avec partages, When résolution du token de l'une, Then nombre de requêtes CONSTANT (< 12) — prouvé par test de comptage (`DB::listen`).
2. Given un token inconnu, Then 404 (aucune requête tenant).
3. Given un partage expiré, Then 404 (comportement inchangé).

### US-2 — Lookup cohérent (P2)

1. Given la création d'un partage, Then une ligne `document_share_lookup` (public) est créée.
2. Given la purge des partages expirés (#5430), Then les lignes lookup correspondantes sont supprimées.
3. Given la suppression d'une compagnie, Then ses lookups sont supprimés (FK cascade).

## Requirements

### Functional Requirements

- FR-1 : migration PUBLIQUE `document_share_lookup` (share_token PK, company_id FK → companies, cascade) — nommage conforme #5431 (`2026_08_25_000001_5428_...`).
- FR-2 : modèle `DocumentShareLookup` (public, sans scope tenant, aucune donnée du document — RGPD).
- FR-3 : `DocumentShareService::createShare` écrit le lookup.
- FR-4 : `PublicDocumentShareController::resolveShare` : lookup → company → `withinTenant` unique → résolution tenant-scoped (comportement RGPD inchangé).
- FR-5 : `accounting:purge-expired-shares` (#5430) purge aussi le lookup.

### Non-Requirements

- Pas de changement du contrat API (réponses/messages inchangés).
- Pas de donnée du document dans le lookup.
- Pas de multi-schémas : le lookup est public, la résolution reste tenant-scoped.

## DoD

- [x] Résolution O(1) prouvée par test de comptage (5 compagnies, < 12 requêtes)
- [x] Lookup créé à la création du partage, purgé avec le partage
- [x] Tests Feature verts (4) + non-régression DocumentShareEmailTest, PHPStan Strict 0 erreur
- [x] CHANGELOG + spec
