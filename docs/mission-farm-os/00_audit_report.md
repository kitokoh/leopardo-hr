# Farm OS — Phase 1 : audit du depot `kitokoh/leopardo-hr`

**Date de l'audit :** 2026-10-16 (audit initial) · **relecture :** 2026-10-18
**Depot :** `kitokoh/leopardo-hr` — branche `bc/bc-37-farm-os`
**Methode :** lecture du code source, des migrations, des routes, des enums, des gardes CI et des registres de gouvernance. **Aucune conclusion n'est tiree d'un nom de fichier seul** : chaque affirmation ci-dessous est adossee a un chemin de code reel.

> **Regle appliquee :** la colonne *Etat* n'autorise que 5 valeurs — `existante et operationnelle`, `partielle`, `defectueuse`, `absente`, `a verifier`. « A verifier » signifie : le code existe mais son execution n'a **pas** pu etre prouvee dans cet environnement.

---

## 1. Synthese de l'architecture reelle

Monorepo multi-stack :

| Zone | Techno | Role |
|---|---|---|
| `api/` | Laravel (PHP 8.4), DDD modulaire | Coeur metier — modules sous `api/app/Modules/` |
| `front/web` | Next.js — vitrine + portail client (RBAC, un seul portail qui s'adapte au role) | Espace tenant web |
| `front/web-offline` | Next.js — PWA offline-first | Mode terrain sans reseau |
| `front/admin-dashboard` | Vue.js 3 | Super-admin plateforme |
| `front/mobile_apps/*` | Flutter | Mobile |
| `edge/` | Caddy + supervisord | Bridge on-prem ↔ cloud |
| `shared/i18n` | ARB + JSON `fr/en/ar/tr` | i18n transversal |

Deux couches backend coexistent, en cours d'unification : `api/app/Core/*` (socles transverses) et `api/app/Modules/*` (DDD). **Tout nouveau code metier va dans `api/app/Modules/<Nom>/`** (`ARCHITECTURE.md`).

### 1.1 Socles transverses reellement disponibles (`api/app/Core/`)

| Socle | Chemin | Role |
|---|---|---|
| Tenant | `Core/Tenant/` — `TenantManager.php`, `Domain/Models/Company.php`, `Domain/Models/EmployeeModuleGrant.php` | `setTenant`, `withinTenant`, `withinSearchPath` ; `Company::hasFeature/setFeature` |
| Auth | `Core/Auth/` — `Domain/Models/{User,Employee,AuditLog}.php` | Identite + journal d'audit |
| Feature | `Core/Feature/` — `Domain/ModuleRegistry.php`, `Infrastructure/Services/{FeatureFlag,FeatureFlagRegistry}.php` | Registre declaratif unique + projection flags |
| Solutions | `Core/Solutions/` — `SolutionCatalogue.php`, `SolutionActivator.php`, `SolutionPermissionInstaller.php`, `Contracts/SolutionManifest.php`, `Enums/SolutionIndustry.php` | Moteur de verticales (catalogue, activation, permissions) |
| AI | `Core/AI/` + `api/app/AI/` | Orchestrateur IA, tools, workflows, privacy |
| Notifications | `Core/Notifications/` (`EmployeeNotifier`) + `Modules/Notification/` | Contrats + implementation |
| Privacy | `Core/Privacy/` | RGPD |

### 1.2 Catalogue de solutions — mecanisme canonique d'ajout d'une verticale

Contrat `App\Core\Solutions\Contracts\SolutionManifest` : `code()`, `name()`, `maturity()` (`pilot|production|placeholder`), `industry()` (`SolutionIndustry`), `description()`, `requiredModules()`, `optionalModules()`, `sensitiveData()`, `permissions()`.

`SolutionCatalogue` est une **allowlist fail-closed** : un code inconnu est refuse ; les manifests sont enregistres par les providers de modules (inversion de dependance — le Core ne reference jamais `App\Modules\*`).

`SolutionActivator` est le seul chemin d'activation, et il est deja conforme a ce que Farm OS exige :

- `activate()` — **idempotent** (`already_active`), **transactionnel** (flag + installation des permissions + audit `solution.activated`), **fail-closed** (`SolutionMissingDependencyException` si un module requis est inactif) ;
- `activateWithDependencies()` — chemin de provisioning : active d'abord les modules requis puis la solution ;
- `deactivate()` — revoque les permissions installees **en conservant** les codes partages avec une autre solution active du tenant ;
- `SolutionActivated::dispatch($company, $code)` — point d'amorcage idempotent pour les donnees de reference de la verticale ;
- `SolutionPermissionInstaller` — installe les permissions du manifest en reutilisant **le socle existant de delegation** `employee_module_grants` ; ne cree **aucun** nouveau systeme de roles.

**`SolutionIndustry`** est un registre **ferme et versionne**, extension uniquement par nouvelle case. La case `agriculture` a ete **ajoutee** par cette mission (extension additive, aucun renommage).

### 1.3 Les points d'enregistrement obligatoires d'une verticale

Creer une verticale = **plusieurs points d'enregistrement au-dela du module**, tous verifies par des gardes locales :

1. catalogue (`SolutionCatalogue` via le provider) ;
2. `api/config/feature-flags.php` ;
3. `Company::KNOWN_MODULES` (derive de `ModuleRegistry`) ;
4. registre BC (`dev-hub/governance/bounded-context-registry.json`) + `CODEOWNERS` ;
5. parite docs architecture (compteur de modules) — garde `check-architecture-docs-parity.sh` ;
6. fixture `CreatesMvpSchema` ;
7. couverture OpenAPI (garde `check-openapi-route-coverage.py`) ;
8. prefixes proteges front + i18n `fr/en/ar/tr` (garde de parite des catalogues).

`ModuleRegistry` est la **source declarative unique** : chaque entree est declaree une fois, et `config/feature-flags.php` / `KNOWN_MODULES` en sont des **projections calculees**.

### 1.4 Conventions et gardes a respecter

- **Multi-tenant** : `company_id` obligatoire, trait `App\Shared\Traits\BelongsToCompany` (scope tenant + auto-remplissage) ; `company_id` n'est **jamais** mass-assignable depuis une requete client.
- **Permissions** : controle **cote serveur**, middleware `module.<code>`, `EmployeeModuleGrant`.
- **Migrations** : gardes de collisions de nom, zero tolerance sur les doublons de schema, `down()` non destructeur, idempotence obligatoire (Render rejoue les migrations).
- **Qualite** : PHPStan **niveau 8**, Pint, Module Structure Validator, purete des couches, convention `execute()` des Actions, i18n (aucun message accentue code en dur), OpenAPI.
- **Branches** : `main` protegee ; protocole `bc/<code-bc>-<slug>` ; `Closes #N` **dans le body** de la PR.

---

## 2. Matrice fonctionnalite × etat (perimetre Farm OS)

Legende : **OK** = existante et operationnelle · **PART** = partielle · **ABS** = absente · **?** = a verifier.

### 2.1 Fondations plateforme

| # | Fonctionnalite exigee par la mission | Etat | Preuve / chemin | Decision Farm OS |
|---|---|---|---|---|
| F01 | Catalogue de solutions verticales | OK | `Core/Solutions/SolutionCatalogue.php` | Reutiliser — enregistrer `FarmOsManifest` |
| F02 | Manifest de verticale | OK | `Core/Solutions/Contracts/SolutionManifest.php` | Reutiliser tel quel |
| F03 | Activation idempotente + transactionnelle + auditee | OK | `Core/Solutions/SolutionActivator.php` | Reutiliser — **ne pas** reecrire |
| F04 | Installation effective des permissions a l'activation | OK | `Core/Solutions/SolutionPermissionInstaller.php` | Reutiliser |
| F05 | Evenement d'activation pour l'amorcage de la verticale | OK | `App\Events\SolutionActivated::dispatch()` | Reutiliser |
| F06 | Registre industrie (enum ferme) | PART | `Core/Solutions/Enums/SolutionIndustry.php` — pas d'agriculture a l'origine | **Ajoute** `Agriculture = 'agriculture'` (extension par case) |
| F07 | Registre unifie modules/flags | OK | `Core/Feature/Domain/ModuleRegistry.php` | **Ajoute** une entree `farm_os` |
| F08 | Feature flags fail-closed + kill switch | OK | `config/feature-flags.php` | Reutiliser |
| F09 | Allowlist console plateforme | OK | `Company::KNOWN_MODULES` | Derivation automatique |
| F10 | Isolation multi-tenant | OK | `Core/Tenant/TenantManager.php`, `Shared/Traits/BelongsToCompany.php` | Reutiliser — `company_id` obligatoire partout |
| F11 | Journalisation des operations sensibles | OK | `Core/Auth/Domain/Models/AuditLog.php` | Reutiliser |
| F12 | Suppression/purge tenant | OK | `PlatformCompanyPurge` | **Declare** les tables Farm OS |
| F13 | Gardes CI de structure/qualite | OK | `.github/workflows/`, `dev-hub/tools/check-*` | Executer les gardes avant push |

### 2.2 Onboarding et provisioning

| # | Fonctionnalite | Etat | Preuve / chemin | Decision Farm OS |
|---|---|---|---|---|
| F14 | Entretien de preparation → plan d'activation | OK | `Modules/Onboarding/Domain/Services/SetupInterviewPlanner.php` | Reutiliser — **etendre** l'allowlist |
| F15 | Questions/reponses en allowlist fail-closed | OK | `SetupInterviewPlanner::QUESTIONS`, `sanitize()` | Ajouter les questions Farm OS |
| F16 | Mapping secteur → solution verticale | PART | `SECTOR_SOLUTIONS` sans agriculture | Ajoute `agriculture` → `farm_os` |
| F17 | Mapping priorites → outils horizontaux | PART | `PRIORITY_TOOLS` | Ajouter les priorites agricoles |
| F18 | Execution du plan (activation reelle, idempotente) | OK | `Modules/Onboarding/Application/Actions/CompleteSetupInterview.php` | Reutiliser |
| F19 | Checklist d'onboarding + resynchronisation | OK | `Actions/SeedDefaultSteps.php`, `OnboardingChecklistController.php` | Reutiliser |
| F20 | Provisioning idempotent/rejouable/audite | PART | Chemin reel : signup → entretien → `SolutionActivator::activateWithDependencies` | Reutiliser le chemin reel ; **ne pas** creer un second orchestrateur |
| F21 | Etablissements / sites multiples | ABS | Aucun modele `Establishment` dans `api/app` | **Cree dans Farm OS** (`farm_sites`) |
| F22 | Provisioning des sites et parametres regionaux | PART | `CompleteSetupInterview` + `CompanyActivationSurfaceWriter` | Etendre cote Farm OS |
| F23 | Espace tenant « Mon Logiciel » | OK | `front/web/src/lib/software-catalog.ts`, regle `link`/`pilot`/`soon` | **Ajouter** les entrees Farm OS (jamais de faux lien) |

### 2.3 Domaines metier agricoles

| # | Fonctionnalite | Etat | Preuve / chemin |
|---|---|---|---|
| F24 | Exploitations/sites, parcelles, cycles culturaux | OK | `farm_sites`, `farm_parcels`, `farm_crop_cycles` |
| F25 | Interventions agricoles (machine a etats fermee) | OK | `farm_operations` + `FarmOperationStatus` |
| F26 | Equipes et affectation controlee cote serveur | OK | `farm_teams`, `farm_team_members`, `AssignFarmOperation` |
| F27 | Intrants, lots, journal de stock immuable | OK | `farm_inputs`, `farm_input_lots`, `farm_input_stock_movements` |
| F28 | Materiel, compteurs manuels, entretien, pannes, couts, documents | OK | `farm_equipment*` (5 tables) |
| F29 | Elevage : lots, animaux, evenements, sante, reproduction, production, alimentation | OK | `farm_livestock_*`, `farm_animals`, `farm_animal_health_events`, `farm_reproduction_events`, `farm_production_records`, `farm_feed_records` |
| F30 | Recoltes, entreposage, tracabilite, coherence anti-double-comptage | OK | `farm_harvest_lots`, `farm_storage_locations`, `farm_storage_movements` |
| F31 | Depenses, campagnes, rattachement de ventes, rentabilite honnete | OK | `farm_expenses`, `farm_campaigns`, `farm_sales_links` |
| F32 | Hors ligne : file idempotente + conflits explicites | OK | `farm_sync_operations`, `farm_sync_conflicts` |
| F33 | Assistant agricole (orchestrateur existant, contexte borne) | OK | `FarmAssistantContextService` + `App\AI\AgentRunner` |
| F34 | Catalogue applicatif + matrice d'integrations | OK | `FarmAppCatalogueService`, `GET /farm/integrations` |
| F35 | Tableau de bord etendu aux modules livres | OK | `GET /farm/dashboard` |

### 2.4 Ce qui reste absent (assume)

| # | Fonctionnalite | Etat | Raison |
|---|---|---|---|
| F36 | Telemetrie capteur / GPS automatique | ABS | Aucune integration reelle ; saisie manuelle assumee |
| F37 | Meteo, prix de marche, imagerie satellite | ABS | Aucune source branchee ; declare indisponible |
| F38 | Application mobile Farm OS dediee | ABS | Aucune application publiee ; le catalogue le dit |
| F39 | Diagnostic veterinaire automatise | ABS | Ne remplace pas un professionnel qualifie |
| F40 | Moteur SQL libre pour l'assistant | ABS | Interdit par la mission |
| F41 | Systeme financier parallele (factures, paiements) | ABS | Interdit ; Farm OS reference les documents existants |
| F42 | Demandes de prix / commandes clients | ABS | Appartient au socle de ventes |

---

## 3. Environnement d'execution de l'audit

| Element | Etat | Consequence |
|---|---|---|
| PHP | 8.2 en session | `pint` (exige ≥ 8.3) non executable localement |
| PostgreSQL + `pdo_pgsql` | absents | `FarmProvisioningTest` et les scenarios HTTP/Feature ne sont pas executables localement |
| PHPUnit (unitaires purs) | disponible | Les tests de contrat/enum/machine a etats sont executables |
| PHPStan | disponible | `phpstan -c phpstan-modules.neon` et `phpstan-strict.neon` executables |
| Node / redocly | disponible | Lint OpenAPI executable |

**Aucun test non execute n'est presente comme reussi.**

---

## 4. Conclusion de l'audit

1. Le socle Leopardo fournit **tout** ce dont une verticale a besoin : catalogue, activation idempotente, permissions, isolation, audit, notifications, IA, fichiers. Farm OS n'a eu a creer **aucun** systeme transversal.
2. Les seuls manques reels etaient : la case d'industrie `agriculture`, l'entree `ModuleRegistry`, le mapping d'onboarding, et les objets metier agricoles eux-memes.
3. Les domaines metier (parcellaire, intrants, materiel, elevage, recoltes, finance, hors ligne, assistant) ont ete implementes **dans le module**, sans toucher aux tables partagees.
4. Les integrations externes ont ete **volontairement** laissees non branchees : la mission interdit toute depense et toute donnee simulee.
