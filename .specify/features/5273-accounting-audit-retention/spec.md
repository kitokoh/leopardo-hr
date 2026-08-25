# Feature Specification: Audit log + rétention RGPD + purge Comptabilité (Closes #5273)

**Feature Branch**: `mod/accounting/5273-audit-retention`
**Created**: 2026-08-23 | **Status**: Draft → In progress
**Issue**: #5273 (P2, security, compliance, Comptabilité Phase A)
**Spec**: `.specify/features/5273-accounting-audit-retention/spec.md`
**Anti-collision**: module `accounting` — sous-domaine **audit/conformité**. Branches en vol : #5222 (contacts), #5223 (documents, moi), #5224 (PDF) — fichiers distincts.

## Contexte

Les données financières sont sensibles : traçabilité totale et conformité RGPD / loi algérienne 18-07. Le socle #5221 fournit les casts `encrypted`/`encrypted:array` (tax_id, reference, metadata) et les FK cascade (lignes/paiements). Les actions documents (#5223) sont déjà journalisées via `DataAccessAuditLogger` (événements `accounting.document_*`). Il reste : la consultation scope module, la politique de rétention + purge, et la preuve du chiffrement au repos.

## User Stories & Testing

### US-1 — Audit trail comptable consultable (P1)

En tant que comptable/principal, je veux voir qui a fait quoi, quand, sur les documents comptables (création, envoi, paiement, annulation, avoir).

1. Given des actions comptables effectuées via l'API, Then des lignes `audit_logs` existent avec `metadata.resource = accounting.document_*`, l'acteur et le tenant.
2. Given `GET /accounting/audit-logs`, Then seuls les événements `accounting.*` du tenant sont retournés (paginés, filtres action/user/date).
3. Given un employé non-manager, Then 403 ; cross-tenant, Then 404/0.

### US-2 — Rétention légale + purge (P1)

1. Given un document finalisé (paid/cancelled/overdue) plus vieux que la rétention (défaut 120 mois = 10 ans), When `accounting:purge-expired`, Then supprimé avec ses lignes et paiements (cascade).
2. Given un document récent ou non finalisé (draft/sent/partially_paid), Then conservé.
3. Given `--dry-run`, Then aucun document supprimé, rapport uniquement.
4. Given un PDF archivé (`pdf_path`), Then le fichier storage est aussi supprimé.

### US-3 — Chiffrement au repos (P2)

1. Given une ligne contact (tax_id), paiement (reference), document (metadata), Then la valeur brute en base est chiffrée (enveloppe Laravel `eyJpdiI6`) et le modèle déchiffre à la lecture (round-trip).

## Requirements

- **FR-001**: `AccountingAuditController::index` — `GET /accounting/audit-logs` (RBAC principal/comptable, tenant scope, filtres `action`/`resource`/`user_id`/`from`/`to`, pagination).
- **FR-002**: commande `accounting:purge-expired` (`--older-than` mois, défaut `config('accounting.retention_months', 120)`, `--dry-run`) — purge des documents finalisés antérieurs au cutoff, cascade, suppression des PDF storage, rapport.
- **FR-003**: `config/accounting.php` (rétention par défaut) + `docs/security/ACCOUNTING_RETENTION.md` (délais légaux documentés).
- **FR-004**: tests — audit trail complet (qui/quoi/quand), endpoint scope module + RBAC/tenant, purge (cutoff, finalisés seulement, dry-run, PDF), chiffrement au repos (reference/metadata complétés).
- **FR-005**: OpenAPI (chemin audit-logs), matrices frontend/RBAC, CHANGELOG, spec `.specify`, `docs/specifications/ISSUE_5273.md`.

## Success Criteria

- **SC-001**: audit trail complet testé (US-1) ; **SC-002**: politique de rétention documentée (US-2) ; **SC-003**: chiffrement au repos prouvé par tests (US-3) ; **SC-004**: PHPStan strict 0, Pint PASS, tests Accounting verts, OpenAPI 0 drift.
