# ADR — Les deux bounded contexts CRM de Leopardo

- **Statut :** ratifié — validé pour implémentation (issue #5705, CRM-V0-01)
- **Date :** 2026-08-28
- **Périmètre :** API Laravel (modules `Platform`, `Marketing`, nouveau module `CRM`), front admin-dashboard, espaces client web/mobile, CI/CD
- **Décideurs attendus :** responsables produit, backend, frontend, mobile, sécurité
- **Issue de référence :** #5705 — « Ratifier les deux bounded contexts CRM »
- **Docs liées :** `docs/specifications/PLAN-V0-V1-CRM-CLIENT-SQL-INTEGRATIONS.md`, `docs/specifications/MODULE_CRM_INTERNE_CLIENT.md`

---

## Contexte

Le terme « CRM » recouvre aujourd'hui deux besoins fondamentalement différents, aujourd'hui confondus dans le dépôt :

1. **Le CRM commercial de Leopardo** : le pipeline d'acquisition des prospects Leopardo (lead marketing → demande d'essai → essai → client actif). C'est un outil **interne à la plateforme**, utilisé par l'équipe Leopardo, non par les entreprises clientes.
2. **Le CRM client** : un module CRM livré **aux entreprises clientes** pour gérer leurs propres prospects, comptes, contacts, opportunités, tâches et campagnes. C'est un produit multi-tenant, comme les modules HR, Paie ou Comptabilité.

État actuel du CRM commercial (existant, à **ne pas remplacer**) :

| Élément | Emplacement actuel |
|---|---|
| Pipeline plateforme | `PlatformCrmPipelineController` — `GET /api/v1/platform/crm/pipeline` (admin plateforme) |
| Lead marketing | `MarketingLead` — table `public.marketing_leads` (schéma public, non-tenant) |
| Demandes d'essai | `CompanyRequest` — table `public.company_requests` |
| UI admin | `front/admin-dashboard/src/views/crm/CrmPipelineView.vue` (`/crm/pipeline`) |

Le CRM client n'existe **pas encore** : aucune table tenant, aucun module DDD, aucune route API tenant. C'est l'objet du programme V0/V1 décrit dans `PLAN-V0-V1-CRM-CLIENT-SQL-INTEGRATIONS.md`.

## Décisions

### ADR-CRM-001 — Deux bounded contexts distincts, deux owners, zéro fusion

| | **CRM Commercial Leopardo** | **CRM Client (tenant)** |
|---|---|---|
| Bounded context | `Platform` + `Marketing` (existant) | nouveau module DDD `App\Modules\CRM` |
| Owner | Équipe Plateforme/Marketing Leopardo | L'entreprise cliente (tenant) + équipe Leopardo pour la plateforme produit |
| Schéma PostgreSQL | `public` | schéma tenant (via `search_path` / `BelongsToCompany`) |
| Isolation | Non-tenant par nature (leads ≠ entreprise) | Tenant strict — `company_id` non nullable sur toute donnée |
| UI | `front/admin-dashboard` (admin Leopardo) | Espaces client (web + mobile terrain) |
| Cycle de vie | Pipeline d'acquisition Leopardo | Cycle de vente de l'entreprise cliente |

Le module `CRM` **n'importe jamais** les agrégats de `Platform`/`Marketing` (interdit par la garde d'isolation #5584). Le CRM commercial **n'est pas étendu** par le programme client : les deux contextes restent des produits séparés qui ne partagent que le tenant (la société cliente) comme clé de rattachement, jamais comme structure de données.

### ADR-CRM-002 — Routes, tables, menus et permissions strictement séparés

- **Routes** : les routes du CRM client vivent sous `/api/v1/crm/*` dans un fichier de routes dédié (`api/routes/modules/crm.php`), chargé dans le groupe `api.manager` (et déclinaisons par rôle via Policies) — jamais dans le fichier des routes plateforme.
- **Tables** : toutes les tables du CRM client sont créées dans `api/database/migrations/tenant/` (schéma tenant). Les tables `public.marketing_leads`, `public.company_requests` et `public.company_requests` (CRM commercial) ne sont pas modifiées par le programme CRM client.
- **Menus** : l'UI du CRM client est rendue dans les espaces client (web + mobile). L'entrée `/crm/pipeline` de l'admin-dashboard reste l'UI du CRM **commercial** Leopardo.
- **Permissions** : le CRM client est protégé par des Policies Laravel (`CrmAccountPolicy`, `CrmContactPolicy`, …) et le contexte tenant — jamais par une garde inline. Le CRM commercial reste protégé par la Gate `viewPlatformAdmin` / middleware admin existant.

### ADR-CRM-003 — Le contexte tenant est une donnée de sécurité (rappelle ADR-002 des fondations)

Toute donnée du CRM client porte `company_id` non nullable ; toute requête Eloquent est scopée `->where('company_id', $companyId)` ou hérite du scope tenant `BelongsToCompany` ; tout endpoint expose un test d'isolation cross-tenant (404 attendu). Les jobs, events et caches du CRM client exigent un tenant (voir #5706, CRM-V0-02, et le contrat d'exécution tenant #5736).

### ADR-CRM-004 — Flux autorisé : Platform → activation tenant documenté

L'activation du CRM client pour une entreprise passe par le **mécanisme de feature flags existant** :

```
Platform admin  ──PUT /api/v1/platform/companies/{company}/features──▶  company.features['crm'] = true
                                                                              │
                                                                              ▼
                                                          FeatureFlag::for($company)  →  'crm' enabled
                                                                              │
                                       ┌──────────────────────────────────────┴──────────────────────┐
                                       ▼                                                             ▼
                        Menus CRM client (web/mobile)                        Routes /api/v1/crm/* accessibles
                        visibles pour le tenant                              (Policies tenant + feature gate)
```

Règles :

1. Le flag `crm` est **désactivé par défaut** pour toute nouvelle entreprise (opt-in plateforme).
2. La désactivation coupe l'accès aux routes, menus et données du CRM client (le code reste présent, la gate bloque).
3. L'activation est **auditée** (mutation sensible → `AuditLog::create()`, préfixe `crm.feature.*`).
4. Le CRM commercial Leopardo n'est **jamais** affecté par ce flag (contextes séparés, ADR-CRM-002).
5. La liste des flags par module est exposée via `FeatureFlag::for($company)` (existant) ; le flag `crm` y est ajouté sans rupture (`features` de `companies` est un JSON object).

### ADR-CRM-005 — Contrôles d'entrée stricts partout

Toute entrée inconnue (champ, filtre, tri, statut, taille de page, fichier) est strictement validée : `Request` dédiées par action, `Rule\In` sur les statuts/enums, whitelist de tris, pagination plafonnée, MIME/taille contrôlés sur les fichiers (import CSV #5714). Aucun paramètre client ne peut élargir le périmètre tenant (ADR-CRM-003).

## Conséquences

- Le programme CRM client démarre dans un module propre (`App\Modules\CRM`), conforme DDD (constitution §VI), sans dette de couplage avec `Platform`/`Marketing`.
- Chaque issue V0/V1 référence cet ADR comme fondation ; toute déviation nécessite un amendement de l'ADR.
- La garde d'isolation #5584 couvre automatiquement le nouveau module : tout import croisé `CRM → Platform/Marketing` fera échouer la CI.
- Le « flux autorisé » est implémentable immédiatement (mécanisme de flags existant), sans changement du CRM commercial.

## Alternatives rejetées

- **Étendre le CRM commercial (Platform/Marketing) pour servir les tenants** : rejeté — mélanger un outil interne non-tenant avec des données client tenant-scoped créerait des fuites cross-tenant structurelles et un couplage admin/client impossible à sécuriser.
- **Un module `CRM` unique absorbant le pipeline plateforme** : rejeté — le pipeline Leopardo a un cycle de vie, un owner et un schéma différents ; le déplacer casserait l'existant sans bénéfice.
- **Dupliquer le CRM commercial dans le tenant** : rejeté — double source de vérité sur les leads Leopardo, confusion des owners.

## Conditions de réexamen

- Si une future version souhaite un enrichissement croisé (ex. attribution automatique d'un lead marketing vers un compte tenant après activation), un nouvel ADR devra définir le contrat de passage (événement versionné, jamais import direct).
- Si le volume de données du CRM client impose un découpage supplémentaire (ex. `CRM Sales` vs `CRM Marketing`), le périmètre de cet ADR sera amendé par une nouvelle issue.

## Validation

- [x] Responsabilités et owners distincts formalisés (ADR-CRM-001)
- [x] Routes, tables, menus et permissions séparés (ADR-CRM-002)
- [x] Flux autorisé Platform → activation tenant documenté (ADR-CRM-004)
- [x] Contexte tenant verrouillé (ADR-CRM-003) — détaillé dans #5706
- [x] Spécifications de référence créées (`PLAN-V0-V1-CRM-CLIENT-SQL-INTEGRATIONS.md`, `MODULE_CRM_INTERNE_CLIENT.md`)
