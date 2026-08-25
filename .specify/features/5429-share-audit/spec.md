# Feature Specification: Audit RGPD des accès au portail + Referrer-Policy no-referrer (Closes #5429)

**Feature Branch**: `mod/accounting/5429-share-audit`
**Created**: 2026-08-25 | **Status**: In progress
**Issue**: #5429 (P2, security, compliance, web)
**Spec**: `.specify/features/5429-share-audit/spec.md`
**Anti-collision**: module `accounting` + front/web — chaînée sur #5428 (lookup) → #5430 (purge) → #5357 (socle partages).

## Contexte

Le portail client (endpoints publics info/download, #5225) n'était **pas tracé** (RGPD : qui a consulté/téléchargé quoi, quand, depuis quelle IP) et l'URL contient un **token dans le path** : le `Referrer-Policy` global (`strict-origin-when-cross-origin`) transmet l'URL complète — donc le token — en `Referer` aux hôtes tiers.

## User Stories & Testing

### US-1 — Audit des accès (P1)

1. Given un accès `info` au portail, Then une entrée `audit_logs` (action `accounting.share.info`, company, share, ip, user_agent, token en metadata) est créée dans le tenant du partage.
2. Given un accès `download`, Then entrée `accounting.share.download` avec metadata token.
3. Given un token inconnu (404), Then aucune entrée d'audit.

### US-2 — Referrer-Policy (P1)

1. Given la route `/documents/shared/{token}` (front/web), Then `Referrer-Policy: no-referrer` (règle dédiée APRÈS la règle générique dans next.config).

## Requirements

- FR-1 : `AuditLog::create` (user_id null — accès public) dans `PublicDocumentShareController` pour info + download, dans le tenant de la compagnie du partage.
- FR-2 : metadata = share_token ; ip + user_agent bornés.
- FR-3 : règle `next.config.ts` source `/documents/shared/:path*` → `Referrer-Policy: no-referrer` (priorité sur la règle générique).
- Non-objectif : pas de changement du contrat API.

## DoD

- [x] Accès info/download tracés (tests Feature ×3)
- [x] `no-referrer` posé sur `/documents/shared/*` (config next)
- [x] CHANGELOG + spec
