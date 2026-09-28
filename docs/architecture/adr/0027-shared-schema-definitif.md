# ADR 0027 — Schéma partagé définitif : le mode « un schéma par tenant » est acté comme mort

## Statut

Proposée — **validation owner requise** (critère d'acceptation de l'issue #8203 / BOS-005).

**Date** : 2026-09-28
**Décideurs** : Équipe technique Leopardo (proposition agent, issue #8203 / BOS-005, lot Z10 ; programme Business OS, `docs/architecture/business-os/09_EXECUTION_READINESS_REVIEW.md` PR #8138)

> Amende l'ADR [0001](0001-multi-tenant-postgresql.md) (« schémas séparés et mode shared contrôlé ») : le mode « un schéma par tenant » n'est plus une option d'architecture. Le schéma partagé `shared_tenants` + `company_id` + global scope est le modèle de tenancy **unique et définitif**.

## Contexte

Le mode « un schéma PostgreSQL par tenant » est **verrouillé à la création** depuis longtemps : `Company::booted()` refuse tout nouveau tenant avec `tenancy_type = 'schema'` (abort 422 `COMPANY_SCHEMA_MODE_LOCKED`). Aucun tenant récent ne l'utilise ; les données métier vivent dans le schéma partagé `shared_tenants` avec isolation logique par `company_id` (`BelongsToCompany`, global scope), prouvée par les tests d'isolation (`FkChainTenantIsolationTest`, `RemainingModelsTenantIsolationTest`).

Mais le vestige coûte cher en confusion :

- la documentation (`docs/architecture/MULTITENANCY.md`) présentait encore le schéma dédié comme l'option « Enterprise » ;
- plusieurs chemins de code actifs branchaient sur `tenancy_type === 'schema'` — chaque nouvelle surface (kiosk, suppression de tenant, controllers web plateforme, FormRequest HR) réimplémentait une variante du même branchement pour un mode **impossible à créer** ;
- l'enum `tenancy_type`, l'index partiel `WHERE tenancy_type = 'schema'` et la table shadow `shared_tenants.companies` (vide, à l'origine du bug des invitations sauté — voir `Company::$table`) entretenaient l'ambiguïté sur la source de vérité.

Le programme Business OS (BOS-005) demande d'acter le modèle cible et de borner les restes.

## Décision

1. **Modèle unique : schéma partagé.** Toute donnée métier vit dans `shared_tenants` (ou `public` pour le registre plateforme), isolée par `company_id` + global scope. Le `search_path` ne bascule plus qu'entre contextes **techniques** (`public`, `shared_tenants`) — jamais vers un schéma par tenant pour un tenant nouveau.
2. **Interdiction de nouvelles branches.** Aucun code nouveau ne peut tester `tenancy_type === 'schema'` ni introduire une bascule vers un schéma dédié. Toute PR qui en ajoute une est refusée en revue.
3. **La garde historique est conservée et documentée ici.** `Company::booted()` (abort 422) est ce qui rend le mode impossible à créer : elle reste en place tant que la colonne `companies.tenancy_type` existe.
4. **Les références restantes forment un inventaire borné** (ci-dessous), pas un « mode supporté » : elles protègent d'éventuels tenants historiques et disparaîtront avec le nettoyage physique.
5. **Nettoyage physique différé.** La suppression de la colonne/enum/index (migration additive, jamais destructive) n'interviendra qu'après un **audit de production chiffré** (0 ligne `tenancy_type = 'schema'` ou traitement explicite de ces lignes) validé par l'owner. Une issue de suivi la portera.

## Inventaire des références restantes au mode mort (état au 2026-09-28)

| Site | Rôle | Traitement |
|---|---|---|
| `Company::booted()` — `creating` → abort 422 | **Garde historique** : interdit la création d'un tenant en schéma dédié | Conservée (documentée ici) |
| `TenantDeletionService` — refus 409 `TENANT_DELETION_UNSUPPORTED_TENANCY` | Protection : refuse de « supprimer » un tenant historique dont les données vivraient hors de `shared_tenants` (silence dangereux, #7535) | Conservé jusqu'à l'audit prod |
| `KioskController` (Attendance) — choix du search_path | Branche pour d'éventuels kiosques de tenants historiques | Conservée (commentaire ADR sur place) ; retrait avec le nettoyage |
| `app/Http/Controllers/Web/KioskController`, `BiometricAdminController` ; `UpdateEmployeeRequest` (HR) | Branches search_path équivalentes, hors zone du lot Z10 | Conservées ; retrait avec le nettoyage |
| `DemoCompanySeeder` — DDL index partiel `WHERE tenancy_type = 'schema'` | Réparation DDL idempotente d'environnements legacy | Conservé |
| `CompanyFactory` (état schema) + tests (`PlatformCompanyDeletionApiTest`, …) | Éprouvent la garde et le refus de suppression | Conservés (ils verrouillent le comportement) |
| Migrations `2026_04_01_000002`, `2026_04_22_000013` | Historique immuable | Jamais modifiées |
| `openapi.yaml` — propriété `tenancy_type` | Contrat API plateforme | Description précisée (valeur `schema` verrouillée) |

## Conséquences

- Le discours produit change : il n'y a **pas** d'offre « Enterprise = schéma dédié ». L'isolation est logique, identique pour tous les tenants, et éprouvée par la suite d'isolation. Si un besoin de résidence de données émerge un jour (gros client, contrainte réglementaire), ce sera une **nouvelle** décision d'architecture (ADR), pas une réactivation silencieuse du mode mort.
- Les bascules `search_path` techniques restantes (surfaces publiques kiosk, vues portefeuille plateforme, hooks `Company`) passent par l'API unique `TenantManager::withinSearchPath()` à restauration garantie (BOS-019 / #8204) ; le middleware `EnsureKioskSearchPathReset` (#3368) reste le filet de sécurité.
- `docs/architecture/MULTITENANCY.md` est la documentation d'entrée du sujet et pointe vers la présente ADR.

## Règles opérationnelles

- Toute nouvelle référence à `tenancy_type = 'schema'` hors de l'inventaire ci-dessus = **refus de PR**.
- Le nettoyage physique (colonne `companies.tenancy_type`, enum, index partiel, table shadow `shared_tenants.companies`) se fait uniquement par migration **additive**, après audit prod chiffré validé par l'owner — jamais de `down()` destructeur.
- En cas de doute sur l'existence de tenants historiques en schéma dédié : **ne pas** « simplifier » une branche de l'inventaire ; demander l'audit d'abord.
