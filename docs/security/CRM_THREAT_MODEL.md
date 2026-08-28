# Threat Model — Module CRM Client (tenant)

- **Statut :** actif — livrable #5731 (CRM-V1-15, hardening & pilote)
- **Date :** 2026-08-28
- **Références :** `docs/architecture/refactoring/ADR-CRM-DUAL-CONTEXTS.md`, `docs/specifications/MODULE_CRM_INTERNE_CLIENT.md`, `docs/specifications/PLAN-V0-V1-CRM-CLIENT-SQL-INTEGRATIONS.md`
- **Périmètre :** module `App\Modules\CRM` (CRM client tenant), routes `/api/v1/crm/*`, espaces client web/mobile, jobs d'import/export, canaux de communication (#5725/#5726/#5727).

> Objectif : documenter les acteurs, actifs, menaces et contrôles du CRM
> client. Les tests négatifs correspondants vivent dans
> `api/tests/Feature/CRM/CrmHardeningNegativeTest.php` (issue #5731).

---

## 1. Acteurs et frontières de confiance

| Acteur | Périmètre | Confiance |
|---|---|---|
| Manager tenant (`principal`/`rh`) | CRUD complet + conversion + fusion + imports | haute (tenant) |
| Employé tenant (rôle CRM autorisé) | Lecture/activités limitées (Policies #5711) | partielle |
| Employé tenant (autre rôle) | Aucun accès CRM | nulle |
| Super-admin plateforme | Aucun accès aux routes tenant `/crm/*` (ADR-CRM-002) | nulle sur CRM client |
| Ancien employé / token révoqué | Aucun (Sanctum + revocation) | nulle |
| Attaquant externe (internet) | Routes publiques uniquement (`/health`, docs) | nulle |

Frontière critique : **le tenant est la frontière de sécurité**. Toute donnée
CRM appartient à exactement un tenant (`company_id` non nullable, scope
global `BelongsToCompany`, 404 cross-tenant).

## 2. Actifs à protéger

1. **PII** : emails, téléphones, notes privées des accounts/contacts/leads
   (chiffrés au repos via `SensitiveDataEncryptor` / casts `encrypted`, #5713).
2. **Données commerciales tenant** : pipelines, stages, opportunités,
   montants, segments, campagnes.
3. **Clés d'idempotence / tokens d'import** : `crm_lead_conversions`,
   `crm_imports.token` (permet un commit) — élévation de privilège si fuite.
4. **Registre RGPD** : consentements et préférences de communication (#5722).
5. **Canal sortant** : webhooks WhatsApp/email/SMS (#5725/#5726/#5727) —
   abus = coût, réputation, spam.

## 3. Menaces et contrôles

### T1 — Fuite cross-tenant (CRITIQUE)
- **Scénario** : un employé du tenant A accède aux données du tenant B en
  manipulant les IDs (`/crm/leads/{id}`, `/crm/imports/{id}/commit`,
  `/crm/duplicates/merge`, `{pipeline}`…).
- **Contrôles** : scope global `BelongsToCompany` (fail-closed #3727),
  Policies par entité avec comparaison `company_id`, test 404 cross-tenant
  sur CHAQUE endpoint sensible (CrmHardeningNegativeTest), index
  `(company_id, …)` sur toutes les tables.
- **Test négatif** : `test_cross_tenant_access_returns_404_on_all_sensitive_routes`.

### T2 — Injection SQL / entrées malveillantes
- **Scénario** : `sort_by`, filtres, statuts, tailles de page ou CSV
  injectés (`' OR 1=1 --`, formules `=HYPERLINK(...)`).
- **Contrôles** : whitelists de tris/filtres/statuts (`Rule\In`, enums),
  pagination plafonnée, Request dédiées (ADR-CRM-005), neutralisation des
  formules CSV à l'import (#5714), requêtes Eloquent scopées (jamais de
  `DB::raw` sur entrée client).
- **Test négatif** : `test_unknown_sort_and_filters_are_rejected`, `test_csv_formula_injection_is_neutralized`.

### T3 — PII exposée / sur-sérialisation
- **Scénario** : une Resource renvoie email/phone/notes en clair à un rôle
  non autorisé, ou un log/dump expose les valeurs déchiffrées.
- **Contrôles** : casts `encrypted` au repos, Resources avec PII masquée
  (ADR-CRM-005), `$hidden` sur les modèles, logs sans PII (jamais
  d'email/téléphone dans les messages d'erreur ni `crm_imports.errors`).
- **Test négatif** : `test_pii_never_appears_in_plaintext_responses`.

### T4 — Idempotence brisée / doublons
- **Scénario** : rejeu réseau d'un `convert`, double `commit` d'import,
  retry de job → doublons d'agrégats ou double envoi canal.
- **Contrôles** : `crm_lead_conversions` UNIQUE (company_id, idempotency_key)
  + UNIQUE PARTIEL (company_id, lead_id) WHERE status='succeeded' (#5717) ;
  verrou atomique `previewed→processing` + rejeu idempotent (#5714) ;
  `WebhookEventRegistry` pour les webhooks entrants (idempotence #5444).
- **Test négatif** : `test_duplicate_conversion_and_commit_are_rejected`.

### T5 — Abus des jobs/queue (imports, exports, canaux)
- **Scénario** : envoi de masse de fichiers/imports, pic webhook → charge
  queue, perte de jobs, double envoi.
- **Contrôles** : bornes strictes à l'import (taille/lignes/colonnes),
  quotas mensuels SMS/WhatsApp (`0` = illimité, CommunicationService),
  jobs idempotents + `tries` bornés, surveillance queue
  (`queue:health-check`, runbook #4340).
- **Test négatif** : `test_import_file_bounds_are_enforced`.

### T6 — Élévation de rôle (RBAC)
- **Scénario** : un employé ordinaire exécute `convert`/`merge`/`commit`
  (actions à permission élevée).
- **Contrôles** : Policies `CrmLeadPolicy::convert`, `CrmImportPolicy`,
  `CrmDuplicatePolicy` (manager `principal`/`rh` uniquement), jamais de
  garde inline (constitution §V).
- **Test négatif** : `test_employee_role_is_forbidden_on_privileged_actions`.

### T7 — Fuite via le CRM commercial Platform/Marketing
- **Scénario** : le programme CRM client touche le pipeline d'acquisition
  Leopardo (`public.marketing_leads`, `company_requests`, UI admin
  `/crm/pipeline`).
- **Contrôles** : ADR-CRM-001/002 (contextes séparés), garde d'isolation
  #5584 (aucun import `CRM → Platform/Marketing`), non-régression #5716
  (#5758), routes `/crm/*` distinctes des routes `/platform/*`.
- **Test négatif** : `test_crm_routes_never_touch_platform_pipeline` (contract test #5712).

## 4. Matrice de risques

| # | Menace | Impact | Probabilité | Contrôle principal | Statut |
|---|---|---|---|---|---|
| T1 | Cross-tenant | Critique | Moyenne | Scope global + 404 tests | ✅ socle V0 |
| T2 | Injection | Élevé | Moyenne | Whitelists + neutralisation | ✅ socle V0 |
| T3 | PII | Critique | Moyenne | Chiffrement + masquage | ✅ #5713 |
| T4 | Idempotence | Élevé | Moyenne | Contraintes UNIQUE + verrous | ✅ #5714/#5717 |
| T5 | Abus queue | Moyen | Faible | Bornes + quotas + jobs idempotents | 🟡 à verrouiller (#5731) |
| T6 | RBAC | Élevé | Faible | Policies dédiées | ✅ #5711 |
| T7 | Plateforme | Critique | Faible | Isolation #5584 + non-régression | ✅ #5716 |

## 5. Tests négatifs (issue #5731)

Le fichier `api/tests/Feature/CRM/CrmHardeningNegativeTest.php` verrouille
les scénarios T1-T6 contre la surface API réelle (contrats #5712). Il est
exécuté par la suite Feature standard et doit rester vert à chaque PR CRM.

## 6. Revues et mises à jour

- Ce threat model est revu à chaque nouveau lot V1 touchant une frontière
  (canaux, automatisations, exports) ou à chaque amendement de l'ADR.
- Toute nouvelle route `/api/v1/crm/*` doit être ajoutée aux tests négatifs
  (cross-tenant + RBAC + entrées strictes) en même temps que son implémentation.
