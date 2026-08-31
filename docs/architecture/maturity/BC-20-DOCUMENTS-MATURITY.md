# BC-20 — Documents & Evidence — Rapport de maturité (DEP-BC20)

- **Statut :** PARTIAL → corrections livrées (#5896)
- **Date :** 2026-08-29
- **Agent propriétaire :** 20 (Documents & Evidence)
- **Référentiel :** `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` §BC-20
- **Périmètre :** assets, pièces, preuves, scans, signatures, exports, rétention

## Cartographie de l'existant

| Brique | Composant |
|---|---|
| Documents comptables | `AccountingDocument` (+ lignes, workflow, numérotation, PDF `DocumentPdfRenderer`, emailing, conversion devise) |
| Partage tokenisé | `DocumentShareService` (token aléatoire + expiration 14 j, résolution stricte), `AccountingDocumentShare`, purge `PurgeExpiredDocumentSharesCommand` — pattern `CabinetShare` (#1817/#5225) |
| Cabinet employé | `CabinetDocument`/`CabinetFolder`/`CabinetShare` — upload MIME allowlisté (`StoreDocumentRequest` : max 20 Mo, `mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,odt,ods,odp,txt,csv,png,jpg,jpeg,webp`), `read_only` (bulletins archivés), cross-tenant 404 |
| Autres uploads | Recrutement (CV pdf/doc max 5 Mo), Notification (pièces jointes max 5 Mo), Paie (justificatifs jpg/png/pdf/heic), HR (imports csv), CRM (imports csv) |
| Stockage | Disque `local` hors webroot (sauf branding `public` par dossier tenant UUID) |

## Audit des douze dimensions

| Dim | Statut | Preuve / Lacune |
|---|---|---|
| D1 Domaine | **PRESENT** | Vocabulaire documenté (`COMPTABILITE_CONCEPTION.md`, workflow #5225) |
| D2 Données | **PRESENT** | Migrations tenant (documents, lignes, partages), contraintes/index, `read_only`, rétention via purge des partages expirés |
| D3 Tenant | **PRESENT** | `BelongsToCompany` + `authorizeOwnership` fail-closed (404 cross-tenant, revue #5445) ; partages résolus hors scope company mais vérifiés par token+expiration |
| D4 API | **PRESENT** | Routes versionnées, Requests strictes (MIME/taille), Resources allowlistées, OpenAPI |
| D5 Autorisation | **PRESENT** | Ownership par employé/company, 401/403/404 testés (`CabinetDocumentControllerTest`) |
| D6 Transactions | **PRESENT** | Workflow de documents (statuts, transitions validées `InvalidDocumentTransitionException`), idempotence de numérotation |
| D7 Asynchronisme | **PARTIAL** | `GenerateDocumentPdf` en job ; partages emailés — pas de file dédiée documents |
| D8 Sécurité | **PARTIAL→CORRIGÉ** | MIME/taille allowlistés, pas de PII dans les logs. **Lacune corrigée : piste d'audit des opérations Cabinet** (upload/suppression/déplacement) — la suppression n'était pas auditable |
| D9 Frontends | **PRESENT** | Portail partagé `/documents/shared/{token}`, états UI |
| D10 Performance | **PARTIAL** | Pagination bornée (≤ 100) ; PDF générés en job |
| D11 Exploitation | **PRESENT** | Purge des partages expirés (commande), runbook fichiers (`RUNBOOK_FILES_CRM.md` pattern) |
| D12 Produit | **PARTIAL** | Parcours document comptable → partage → portail ; recette pilote |

## Corrections livrées dans cette PR

1. **Piste d'audit du Cabinet (D8)** — `CabinetAuditLogger` :
   - `cabinet.document.uploaded` : métadonnées (nom, taille, MIME) ;
   - `cabinet.document.deleted` : écrit **avant** suppression (références
     conservées : nom, chemin, taille, MIME) ;
   - `cabinet.document.moved` : ancien/nouveau dossier ;
   - la journalisation n'échoue jamais l'opération métier (report + absorbée) ;
   - un refus (document `read_only`) ne produit **aucune** entrée.
   Tests `CabinetDocumentAuditTest` (4) : upload audité, suppression auditable
   avant suppression, déplacement audité, refus read_only non audité.

## Sortie exigée par le backlog

- [x] Un fichier d'un tenant est inaccessible à un autre (404 fail-closed, testé)
- [x] Un fichier malveillant est bloqué (MIME/taille allowlistés par Request)
- [x] Une URL expire (token + expiration 14 j, purge commandée)
- [x] Une suppression respecte l'audit nécessaire (**nouveau** : audit Cabinet)

## Reste à faire (hors périmètre de cette PR courte)

- Chiffrement au repos des fichiers (S3 SSE / encrypt) et URLs temporaires signées
- Antivirus / scan des uploads (dépendance externe)
- Versionnement des documents (nouvelles versions auditées)
