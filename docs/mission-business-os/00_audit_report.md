# 00 — Rapport d'audit initial (Leopardo Business OS)

**Dépôt :** `kitokoh/leopardo-hr` · **Commit audité :** `main` @ `e9c5f986c`
**Date :** 2026-10-17 · **Méthode :** lecture du code sur le commit réel, appels à l'API
GitHub (branches, PR, issues, check-runs), aucune supposition sur l'état des fonctionnalités.

Ce rapport distingue systématiquement **faits vérifiés** (avec référence de fichier ou
d'appel API) et **hypothèses** (à confirmer par exécution).

---

## 1. Ce qui existe réellement

| Domaine | Constat vérifié |
|---|---|
| **Architecture** | Monolithe modulaire Laravel 12 / PHP 8.4, DDD 5 couches, **35 modules** sous `api/app/Modules`, socle `api/app/Core` (10 sous-domaines), contrats `api/app/Shared` (11 domaines) |
| **Multi-tenant** | Tenant partagé + schéma PostgreSQL (`shared_tenants`), `company_id` + scope `BelongsToCompany`, `TenantManager`, **442 migrations tenant**, 73 `public`, 2 `edge` |
| **Identité** | Sanctum, 2FA, biométrie, rôles `manager_role` (principal/rh/dept/superviseur), grants par module (`api.module.grant:<module>`), policies par ressource, `SuperAdmin` + `platform.permission:*` |
| **Provisioning** | `CompanyProvisioningService::provisionSharedCompany()` — transactionnel, allowlist de solutions **avant écriture**, pays obligatoire, seed d'étapes idempotent (clé `step_key` unique) |
| **Onboarding** | 10 étapes génériques + étapes par verticale, `setup-interview` (4 questions, brouillon serveur), checklist API, import de démo, `welcome-ack` |
| **Catalogue** | `SolutionCatalogue` + `SolutionManifest` (allowlist, `maturity`, modules requis), `SolutionActivator`, `SolutionPermissionInstaller`, **12 manifests** sur `main`, tous `pilot` |
| **Verticales** | Restaurant, RestaurantManager, FuelStation, TravelAgency, EduManager, HealthManager, Pharmacy, HospitalityManager, Retail, Vtc, Delivery, Fundraising |
| **Applications** | 6 fronts (web, admin Vue, travel-web, marketplace, web-offline PWA, kiosque) + **9 apps Flutter** (melos) |
| **Centre « Mes logiciels »** | `/mon-logiciel` + `software-catalog.ts` : 7 sections de fiche, statut **dérivé** des canaux réels (`published`/`pilot`/`not_distributed`), filtrage par modules activés |
| **Hors-ligne** | `SyncQueue`/`SyncLog` (conflits), `EdgeNode` + santé, `useOfflineSync` + `ConnectionIndicator`, clé d'idempotence stable POS/KDS, révocation d'appareil, service worker testé |
| **Superadmin** | `Modules/Platform` (88 fichiers) : tenants, santé, impersonation tracée, outbox, observabilité files/notifications, support, annonces, suppression inventoriée, kill-switch |
| **Branding** | `CompanyBrandingController`, favicon premium, assets `public/brand`, garde `check-brand-assets.mjs`, `prefers-reduced-motion` respecté |
| **Tests / CI** | **78 workflows**, gardes d'architecture et de migrations, PHPStan niveau 8 sur le delta, i18n 4 locales avec parité vérifiée |

**Conclusion factuelle :** Leopardo Business OS n'est pas un squelette. Le socle
(tenancy, provisioning, RBAC, catalogue, applications, hors-ligne) est écrit et partiellement
testé. La mission ne consiste pas à le construire, mais à combler les écarts entre ce socle
et un parcours réellement opérationnel.

---

## 2. Travaux déjà présents (ne pas dupliquer)

| Élément | État vérifié |
|---|---|
| PR **#8487** `bc/bc-36-trade-os` | ouverte, **207 fichiers**, apporte `Modules/Trade` + registre d'intégrations + rôles Trade |
| Branche `bc/bc-37-farm-os` | existe, verticale Farm absente de `main` |
| Branche `feat/restaurant-os-printing-and-rbac` | travaux restaurant non fusionnés |
| Branche `feat/superapp-public-discovery` | découverte d'applications publique |
| `docs/mission-leopardo/` | dossier d'une mission antérieure (vitrine / constellation), **ne couvre pas** l'onboarding ni les verticales |
| `docs/00_REFERENCE/` | TAD/TRD/AppFlow/Backend Schema/Implementation Plan **antérieurs**, non alignés sur cette mission |

**Décision :** ne rien fusionner de ces branches dans la branche de mission (ADR-02) ;
référencer leur existence et leurs dépendances.

---

## 3. Problèmes identifiés

### 3.1 Bloquants pour un pilote réel

| # | Problème | Preuve |
|---|---|---|
| 1 | **Aucun parcours d'onboarding universel pré-authentification.** L'entretien existant est un questionnaire post-login à 4 questions. | `SetupInterviewPlanner` (sector, premises, priorities, scheduled_hours) |
| 2 | **L'intention Trade n'atteint pas le provisioning.** `/signup` ne lit pas `?trade=` ; seuls `?sector=` et `?pack=` sont consommés. | `lib/signup-sector.ts`, `pack-signup.ts` |
| 3 | **Aucun établissement créé à l'onboarding.** Un tenant peut exister sans lieu d'exploitation. | `provisionSharedCompany()` ne crée aucun `sites` |
| 4 | **L'établissement n'est pas un axe de données.** Table `sites` : ni index `company_id`, ni type, ni statut, ni code. Chaque verticale réinvente son modèle. | migration `2026_04_01_000100_…` |
| 5 | **Idempotence du provisioning non prouvée.** Code idempotent (#3600), aucun test de course, pas de clé cliente. | absence de test |
| 6 | **Idempotence du rejeu hors-ligne non prouvée.** Clé stable POS/KDS, aucun scénario E2E de coupure. | absence de test |

### 3.2 Promesses non tenues

| # | Problème | Preuve |
|---|---|---|
| 7 | **Distribution « build testeurs » en échec** pour 4 apps alors que le statut affiché est « accès pilote ». | 4 check-runs `Distribute … to Firebase` = failure |
| 8 | **`offline: boolean` déclaratif** dans le catalogue, sans preuve d'exécution. | `software-catalog.ts` |
| 9 | **Maturité `pilot` des 12 verticales non exposée** en console superadmin ni en vitrine. | manifests vs `views/solutions` |
| 10 | **Doublon de code de solution** `restaurant` / `restaurantmanager`. | `RestaurantManifest` + catalogue |

### 3.3 Lacunes d'exploitation

| # | Problème |
|---|---|
| 11 | Aucune vue superadmin du provisionnement (étapes, échecs, tenants sans établissement). |
| 12 | Aucun diagnostic sync/installation par tenant. |
| 13 | Aucune UI d'arbitrage des conflits alors que le modèle de données existe. |
| 14 | Pas de page 403 ; `error.tsx` sans identifiant de corrélation. |
| 15 | Aucun statut d'intégration dérivé de la configuration réelle. |
| 16 | Aucun scénario E2E par verticale. |

### 3.4 État de la CI (préexistant, non imputable à la mission)

**104 check-runs sur `e9c5f986c` : 69 success · 17 failure · 9 skipped · 4 cancelled · 1 in_progress.**

Détail et protocole de non-régression : `14_audit_baseline.md`.

---

## 4. Points forts à préserver

1. **Allowlist fail-closed** sur les solutions : aucun code inconnu n'est activable.
2. **Transaction unique** au provisioning : pas d'état partiel en cas d'échec.
3. **Idempotence par clé métier** (`step_key`) plutôt que par drapeau.
4. **Statut d'application dérivé** (règle #6697) : le mensonge commercial est
   structurellement difficile.
5. **Gardes CI nombreuses** : architecture, isolation modules, migrations, i18n, tokens.
6. **Honnêteté documentaire** déjà pratiquée dans le dépôt (limites assumées dans les
   entrées de changelog).

---

## 5. Hypothèses à confirmer (non vérifiées dans cet audit)

| # | Hypothèse | Comment la vérifier |
|---|---|---|
| H1 | Les 4 jobs Firebase échouent par absence de secrets, non par défaut de code | lire les logs du run |
| H2 | Le filtrage par établissement est cohérent dans toutes les verticales | audit route par route + test d'isolation |
| H3 | `manager_role` suffit aux besoins de permission des 12 verticales | `SolutionPermissionInstaller` par manifest |
| H4 | Aucun utilisateur réel n'a besoin d'appartenir à deux tenants (`companies.email` unique) | à valider produit |
| H5 | Les 100 échecs de tests Travel et 13 de Fuel sont bien préexistants | comparaison liste à liste sur `main` |

---

## 6. Conclusion

Le socle est **solide et réutilisable** ; les manques sont **précis et localisés** :
un parcours d'onboarding qui porte réellement l'intention du client jusqu'au tenant, un
établissement qui soit un axe de données, et des preuves d'idempotence là où l'argent
circule. Les 48 écarts de la matrice se répartissent en 14 réutilisables, 15 à compléter,
8 à corriger, 9 à créer, 2 à vérifier — voir `08_gap_matrix.md`.
