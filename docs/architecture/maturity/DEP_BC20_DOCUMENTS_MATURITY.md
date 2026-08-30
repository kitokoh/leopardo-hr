# DEP-BC20 — Rapport de maturité BC-20 DOCUMENTS

> **Issue :** [DEP-BC20 #5896](https://github.com/kitokoh/leopardo-hr/issues/5896)
> **Contexte :** BC-20 — Documents & Evidence (fichiers, pièces justificatives, scans, rétention, signatures, exports protégés)
> **Date :** 2026-08-30
> **Statut :** **Livré** — cartographie + scorecard 12 dimensions + corrections identifiées (PRs courtes séparées).

## 1. Cartographie de l'existant

| Élément | Emplacement | État |
|---|---|---|
| Dossier employé (checklist documents) | `api/app/Modules/HR/Domain/Models/EmployeeDocument.php` (types : contract_signed, employee_file, career_decision, departure_record, notice_summary, settlement, certificate, other ; statuts : received/uploaded/generated/missing) | Présent |
| Service documents employé | `api/app/Modules/HR/Application/Services/EmployeeDocumentService.php` | Présent |
| API documents employé | `api/routes/modules/hr_extended.php` — `/me/documents` (self), `/employee-documents` CRUD (manager) | Présent |
| Resource API | `api/app/Http/Resources/Api/V1/EmployeeDocumentResource.php` | Présent |
| Cabinet (partage de documents) | `api/app/Modules/Cabinet` — dossiers/documents/partage par token signé, `/cabinet/*` | Présent (active) |
| Module Documents dédié | `api/app/Modules/Documents` | Planifié (registre BC-20) |
| Politique de rétention | `docs/security/POLITIQUE_RETENTION_DOCUMENTS.md` + `docs/security/ACCOUNTING_RETENTION.md` | Présent |
| Runbook fichiers | `docs/ops/RUNBOOK_FILES_CRM.md` (BC-13/14/20 : partage, purge, retention) | Présent |
| Uploads (threat model) | Surface `uploads` au registre MAT-017 (type/taille/MIME, secrets, permissions, audit) | Présent |

## 2. Scorecard des 12 dimensions

| Dimension | Verdict | Preuves / constats |
|---|---|---|
| D1 Domaine/métier | 🟢 Présent | Types et statuts de documents employé explicitement modélisés (enum-like constants), vocabulaire documenté (types/statuts) |
| D2 Données | 🟡 Partiel | Table `employee_documents` tenant-scoped ; **index volumétriques et stratégie de purge à consolider** (recommandation) |
| D3 Tenant | 🟢 Présent | `BelongsToCompany` sur `EmployeeDocument` ; scope `ForEmployee` ; routes tenant-scoped |
| D4 API | 🟢 Présent | CRUD `/employee-documents` + `/me/documents` versionnés sous `/api/v1`, Resource dédiée, OpenAPI couvert (0 drift) |
| D5 Autorisation | 🟢 Présent | Accès manager vs self-service (`/me/*`) ; uploads contrôlés par validation type/taille/MIME (threat model uploads) |
| D6 Transactions | 🟡 Partiel | Opérations unitaires simples ; pas de workflow multi-étape transactionnel identifié |
| D7 Asynchronisme | 🟡 Partiel | Partage par token signé ; **jobs de purge/scan antivirus non identifiés** (recommandation) |
| D8 Sécurité | 🟢 Présent | Validation fichiers (mimes/max), stockage privé, rétention documentée, surface `uploads` au registre MAT-017 (contrôles type_taille_mime/secrets/permissions/audit) |
| D9 Frontend | 🟡 Partiel | Checklist dossier employé exposée via l'API ; écrans de gestion documentaire dans le portail à confirmer (hors périmètre backend) |
| D10 Performance | 🟡 Partiel | **Index et volumétrie (documents signés, scans) à auditer** (recommandation) |
| D11 Exploitation | 🟢 Présent | `RUNBOOK_FILES_CRM.md` couvre partage/purge/retention ; politique de rétention dédiée |
| D12 Produit | 🟡 Partiel | Pas de golden journey documents dédiée (les parcours contrat/paie couvrent partiellement) |

## 3. Risques

1. **Purge/retention non câblée** : la politique `POLITIQUE_RETENTION_DOCUMENTS.md` existe mais aucun job de purge n'est identifié — les documents arrivés en fin de rétention restent stockés (coût + risque RGPD).
2. **Index volumétriques** : les listes de documents par employé passent par `employee_id` ; un index composite tenant-first sur les volumes attendus (scans de contrats) reste à vérifier.
3. **Module Documents planifié** : `api/app/Modules/Documents` reste `planned` au registre — le contexte vit aujourd'hui entre HR (dossier employé) et Cabinet (partage) ; la consolidation est un chantier distinct (hors DEP).
4. **Antivirus/scan** : le threat model mentionne les scans mais aucun hook antivirus n'est identifié dans le code.

## 4. Corrections proposées (PRs courtes séparées)

| Correction | Périmètre | Issue suggérée |
|---|---|---|
| Job de purge rétention + test idempotent | Commande console `documents:purge-expired` (ou équivalent), index sur `(company_id, retention_until)` | follow-up BC-20 |
| Index composite tenant-first sur `employee_documents` | Migration additive + garde collisions | follow-up BC-20 |
| Golden journey documents (upload → partage → purge) | Seed pilote + test Feature | follow-up BC-20 |

## 5. Conclusion

Le contexte BC-20 est **exploitable** : le dossier employé est modélisé, routé, autorisé et couvert par une politique de rétention et un runbook. Les écarts sont des durcissements ciblés (purge, index, golden journey) — aucun correctif bloquant détecté. Aucun code de production modifié dans ce rapport ; les corrections feront l'objet de PRs courtes avec tests.
