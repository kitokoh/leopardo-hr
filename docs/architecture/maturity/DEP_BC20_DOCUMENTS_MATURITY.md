# DEP-BC20 — Rapport de maturité BC-20 DOCUMENTS

> **Issue :** [DEP-BC20 #5896](https://github.com/kitokoh/leopardo-hr/issues/5896)
> **Contexte :** BC-20 — DOCUMENTS (fichiers, pièces justificatives, scans, rétention, antivirus, signatures, exports protégés)
> **Date :** 2026-08-30
> **Statut :** **Actif** — audit 12 dimensions du code sur `main`.

## 1. Cartographie (état `main`)

| Élément | État |
|---|---|
| `api/app/Modules/Cabinet` | 22 fichiers — documents RH/employés, justificatifs |
| `api/app/Modules/HR/Domain/Models/EmployeeDocument.php` | Documents liés aux employés (`employee_documents`) |
| Routes | `/api/v1/cabinet/*` (documents, partages, signatures) |
| Registre BC | `BC-20` = DOCUMENTS, dépendances BC-03 (IDENTITY) / BC-04 (HR) |

Preuves de code : `create_employee_documents_table` (migration tenant), `EmployeeDocument` (PII documents), partage de documents (`SHARE_EXPIRED`, tokens de partage), signatures (`signatures`), rétention documentaire (audit + archive), `ExpenseClaim` pièces justificatives, contrats RH (documents de contrat), portail documents partagés (route publique document partagé listée dans `public-routes-canonical.txt`).

## 2. Scorecard des 12 dimensions

| Dim | Domaine | Verdict | Constat / preuve |
|---|---|---|---|
| D1 | Domaine | 🟢 PRÉSENT | Module Cabinet DDD + modèle EmployeeDocument, vocabulaire documents/partages documenté |
| D2 | Données | 🟢 PRÉSENT | Migrations tenant (employee_documents, cabinet, partages), index tenant-first, garde #1962 vert |
| D3 | Tenant | 🟢 PRÉSENT | Modèles scopés `BelongsToCompany`, partages par token bornés au tenant émetteur, tests cross-tenant |
| D4 | API | 🟢 PRÉSENT | Routes `/api/v1/cabinet/*` versionnées + portail documents partagés (route publique contrôlée, garde #5519), OpenAPI couvert |
| D5 | Autorisation | 🟢 PRÉSENT | Policies documents (propriétaire/manager), partage par token avec expiration (SHARE_EXPIRED), accès employé auto |
| D6 | Transactions | 🟢 PRÉSENT | Upload/remplacement transactionnel, révocation de partage, rétention horodatée |
| D7 | Asynchronisme | 🟡 PARTIEL | Traitement des scans/files via files/queues (upload lourd), pas de pipeline asynchrone dédié antivirus/malware |
| D8 | Sécurité | 🟢 PRÉSENT | PII documents tenant-scopées, tokens de partage éphémères, exports protégés, threat model uploads (`security-threat-models.json`, MAT-017) — **l'analyse antivirus reste à confirmer sur le pipeline de fichiers** |
| D9 | Frontend | 🟢 PRÉSENT | Portail documents (web), pièces justificatives mobiles (hr/manager apps) |
| D10 | Performance | 🟡 PARTIEL | Pagination des listes de documents, index ; budgets p95/p99 non verrouillés (MAT-014) |
| D11 | Exploitation | 🟢 PRÉSENT | Logs structurés + corrélation (MAT-009), audit des accès aux documents, runbooks backup/restore (files) |
| D12 | Produit | 🟢 PRÉSENT | Cycle document couvert (upload → partage → signature → rétention), justificatifs intégrés (absences, notes de frais) |

## 3. Vérification (preuve)

Suites sur `main` : tests documents/cabinet (upload, partage, expiration, signatures), `EmployeeDocument*` (RH), `ExpenseClaimWorkflowTest` (pièces justificatives), garde des routes publiques (`check-public-routes.sh`, portail documents). Gardes locales : registre ✅, migrations ✅, OpenAPI ✅.

## 4. Recommandations (PR futures, non bloquantes)

1. **Analyse antivirus/malware** (D8) : brancher un scan asynchrone sur l'upload (threat model uploads MAT-017) — **finding à lever avant pilote**.
2. **Pipeline fichiers asynchrone** (D7) : traitement des scans en job `TenantScopedJob` (pattern EdgeSync).
3. **Budgets performance** (D10) : verrouiller les listes de documents (MAT-014).
4. **Rétention automatique** (D2/D8) : job de purge/archivage selon la politique RGPD (EDU-019 pattern).

## 5. Non-régression

Aucun changement de code de production dans ce rapport — audit + documentation uniquement. CRM commercial plateforme intact.
