# Plan — CRM Client Leopardo V0 & V1

- **Statut :** actif — référentiel du programme CRM client (issues #5705 → #5731)
- **Date :** 2026-08-28
- **ADR :** `docs/architecture/refactoring/ADR-CRM-DUAL-CONTEXTS.md`
- **Module :** `docs/specifications/MODULE_CRM_INTERNE_CLIENT.md`

> Ce document est le plan maître. Chaque issue du programme y référence son
> lot, ses dépendances et ses livrables. Toute évolution du périmètre passe
> par une mise à jour de ce plan **et** de l'ADR.

---

## 1. Vision

Livrer aux entreprises clientes Leopardo un CRM commercial **tenant-scoped**,
multi-device (web + mobile terrain), avec un socle V0 sûr (données, API,
contrôles, isolation) puis des capacités V1 (automatisation, canaux,
segmentation, dashboarding).

Le CRM commercial Leopardo (`Platform`/`Marketing`, pipeline d'acquisition
interne) reste **strictement séparé** — voir ADR-CRM-001/002.

## 2. Contexte technique

- Backend : Laravel 12 (PHP 8.4), PostgreSQL multi-tenant (`search_path`).
- Module DDD : `api/app/Modules/CRM/` (Application/Domain/Infrastructure/Interfaces/Providers).
- Migrations : `api/database/migrations/tenant/` — nommage `YYYY_MM_DD_0000NN_<issue>_<slug>.php`,
  garde anti-collision (#1962/#5431) avant push.
- Isolation : `company_id` uuid non nullable, `AbstractTenantModel`, Policies,
  tests cross-tenant 404 obligatoires.
- Contrats : OpenAPI `api/openapi.yaml` maintenu à jour à chaque endpoint
  (garde `check-openapi-route-coverage.py`), SDK régénérés.
- Registre RGPD : `docs/security/REGISTRE_TRAITEMENTS_DONNEES_RH.md` complété
  pour toute donnée PII (#5713).
- Le contexte de sécurité (middleware, search_path, jobs/events/cache) est
  verrouillé dans CRM-V0-02 (#5706) et **précède** toute donnée métier.

## 3. Périmètre du programme

### V0 — Socle sûr (issues #5705 → #5716)

| # | Lot | Issue | Livrables clés |
|---|---|---|---|
| CRM-V0-01 | Contexte & gouvernance | #5705 | ADR dual-contexts, specs, flux activation tenant |
| CRM-V0-02 | Contexte tenant | #5706 | Rapport conventions tenant, test 404 cross-tenant, jobs/events/cache tenant |
| CRM-V0-03 | Squelette DDD | #5707 | Module `App\Modules\CRM` structurellement vert |
| CRM-V0-04 | Migrations accounts/contacts | #5708 | Tables tenant `crm_accounts`, `crm_contacts` |
| CRM-V0-05 | Migrations leads/pipelines/opportunities | #5709 | Tables `crm_leads`, `crm_pipelines`, `crm_opportunities` |
| CRM-V0-06 | Activities/tasks/ownership | #5710 | `crm_activities`, `crm_tasks`, ownership + Policies |
| CRM-V0-07 | Policies & validation stricte | #5711 | Policies CRUD, validation entrées, contrôle filtres/tris/tailles |
| CRM-V0-08 | Contrats API & OpenAPI | #5712 | Routes `/api/v1/crm/*`, OpenAPI complet, SDK |
| CRM-V0-09 | PII, HMAC, RGPD | #5713 | Chiffrement PII (SensitiveDataEncryptor), HMAC, registre RGPD, audit |
| CRM-V0-10 | Import CSV sécurisé | #5714 | Import CSV validé, contrôlé, journalisé |
| CRM-V0-11 | UI web CRM minimale | #5715 | Espace client web : CRUD comptes/contacts/leads |
| CRM-V0-12 | Non-régression CRM commercial | #5716 | Garde de non-régression Platform/Marketing |

### V1 — Valeur (issues #5717 → #5731)

| # | Lot | Issue | Livrables clés |
|---|---|---|---|
| CRM-V1-01 | Lead → Account/Contact/Opportunity | #5717 | Conversion guidée + transactionnelle |
| CRM-V1-02 | Déduplication & fusion | #5718 | Détection doublons, fusion supervisée |
| CRM-V1-03 | Recherche tenant-scoped | #5719 | Recherche unifiée comptes/contacts/leads/opportunités |
| CRM-V1-04 | Timeline, tâches, relances | #5720 | Timeline par entité, tâches, relances opérationnelles |
| CRM-V1-05 | Dashboard pipeline & qualité | #5721 | KPIs pipeline, score qualité des données |
| CRM-V1-06 | Consentements & préférences | #5722 | Consentements de communication par contact |
| CRM-V1-07 | Segments simples | #5723 | Segments tenant par critères |
| CRM-V1-08 | Campagnes marketing tenant | #5724 | Campagnes sur segments |
| CRM-V1-09 | WhatsApp Business officiel | #5725 | Intégration canal WhatsApp (Cloud API) |
| CRM-V1-10 | Email transactionnel & marketing | #5726 | Envois contrôlés (opt-in, quotas) |
| CRM-V1-11 | SMS / canaux adaptateurs | #5727 | Abstractions de canaux, adaptateur SMS |
| CRM-V1-12 | Automatisations simples | #5728 | Règles déclencheur/action tenant |
| CRM-V1-13 | Imports/exports & read models | #5729 | Exports avancés, read models d'analyse |
| CRM-V1-14 | Mobile terrain | #5730 | Client mobile CRM ciblé (champ) |
| CRM-V1-15 | Hardening & pilote | #5731 | Charge, revue sécurité, pilote tenant |

## 4. Règles transverses (toutes les issues)

1. **Spec-first** : toute issue s'appuie sur ce plan + la spec module ; pas de code sans validation.
2. **Anti-doublon** : vérifier branches (`grep <issue>`), PRs et assignees avant de commencer ; marker branch immédiat (protocole #2400).
3. **Tenant** : `company_id` non nullable, requêtes scopées, test 404 cross-tenant par endpoint sensible.
4. **Entrées strictes** : champs inconnus rejetés, statuts/filtres/tris whitelistés, pagination plafonnée, fichiers contrôlés.
5. **PII** : chiffrement au repos, HMAC sur identifiants sensibles si nécessaire, registre RGPD, audit des mutations sensibles.
6. **Migrations** : garde `dev-hub/tools/check-migration-basename-collisions.sh` avant push (#1962).
7. **Qualité** : PHPStan strict level 8 vert, tests avant implémentation, CHANGELOG.md mis à jour, OpenAPI maintenu.
8. **CI** : checks requis verts avant merge ; `Closes #N` dans le body de chaque PR.
9. **N+1** : eager loading systématique sur les index (comptes → contacts primaires, opportunités → pipeline).

## 5. Dépendances d'implémentation

```
CRM-V0-01 (#5705) ──▶ CRM-V0-02 (#5706) ──▶ CRM-V0-03 (#5707) ──▶ CRM-V0-04 (#5708)
                                                        │
                                                        ├──▶ CRM-V0-05 (#5709) ──▶ CRM-V0-06 (#5710) ──▶ CRM-V0-07 (#5711)
                                                        │                                                      │
                                                        └──▶ CRM-V0-08 (#5712) ◀───────────────────────────────┘
                                                                 │
                        ┌────────────────────────────────────────┤
                        ▼                                        ▼
                 CRM-V0-09 (#5713)                        CRM-V0-10 (#5714)
                        │                                        │
                        └──────────────┬─────────────────────────┘
                                       ▼
                                CRM-V0-11 (#5715)
                                       │
                                CRM-V0-12 (#5716)  (non-régression, peut courir en parallèle)
                                       │
                 ┌─────────────────────┼─────────────────────┐
                 ▼                     ▼                     ▼
           CRM-V1-01 (#5717)    CRM-V1-02 (#5718)     CRM-V1-03 (#5719)   … (V1 dépend de V0 complet)
```

Les lots V1 s'appuient tous sur le socle V0 ; leur ordre relatif reste
flexible pour le travail en parallèle, sous réserve de respecter les
dépendances de données (ex. #5722 consentements avant #5724 campagnes).

## 6. Définition of Done (toutes issues)

- Tests écrits avant l'implémentation (logique métier) ; suite Feature verte.
- PHPStan strict 0 erreur, Pint conforme.
- Contrôles tenant + validation d'entrée + absence de N+1 critique.
- OpenAPI à jour (si API), CHANGELOG.md mis à jour.
- Checks CI requis verts ; PR mergée avec `Closes #N` ; branche supprimée.
