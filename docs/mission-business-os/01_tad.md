# 01 — TAD : Technical Architecture Document

**Mission :** Leopardo Business OS — de la vitrine à la plateforme réellement opérationnelle
**Branche :** `mission/leopardo-business-os` · **Base :** `main` @ `e9c5f986c`
**Statut :** descriptif de l'existant **vérifié** + cible. Toute affirmation « cible » est
étiquetée **[cible]** ; rien n'est décrit comme implémenté sans référence de fichier.

---

## 1. Principe directeur

Le socle **existe** et n'a pas à être reconstruit : monolithe modulaire Laravel 12 (PHP 8.4)
en DDD, multi-tenant par `company_id` + schémas PostgreSQL, catalogue de solutions à
allowlist, provisioning transactionnel, onboarding piloté par checklist, passerelle Edge
pour le hors-ligne. La mission consiste à **combler les écarts** entre ce socle et un
parcours de bout en bout réellement opérationnel — pas à créer une seconde plateforme.

Trois invariants de conception :

1. **Une seule base de code par capacité.** Une verticale est un *pack de configuration* +
   un module métier, jamais une application séparée (voir `SolutionManifest`).
2. **Fail-closed sur toute allowlist.** Code de solution, secteur, module, permission :
   une valeur inconnue est refusée, jamais résolue dynamiquement.
3. **Honnêteté de l'état.** Un canal de distribution, une intégration, une maturité de
   solution ne sont jamais déclarés à la main : ils sont **dérivés** de faits constatables
   (cible d'installation réelle, présence de clé de configuration, manifest).

---

## 2. Topologie du monorepo (vérifiée)

```
leopardo-hr/
├── api/                    Laravel 12 — monolithe modulaire (PHP 8.4)
│   ├── app/Core/           socle transversal : AI, Auth, Feature, Http, Mail,
│   │                       Notifications, Privacy, Seed, Solutions, Tenant
│   ├── app/Modules/        35 modules métier (DDD 5 couches)
│   ├── app/Shared/         contrats inter-modules (Attendance, Catalog, Crm, Delivery,
│   │                       Fleet, Geo, HR, Notification, Payments, Payroll, Tracking)
│   ├── routes/             api.php + modules/*.php (un fichier par module)
│   └── database/migrations 3 bases logiques : public (73), tenant (442), edge (2)
├── front/
│   ├── web/                Next.js 16 App Router — vitrine + dashboard + Mon Logiciel
│   ├── admin-dashboard/    Vue 3 / Vite — console superadmin
│   ├── travel-web/         Next.js — marketplace voyage
│   ├── marketplace/        Next.js — marketplace inter-agences
│   ├── web-offline/        PWA offline-first (bridge Edge)
│   ├── zkteco-kiosk/       kiosque pointage HTML/JS
│   └── mobile_apps/        9 apps Flutter (melos) : core, employee, manager, hr,
│                           accounting, marketing, cameras, platform_admin, travel_agent
├── edge/                   pont on-prem (synchronisation)
├── dev-hub/                outillage CI (gardes, scripts, protocoles)
└── .github/workflows/      78 workflows
```

**Réutilisation :** l'ensemble `Core/*`, `Shared/Contracts/*`, les 35 modules et les 6
fronts sont réutilisables en l'état. Aucun composant n'est à supprimer pour faire avancer
la mission (§ `08_gap_matrix.md`).

---

## 3. Couches backend (règle DDD appliquée)

```
Interfaces/Api/V1/{Controllers,Requests,Resources}   ← HTTP seulement
Application/{Actions,DTOs,Queries,Services}          ← orchestration, transaction
Domain/{Models,Contracts,Enums,Exceptions,Policies,Support}  ← règles métier
Infrastructure/{Services,Repositories,Consumers}     ← I/O, adaptateurs externes
Providers/                                           ← enregistrement module
```

Contraintes vérifiées et appliquées par la CI :

- `architecture-check.yml` — placement des couches (`application-layer-placement-guard.yml`),
  isolation inter-modules (un module n'importe pas `App\Modules\Autre`).
- `Core/Solutions/SolutionCatalogue` **ne référence jamais** `App\Modules\*` : les manifests
  sont enregistrés par les providers (inversion de dépendance, garde #5584).
- `phpstan-strict.neon` niveau 8 sur `Core`/`Modules`/`Shared`, gate **sur le delta**
  (baseline gelée).

---

## 4. Multi-tenant

**Modèle réel :** tenant partagé (`tenancy_type = 'shared'`, schéma `shared_tenants`) avec
`company_id` obligatoire sur toute donnée métier, scope global `BelongsToCompany`, et
résolution via `TenantManager` (middleware `tenant`, `try/finally` autour de
`resetToPrevious()`).

```
Company (tenant) 1─n Site (établissement) 1─n Employee
Company 1─n CompanySetting
Company 1─n Subscription / Invoice / Plan
Company 1─n OnboardingStep / OnboardingProgress
Employee 1─n EmployeeModuleGrant (accès aux modules/apps)
SuperAdmin (plateforme, hors tenant)
```

**Établissements :** `Core/Tenant/Domain/Models/Site` + `HR/SiteController`
(`GET/POST/PUT/DELETE /api/v1/sites`). **Constat :** `sites` est un modèle **générique**
(nom, adresse, GPS, rayon) ; les verticales qui ont besoin de plus (restaurant, station,
travel) portent leur **propre** modèle de site (`RestaurantBranch`, `FuelSite`,
`TravelOffice`). Conséquence : un groupe multi-établissements fonctionne, mais **aucune vue
consolidée générique** n'existe — chaque verticale doit la fournir (§ gap V-03).

**Isolation :** contrôle à trois niveaux — `search_path` PostgreSQL, scope Eloquent,
et policies. Les tests d'isolation existent par module ; la généralisation multi-tenant
d'un parcours complet reste à couvrir (§ `15_test_plan.md`).

---

## 5. Identité, rôles, permissions

| Niveau | Mécanisme vérifié |
|---|---|
| Authentification | Sanctum (tokens), `token.refresh`, 2FA (`/auth/2fa/*`), biométrie (`/auth/biometric-enrollment`) |
| Rôles tenant | `manager_role` : `principal`, `rh`, `dept`, `superviseur` (+ `role = manager/employee`) |
| Grants modulaires | `EmployeeModuleGrant` — `ModuleKey` enum, middleware `api.module.grant:<module>` |
| Policies | une policy par ressource sensible (`FuelSalePolicy`, `TravelTicketPolicy`…) |
| Plateforme | `SuperAdmin` + middleware `platform.permission:<domaine>.<action>` |
| Journalisation | `audit_logs` (`AuditLog::record`), `PlatformOutboxEvent`, `ClientEvent` |

**Point d'attention réel :** `manager_role` est un **rôle de gestion RH**, pas un rôle
métier de verticale. Une solution qui déclare des rôles propres (`trade.role:*` sur la
branche `bc/bc-36-trade-os`) doit les installer elle-même via `SolutionPermissionInstaller`
— c'est le pattern prévu, mais il n'est pas généralisé à toutes les verticales.

---

## 6. Provisioning et onboarding

**Chaîne réelle :**

```
Vitrine (Next.js)
  └─ /signup (+ ?sector=, ?pack=) ──► SignupForm
        └─ POST /api/forms/signup (Next route) ──► API POST /trial/signup
              └─ SelfServiceTrialController::signup      (throttle 5/15 min)
                    └─ POST /trial/verify (OTP)          (throttle dédié, verrou 5 échecs)
                          └─ ProvisionGuidedTrial          (idempotent, #3600)
                                ou SuperAdmin → CompanyProvisioningService::provisionSharedCompany
```

`CompanyProvisioningService::provisionSharedCompany()` (247 lignes, vérifié) :

1. **Pays obligatoire**, allowlist `CountryDefaults` — aucun fallback silencieux (#1867).
2. **Allowlist des solutions** vérifiée **avant toute écriture** ; code inconnu → 422 (#6693).
3. `DB::transaction` : création `companies` (+ slug unique), `CREATE SCHEMA IF NOT EXISTS
   shared_tenants`, `TenantManager::setTenant`, création du manager principal (champs
   sensibles posés explicitement, #3677), seed des étapes d'onboarding, activation des
   solutions (`SolutionActivator`, idempotent) avec remontée des dépendances manquantes.
4. `SeedDefaultSteps` : 10 étapes génériques, **complétées par des étapes verticales**
   (`travel_setup_network`, `customize_showcase`, …) selon les modules actifs ; dédup par
   `step_key` (#4188).

**Post-login :** `POST /onboarding/welcome-ack` (persisté dans
`companies.metadata.welcome_seen_at`), `GET/PATCH/POST /setup-interview*` (brouillon serveur
dans `companies.metadata.setup_interview`), `GET /onboarding-setup/checklist` +
`PATCH /{stepKey}/complete|skip`, `GET /demo-data` + `POST /{code}/import|dismiss`.

**Écart structurel majeur (§ gap O-01) :** il n'existe **aucun parcours d'onboarding
universel pré-authentification** qui capture profil professionnel → type de marché →
entreprise → établissement → modules → première opération. L'entretien `setup-interview`
est un **questionnaire post-login** à 4 questions (`sector`, `premises`, `priorities`,
`scheduled_hours`), pas le parcours décrit par la mission. Le point d'entrée `Trade`
existe côté vitrine mais son intention n'atteint pas le provisioning (voir O-02).

---

## 7. Catalogue de solutions et verticales

**Mécanisme réel :** `SolutionCatalogue` (allowlist, factories) + `SolutionManifest`
(`code`, `name`, `maturity`, `industry`, `description`, `requiredModules`,
`optionalModules`, `sensitiveData`, `permissions`) + `SolutionActivator` +
`SolutionPermissionInstaller` + `SolutionSurveyEngine`/`SolutionSurveyRegistry`.

**Manifests présents sur `main` (13) :** Delivery, EduManager, FuelStation, Fundraising,
HealthManager, HospitalityManager, Pharmacy, Restaurant, RestaurantManager, Retail,
TravelAgency, Vtc — **tous `maturity = 'pilot'`**.

**Constat vérifié :** sur `main`, aucun manifest n'est en `production` ; le dépôt ne
contient **aucune** verticale Trade ni Farm (elles vivent sur des branches non fusionnées).
Le catalogue expose donc 12 packs, dont 10 sont repris par la vitrine
(`data/solution-catalog.ts`).

**Ambiguïté réelle :** `restaurant` et `restaurantmanager` coexistent comme deux codes de
solution pour une seule verticale ; le commentaire de `RestaurantManifest` documente la
cascade (activation de `restaurant` → flag opérationnel `restaurantmanager`), mais le
doublon reste une source de confusion côté catalogue/onboarding (§ gap C-02).

---

## 8. Applications et distribution

| Application | Surface | Canal réel de distribution (vérifié) |
|---|---|---|
| Portail web (dashboard) | `front/web` | servi par le déploiement web |
| Mon Logiciel / fiches apps | `front/web/(dashboard)/mon-logiciel` | — |
| Admin plateforme | `front/admin-dashboard` | déploiement admin |
| Marketplace voyage | `front/marketplace` | déploiement |
| PWA hors-ligne | `front/web-offline` | PWA installable |
| Kiosque pointage | `front/zkteco-kiosk` | accès pilote accompagné (`/download`) |
| Android manager / employee / hr / platform_admin | Flutter | **Firebase App Distribution** (secrets `FIREBASE_*_APP_ID`) |
| Android accounting / marketing / cameras / travel_agent | Flutter | **aucun** canal public (`soon`) |
| iOS (toutes) | Flutter | **aucun** canal public (pas de signature/TestFlight dans la CI) |

`software-catalog.ts` **dérive** ce statut des cibles réelles (`published` = au moins une
cible `link`, `pilot`, `not_distributed`) — règle d'honnêteté #6697. Aucun lien fictif.

**Constat :** la distribution Firebase **échoue** sur `main` (4 jobs « Distribute … to
Firebase » en échec, cf. § `14_audit_baseline.md`) : le canal « build testeurs » n'est donc
**pas** opérationnel aujourd'hui, même s'il est déclaré.

---

## 9. Hors-ligne et synchronisation

```
Poste terrain (PWA / kiosque / POS)
  ├─ cache applicatif (Service Worker, `sw-strategies.js` : /api/* → network-first,
  │   assets → cache-first, testé par `front/web-offline/tests/sw-strategies.test.mjs`)
  ├─ file locale + clé d'idempotence stable (`useOfflineSync`, `dispatch()`)
  └─ Edge node (LAN) ── /api/v1/edge/health ──► sync
                                               │
                                     SyncQueue : pending|processing|synced|conflict|failed
                                     SyncLog  : conflicts_detected / conflicts_resolved
                                     conflict_resolution : local_wins|cloud_wins|manual
```

**Vérifié :** le modèle de données du conflit existe (`SyncQueue`, `SyncLog`), la santé Edge
est testée (`edge-health.test.ts`), l'idempotence de rejeu est câblée pour KDS/POS. La
révocation d'appareil existe (`DELETE /api/v1/edge/{nodeId}`, scope tenant).

**Non vérifié / non revendiqué :** aucun test de bout en bout « coupure réseau réelle →
file visible → reconnexion → rejeu sans doublon → conflit arbitré » n'est exécuté en CI
aujourd'hui. Le hors-ligne ne doit donc pas être annoncé comme « validé terrain »
(§ `15_test_plan.md`, scénarios OL-1..OL-5).

---

## 10. Superadmin

`Modules/Platform` (88 fichiers) fournit : tenants et santé tenant
(`PlatformCompanyHealthService`), impersonation tracée (`PlatformImpersonationSession`),
outbox (`PlatformOutboxPublisher` + registry de consumers), observabilité notifications et
queues, supports/tickets, annonces, répertoire utilisateurs plateforme, suppression de
tenant inventorée (`TenantDeletionInventory`), feature kill-switch, réglages IA, leads
marketing, funnel d'acquisition. Console Vue : `front/admin-dashboard/src/views/{companies,
solutions,subscriptions,edge,support,users,system,…}`.

**Permissions :** toutes les routes sensibles portent `platform.permission:<domaine>.<action>` ;
les alias `/admin/*` historiques qui avaient perdu la garde ont été corrigés (#7973).

**Écart :** il n'existe **aucune vue unifiée du provisionnement** (étapes franchies, échecs
d'onboarding, blocages) ni de diagnostic d'installation/sync par tenant dans la console —
seulement des écrans par domaine (§ gap S-01).

---

## 11. Branding et pages d'erreur

Vérifié : `CompanyBrandingController` (`GET/PATCH /company/branding`), `favicon.svg`
premium, assets `public/brand/`, `manifest` route, `og-image`, écrans `error.tsx` et
`not-found.tsx` côté Next. Garde `check-brand-assets.mjs` (pas de damier incrusté).

**Écart :** aucune page `403` dédiée, aucune page `500` distincte par tenant, pas de
branding appliqué aux écrans d'authentification/onboarding/chargement au-delà des tokens
globaux, et la personnalisation par tenant n'est pas bornée par un contrôle
d'administration visible (§ gaps B-01..B-03).

---

## 12. Décisions d'architecture de cette mission

| Décision | Justification |
|---|---|
| **A1 — Ne pas créer d'application par verticale** | Le pack de configuration + module métier couvre déjà le besoin ; dupliquer multiplierait les bases de code et les permissions. |
| **A2 — Ne pas fusionner la branche `bc/bc-36-trade-os` dans cette branche** | PR #8487 ouverte, 207 fichiers, mergeable inconnu, base en retard ; la fusionner ici rendrait la PR de mission non relisible. Trade est référencé comme dépendance (§ `07_implementation_plan.md`). |
| **A3 — Onboarding universel = extension de l'orchestrateur existant** | `CompanyProvisioningService` + `ProvisionGuidedTrial` sont transactionnels et idempotents ; un second chemin créerait des doublons de tenants. |
| **A4 — Statut d'application dérivé, jamais saisi** | Règle #6697 déjà appliquée par `softwareAvailability()` ; l'étendre à la console superadmin. |
| **A5 — Pas de nouveau schéma pour le centre « Mes logiciels »** | Le catalogue est statique côté front + `EmployeeModuleGrant` côté tenant ; aucune table nécessaire. |
| **A6 — Hors-ligne déclaré par test, pas par intention** | Trois états seulement : `verified` (test CI rejouable), `implemented` (code présent, non testé E2E), `absent`. |

Détail complet et alternatives écartées : `09_adr.md`.

---

## 13. Risques d'architecture

| Risque | Impact | Traitement |
|---|---|---|
| Doublon de code de solution `restaurant` / `restaurantmanager` | Activation incohérente selon le chemin (vitrine vs survey) | ADR-04 : un seul code canonique par verticale, alias documenté |
| `manager_role` confondu avec rôle métier | Permissions de verticale non installées | `SolutionPermissionInstaller` obligatoire dans chaque manifest |
| Verticale déclarée `pilot` présentée comme disponible | Promesse commerciale fausse | Maturité exposée dans le catalogue et dans Mon Logiciel |
| Edge non testé de bout en bout | Perte de ventes terrain en cas de coupure | Scénarios OL-1..OL-5 bloquants avant tout pilote |
| CI `main` déjà rouge sur 17 checks | Impossible de distinguer une régression d'un état initial | Baseline figée dans `14_audit_baseline.md` |
