# Feature Specification: Registre unifié modules / features / solutions (BOS-010)

**Feature Branch**: `spec/8148-unified-module-registry`
**Created**: 2026-09-26 | **Status**: Draft — en attente de validation owner
**Issue**: #8148 (BOS-010, Block 1 P1 — zéro code applicatif)
**Références programme**: `docs/architecture/business-os/09_EXECUTION_READINESS_REVIEW.md` (PR #8138) — débloque BOS-011 (Block 2), prérequis de BOS-013/016/026.

---

## 1. Contexte et problème démontré

L'activation d'un module par tenant est définie par **trois sources de vérité
plus deux tables de correspondance satellites**, toutes maintenues à la main :

| # | Source | Rôle réel (mesuré dans le code) |
|---|---|---|
| 1 | `api/config/feature-flags.php` | 20 flags (`scope` module/solution, `default`, `since`, `killable`) + kill switches config/env — consommé par `FeatureFlagRegistry`, `AiCloudPolicy`, `Company::hasFeature()` (défauts) |
| 2 | `Company::KNOWN_MODULES` (const, 20 entrées) | Allowlist d'activation plateforme — `PlatformCompanyFeatureController::update()` **reconstruit** `companies.features` à partir d'elle ; absente d'ici, une verticale n'est jamais activable par l'admin |
| 3 | `companies.features` (JSON) + `companies.metadata.modules` (JSON) | État par tenant : flags plateforme d'un côté, sélection d'outils horizontaux du client de l'autre — écrits par des chemins distincts qui doivent rester cohérents (double écriture #7432/#7476) |
| 4 | `Company::HORIZONTAL_TOOLS` (12) / `HORIZONTAL_TOOL_FEATURES` (5 miroirs) / `TEAM_TOOLS` | Correspondances outil horizontal ⇄ flag plateforme, éditées à la main |
| 5 | `docs/REFERENTIEL_PRODUIT/ROADMAP.md` | Enregistrement documentaire exigé par le docblock de `KNOWN_MODULES` (APV L.08) — 3e point d'enregistrement manuel |

### Désynchronisations documentées (même classe de bug, 5 récidives)

- **#7220** — `travelagency` absent de `KNOWN_MODULES` → verticale Travel inactivable par la plateforme.
- **#7235** — `accounting` (comptabilité) : module horizontal existant côté serveur, non déclaré dans les registres client/plateforme.
- **#7432** — `training` : outil horizontal **et** flag plateforme du même nom ; l'auto-activation client n'écrivait qu'une source sur deux.
- **#7785** — `healthmanager` : enregistrement aux 3 points exigé dès la création (leçon appliquée).
- **#7976** — `restaurantmanager` (flag opérationnel réel) absent de `KNOWN_MODULES` alors que `restaurant` y figurait → verticale en 403.

### Désynchronisation vivante mesurée au 2026-09-26 (preuve que le problème n'est pas résolu)

- **`fleet`** est dans `KNOWN_MODULES` (gate serveur `module.fleet`, manifests Delivery/FuelStation) mais **absent de `config/feature-flags.php`** : pas de `default`, pas de `since`, invisible de `FeatureFlag::for()`.
- **`ai_cloud_allowed`** est dans `feature-flags.php` (`scope: solution`) mais hors `KNOWN_MODULES` — légitime, mais la distinction module/solution n'existe que par convention orale, pas structurellement.
- Les gardes existantes sont **par verticale et ajoutées après chaque incident** (`TravelAgencyModuleRegistryTest`, `RestaurantManagerModuleRegistryTest`, `TrainingModuleRegistryTest`, `CommunicationModuleGateTest`, `HospitalityModuleStatusTest`…) : il n'existe **aucun test de parité global** registre ⇄ allowlist ⇄ consommateurs.

**Conséquence** : ajouter un module exige aujourd'hui 3 enregistrements manuels cohérents (registre de flags + allowlist + miroir metadata éventuel), chaque oubli produit un bug d'activation découvert en production, et le Block 2 (BOS-011/012/013) ne peut pas démarrer sans une spec validée.

---

## 2. User Stories & Testing

### User Story 1 — Le développeur enregistre un nouveau module en **1 seul endroit** (P1)

Un développeur ajoute une verticale (ex. `fleet`). Il déclare **une entrée
unique** dans le registre unifié ; l'allowlist plateforme, la projection
`feature-flags`, le miroir metadata éventuel et l'exposition `/auth/me` en
découlent structurellement.

**Why this priority**: c'est le cœur de l'issue — supprimer la classe de bug « désync des 3 sources » au lieu d'ajouter un Nième test de rattrapage.

**Independent Test**: ajout d'un module factice en recette → visible et activable via `PATCH /platform/companies/{id}/features`, présent dans `FeatureFlag::for()`, sans modification d'aucun autre fichier.

**Acceptance Scenarios**:
1. **Given** une entrée `fleet` déclarée dans le registre unifié, **When** l'admin plateforme liste les modules connus, **Then** `fleet` apparaît et est activable (fail-closed par défaut).
2. **Given** un code de module utilisé par un gate (`module.fleet`), un manifest (`code()`) ou un miroir d'outil horizontal, **When** la CI s'exécute, **Then** elle échoue si ce code est absent du registre (« module non enregistré = échec »).
3. **Given** un module retiré du registre, **When** la CI s'exécute, **Then** elle échoue si un consommateur (gate, manifest, miroir) y fait encore référence.

### User Story 2 — L'admin plateforme garde une feature map **identique** pendant la migration (P1)

Pendant toute la transition (BOS-011 → BOS-012), la résolution effective de
chaque flag pour chaque tenant reste **strictement identique** : `/auth/me`,
les gates middleware, la console plateforme et le catalogue client ne
changent pas de comportement.

**Why this priority**: la migration touche le chemin d'activation de tous les tenants ; toute divergence est une régression produit immédiate (403 ou module offert par erreur).

**Independent Test**: test de parité automatisé comparant `FeatureFlag::for($company)` en mode `legacy` et en mode `registry` sur un panel de tenants représentatifs + snapshot exhaustif en staging.

**Acceptance Scenarios**:
1. **Given** des tenants couvrant `features` nul/vide/partiel, `metadata.modules` nul/partiel et un kill switch actif, **When** la feature map est résolue en mode legacy puis registry, **Then** les deux cartes sont **byte-identiques** (test automatisé).
2. **Given** le mode `dual` actif, **When** une divergence de résolution survient, **Then** elle est journalisée (canal audit JSON, sans PII : company_id + clé + valeur legacy/registry) et la valeur **legacy** est servie (fail-safe pendant la transition).
3. **Given** un snapshot de la feature map de tous les tenants de staging avant bascule, **When** le mode `registry` est activé, **Then** le nouveau snapshot est identique, faute de quoi la bascule est annulée.

### User Story 3 — L'exploitation conserve ses kill switches avec **priorité DB** (P1)

Un incident produit exige de couper `leo_ai` pour toute la plateforme en
moins d'une minute. Le kill switch base de données (`FeatureKillSwitchService`,
auditée, idempotente) reste **prioritaire** sur toute déclaration du registre
et sur les kill switches config/env.

**Why this priority**: sécurité d'exploitation non négociable (MAT-010) — aucune refonte ne peut affaiblir le chemin de coupure d'urgence.

**Independent Test**: activation d'un kill switch DB sur un flag `killable` → résolution `false` pour tous les tenants, quelle que soit la valeur stockée ; désactivation → retour à l'état antérieur (aucune donnée supprimée).

**Acceptance Scenarios**:
1. **Given** un flag actif pour un tenant, **When** le kill switch DB est activé, **Then** `hasFeature()` et `FeatureFlag::for()` retournent `false` pour ce flag (tous tenants) en moins d'un TTL de cache (60 s).
2. **Given** un kill switch DB actif et un kill switch config/env inactif, **Then** la valeur DB l'emporte (priorité conservée et testée).
3. **Given** un flag déclaré `killable: false` (`rh`), **Then** toute tentative de kill est refusée (fail-closed) et journalisée.

### User Story 4 — `metadata.modules` devient un **statut dérivé**, pas une 3e écriture (P2)

La sélection d'outils horizontaux du client (`training`, `showcase`,
`cameras`, `accounting`, `crm`) vit aujourd'hui dans `metadata.modules`,
en double avec `companies.features` pour les 5 outils mirrorés. Cible : une
seule écriture canonique (la feature), `metadata.modules` devenant une
**projection dérivée** pour le client web.

**Why this priority**: élimine la double écriture #7432/#7476 sans casser le client `client-features.ts` qui consomme `company.modules` (projection `/auth/me`).

**Independent Test**: activation d'un outil mirroré par le client (`POST /company/modules/{key}/activate`) → la feature canonique est posée, la projection `modules` exposée dans `/auth/me` reflète la même vérité, et le client web affiche l'outil sans changement de contrat.

**Acceptance Scenarios**:
1. **Given** un outil horizontal mirroré, **When** il est activé (quel que soit le chemin : provisioning trial, auto-activation client, console plateforme), **Then** une **seule** écriture canonique a lieu et la projection metadata en découle.
2. **Given** `modules:consolidate --dry-run` (BOS-012), **When** il s'exécute sur staging, **Then** il rapporte 0 divergence fonctionnelle `hasFeature` avant/après dérivation, ou liste précisément les tenants divergents.
3. **Given** un tenant historique sans sélection déclarée (`metadata.modules` nul), **Then** le comportement actuel est préservé (pas de verrouillage surprise — garantie `moduleSelection()` existante).

---

## 3. Exigences fonctionnelles

- **FR-1 — Source déclarative unique** : un registre PHP versionné déclare pour chaque clé : `key`, `kind` (`module` | `solution` | `horizontal_tool`), `default`, `since`, `killable`, `description`, `metadata_mirror` (clé `metadata.modules` ou `null`), `platform_exposable` (bool, remplace `KNOWN_MODULES`), `scope` (compat). **Aucune table de base de données** — cohérent avec la gouvernance actuelle (MAT-010, registre versionné + kill switches DB pour l'exploitation).
- **FR-2 — Dérivations structurelles** : `KNOWN_MODULES`, la projection `feature-flags.flags` et `HORIZONTAL_TOOL_FEATURES` sont **calculés** depuis le registre (plus jamais édités à la main). La parité est structurelle, pas testée après coup.
- **FR-3 — Résolution unique fail-closed** : un point d'évaluation unique — kill switch DB (prioritaire) → kill switch config/env → état tenant (`companies.features`) → défaut du registre ; flag inconnu = `false`.
- **FR-4 — Dual-read réversible** : mode `legacy` | `dual` (comparaison + log de divergence, legacy servi) | `registry`, piloté par config/env — la bascule est un flag, **pas un big-bang** (§2.2 BOS-011).
- **FR-5 — Parité prouvée par test** : test de parité de la feature map (cf. §5) exécuté en CI + snapshot staging obligatoire avant bascule `registry`.
- **FR-6 — Garde d'enregistrement CI** : toute clé référencée par un gate `module.*`, un manifest `code()`, un miroir horizontal ou un client doit exister dans le registre — sinon échec CI. Généralise et remplace les tests de registre par verticale.
- **FR-7 — Kill switch priorité DB** : politique inchangée (FR-3), `killable: false` refusé au kill, audit JSON conservé.
- **FR-8 — metadata dérivé** : à terme (BOS-012), `moduleSelection()` est calculé depuis l'état canonique via les `metadata_mirror` déclarés ; la double écriture transitoire est bornée et mesurée.
- **FR-9 — Zéro changement de contrat** : `/auth/me` (`features` + `modules`), `GET/PATCH /platform/companies/{id}/features`, `client-features.ts` — contrats identiques pendant toute la transition.

---

## 4. Modèle du registre (cible)

```php
// Déclaration unique par clé (extrait du format cible — BOS-011 l'implémente)
'training' => [
    'kind' => 'horizontal_tool',        // module | solution | horizontal_tool
    'default' => false,                  // fail-closed sauf 'rh' (true)
    'since' => '4.12.0',
    'killable' => true,
    'platform_exposable' => true,        // ∈ KNOWN_MODULES dérivé
    'metadata_mirror' => 'training',     // projection metadata.modules (null si aucune)
    'description' => 'Formation (horizontal + flag plateforme, #7432).',
],
'ai_cloud_allowed' => [
    'kind' => 'solution',
    'platform_exposable' => false,       // jamais dans KNOWN_MODULES — aujourd'hui implicite
    // …
],
'fleet' => [
    'kind' => 'module',
    'platform_exposable' => true,        // corrige la désync mesurée au 26/09
    // …
],
```

Dérivations automatiques : `knownModules() = keys(platform_exposable)` ·
`flags() = projection config` · `horizontalMirrors() = entries(metadata_mirror != null)`.

---

## 5. Test de parité de la feature map (définition pour BOS-011/012)

1. **Panel de fixtures** : tenants couvrant `features` ∈ {null, {}, partiel, complet}, `metadata.modules` ∈ {null, partiel, complet}, kill switch {inactif, actif sur flag killable}, flag `default: true` (`rh`), flag inconnu (fail-closed).
2. **Comparaison** : pour chaque fixture, `FeatureFlag::for($company)` et `Company::hasFeature($key)` en modes `legacy` vs `registry` → égalité stricte obligatoire.
3. **Snapshot staging** : export de la feature map résolue de **tous** les tenants staging avant bascule, comparaison après bascule `registry` — 0 diff exigé, sinon rollback `legacy`.
4. **Garde CI permanente** : après bascule, le test de parité devient la garde « module non enregistré = échec » (FR-6).

---

## 6. Stratégie dual-read et rollback

| Phase | Mode | Comportement | Sortie |
|---|---|---|---|
| 0 | `legacy` (défaut) | chemins actuels inchangés | — |
| 1 | `dual` | résolution calculée par les deux chemins ; **legacy servi** ; divergences journalisées (sans PII) | 0 divergence sur fenêtre convenue en staging |
| 2 | `registry` | registre servi | snapshot parité staging OK (FR-5.3) |
| 3 | retrait | code legacy supprimé après 1 release de dual-read stable (BOS-012) | — |

**Rollback** : à toute étape ≤ 2, retour au mode précédent par config/env — aucune migration de données à rejouer (l'état tenant n'est pas réécrit avant BOS-012, et BOS-012 produit un rapport de diff + conserve le dual-read comme chemin de retour).

---

## 7. Politique kill switch

- Ordre de résolution conservé : **DB (`feature_kill_switches`) > config `kill_switches`/env > état tenant > défaut registre** ; fail-closed partout.
- `killable: false` (`rh`) : refus fail-closed + journal d'audit.
- Toute bascule reste idempotente, horodatée (`toggled_by/at`, `reason`) et auditée (comportement `FeatureKillSwitchService` existant, inchangé).
- Aucune suppression de données au kill : la résolution est figée à `false`, l'état stocké est préservé.

---

## 8. Consommateurs à migrer (inventaire exhaustif mesuré)

**Cœur registre/résolution** : `config/feature-flags.php` · `Company` (`KNOWN_MODULES`, `HORIZONTAL_TOOLS`, `HORIZONTAL_TOOL_FEATURES`, `TEAM_TOOLS`, `hasFeature()`, `setFeature()`, `moduleSelection()`, `activateHorizontalTool()`) · `FeatureFlagRegistry` · `FeatureFlag` (façade statique) · `FeatureKillSwitchService` · `FeatureRegistryServiceProvider`.

**Activation / provisioning** : `SolutionActivator` · `SolutionCatalogue` + manifests Core (`FuelStation`, `EduManager`, `HospitalityManager`, `HealthManager`, `Restaurant`, `TravelAgency`, `Pharmacy`) · `Billing\ProvisionGuidedTrial` · `Billing\VerifyTrialSignup` · `Billing\HorizontalToolSelection` · actions verticales (`ActivateRestaurantManagerAction`, `ActivateTravelAgencyAction`…).

**Console plateforme** : `PlatformCompanyFeatureController` (show/update) · `PlatformCompanyController` + `resources/views/platform/companies/edit.blade.php` · `PlatformCompanyHealthService` · `FeatureFlagAuditRecorder`.

**Gates / exposition** : middlewares `Ensure*ModuleMiddleware` et gates `module.*`, `crm.enabled` · `AiCloudPolicy` · `EmployeeResource` (`/auth/me` → `features` + `modules`) · `Onboarding\SeedDefaultSteps` · routes `billing` (feature-flags).

**Clients** : `front/web/src/lib/client-features.ts` (`explicitToolValue`, catalogue modules) + consommateurs (`Sidebar.tsx`, `dashboard-nav.ts`) — contrat `/auth/me` préservé, aucune migration client pendant la transition.

**Documentaire** : `docs/REFERENTIEL_PRODUIT/ROADMAP.md` (point d'enregistrement manuel à supprimer ou à générer).

**Tests à généraliser/remplacer par la garde FR-6** : `TravelAgencyModuleRegistryTest`, `RestaurantManagerModuleRegistryTest`, `TrainingModuleRegistryTest`, `CommunicationModuleGateTest`, `HospitalityModuleStatusTest`, `FeatureFlagTest`, `PlatformCompanyFeatureApiTest`, `FeatureFlagKillSwitchTest`, `FeatureFlagControllerTest`, `SolutionManifestTest` (Fuel/Edu).

---

## 9. Non-Goals (hors périmètre #8148)

- **Aucun code applicatif** — l'implémentation est BOS-011 (registre + dual-read) puis BOS-012 (consolidation données), Block 2.
- Extension du `SolutionManifest` (champ `industry`, installation des permissions) → **BOS-013**.
- Convergence des 4 contrats `SolutionManifest` (Core + 3 locaux : TravelAgency, Delivery, RestaurantManager) → **BOS-014**.
- Refonte du client web (la projection `/auth/me` est un contrat stable).
- Renommage de clés existantes (tout renommage passerait par alias déclarés, à décider dans BOS-011 si nécessaire).

## 10. Edge Cases

- Tenant historique avec `features` null et `metadata.modules` null → comportement actuel préservé (défauts registre, sélection nulle = non verrouillé).
- Clé inconnue présente dans `companies.features` (donnée historique) → ignorée à la résolution (fail-closed), rapportée par `modules:consolidate --dry-run` (BOS-012) au lieu d'être supprimée silencieusement.
- Flag `default: true` autre que `rh` ajouté un jour → le piège documenté dans `hasFeature()` (#7400) ne doit pas être réintroduit : le défaut ne vit que dans le registre.
- Kill switch sur clé inconnue → refus fail-closed + audit (cohérent FR-7).
- Outil horizontal **sans** miroir plateforme (`employees`, `reports`…) → reste une pure donnée `metadata.modules` ; le registre déclare `metadata_mirror: null` et n'invente pas de flag plateforme.
- Divergence dual-read en production staging → legacy servi + log ; la bascule `registry` est bloquée tant que des divergences non expliquées existent.

## 11. Critères d'acceptation (issue #8148)

1. Spec + plan + tasks relus et **validés par le owner**.
2. La spec répond explicitement (§5, §6, FR-1/FR-2) : comment la parité feature map est prouvée · comment le dual-read permet le rollback · comment un nouveau module est enregistré en 1 seul endroit.
3. ADR-0026 livrée sur la branche `spec/8148-unified-module-registry` (PR associée).

**Tests** : pas de tests pour ce livrable documentaire ; la spec **définit** les tests de parité attendus pour BOS-011/012 (§5).
**Rollback** : N/A (documents uniquement).
