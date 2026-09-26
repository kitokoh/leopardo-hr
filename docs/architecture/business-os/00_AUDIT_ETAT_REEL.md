# AUDIT — État réel de Leopardo (Business OS)

> Date : 2026-09-26 · Commit audité : `7919a73` (2026-09-25) · Auteur : PM/Architecte (mission Business OS)
> Méthode : inspection réelle du code (4 axes : Core/tenancy, IA, modules métier, fronts/gouvernance). Aucune hypothèse prise pour vérité.

---

## 1. Résumé de l'audit

**Leopardo n'est pas un logiciel RH qu'il faudrait transformer en plateforme multi-métiers : c'est déjà une plateforme multi-métiers en cours de consolidation.** Le dépôt contient 32 modules métier dont 9 verticales (Restaurant, Travel, Edu, Health, Pharmacy, FuelStation, Hospitality, Retail, Delivery), un registre de solutions, un moteur de provisioning, un système d'IA outillé avec contrôle RBAC, et deux surfaces de commerce public. La vision « Business OS » de la mission est donc **largement amorcée** — le risque n'est pas de devoir tout construire, mais de **reconstruire par-dessus l'existant** et d'aggraver la dette de convergence.

| Axe | État | Verdict |
|---|---|---|
| Multi-tenancy | Custom, schéma partagé + `company_id`, fail-closed | **Solide** — ne pas remplacer |
| RBAC | Custom 3 couches (rôles, grants modules, assignments ressources) | **Solide** — étendre, pas refondre |
| Solutions/Features | SolutionCatalogue + SolutionActivator + feature flags | **Existe, mais triple source de vérité** |
| Provisioning | CompanyProvisioningService + SetupInterviewPlanner + DemoDataKit | **Existe et mature** |
| Modules métier | 32 modules, 10 production-ready | **Réel** — dette concentrée dans le cœur HR |
| IA | Orchestrateur + tools RBAC fail-closed + 3 providers | **Avancé** — pas de router, pas de RAG, 1 fuite PII |
| Commerce public | marketplace retail + travel-web + vitrines | **Existe déjà (×3 surfaces)** |
| Marketplace multi-tenant | Partiel, par verticale (retail, travel) | **Amorcé** — pas de super-marketplace global |
| Vision « Company OS » | Déjà documentée (P03, GOTO_MARKET, PLAN_100PCT, 25 ADR) | **Ne pas redocumenter** |

---

## 2. Tenancy — modèle réel

- **Tenant = `Company`** (`api/app/Core/Tenant/Domain/Models/Company.php`). Table `public.companies` : UUID, `slug`, `status` (active/trial/suspended/expired), `plan_id`, `features` JSONB, `metadata` JSONB.
- **Isolation = schéma partagé `shared_tenants` + colonne `company_id` + global scope Eloquent** (`api/app/Shared/Traits/BelongsToCompany.php`) : fail-closed (marqueur `tenant_scope_required`), force `company_id` au `creating`, bloque sa modification, bypass nommés et audités (`forCompany`, `crossTenantForSystemTask`).
- Le mode schema-per-tenant (`tenancy_type='schema'`) est **verrouillé et mort** (abort 422 à la création) — vestige à nettoyer, pas à ressusciter.
- **Résolution du tenant** : pas de sous-domaine ; tenant dérivé de l'employé authentifié Sanctum (`TenantMiddleware`), fallbacks via abilities du token et `public.user_lookups`.
- **Contrat runtime complet** : jobs `TenantScopedJob`, events `TenantEventDispatcher`, cache `tenant:{id}:*`, garde `TenantContextGuard` (cf. `docs/architecture/TENANT_RUNTIME_CONTRACT.md`).
- **Trois acteurs auth** : `Employee` (tenant, canonique), `User` (compte personnel global), `SuperAdmin` (plateforme, guards dédiés).

### Réponses aux questions de la mission

| Question | Réponse réelle |
|---|---|
| Un client peut-il avoir plusieurs entreprises ? | **Partiellement.** `public.user_employee_links` relie un compte `User` à des employés de plusieurs companies, mais l'auth opérationnelle est mono-tenant : `employees.email` est **unique global**, pas de « company switch » en session. Multi-entreprises = comptes distincts aujourd'hui. |
| Une entreprise peut-elle avoir plusieurs établissements ? | **Oui.** Table `sites` (géofence incluse) + établissements verticaux : `RestaurantBranch`, `HospitalityProperty`, `TravelOffice/Station`, `EduCampus`, `FuelStation`. |
| Un utilisateur peut-il appartenir à plusieurs organisations ? | Table de liaison oui, session unifiée non. |
| Un rôle peut-il être limité à un établissement ? | **Oui**, par `employee_resource_assignments` (`resource_type`, niveaux view<operate<manage, fail-closed progressif) — mais seulement ~6 policies le consomment à ce jour. |
| Chaîne de restaurants / hôtel multi-sites / école multi-campus ? | **Représentables** : branches/properties/campus sont des `resource_type` enregistrés (`api/config/resource_types.php`). |
| Utilisateurs externes / partenaires / franchisés ? | **Non modélisés** comme acteurs cross-tenant. Les franchisés seraient des companies distinctes ; aucune notion de groupe. |

**Conclusion tenancy : le modèle Tenant → Company → Sites/Établissements couvre les cas demandés. Ne PAS créer de concept Organization/BusinessGroup/Workspace/Membership.** Le seul manque réel est le **multi-org en session** (switch) et l'**email unique global** qui le bloque structurellement — évolution ciblée possible, pas refonte.

---

## 3. RBAC — modèle réel

100 % custom (pas de spatie), 3 couches :

1. **Rôles colonnes** : `employees.role` (manager/employee/ordinary) + `employees.manager_role` (principal, rh, dept, comptable, superviseur, marketing, manager, server, kitchen, rider — CHECK constraint SQL). Scoping d'équipe fail-closed : `dept` → département, `superviseur` → subordonnés directs.
2. **Grants modules** : `employee_module_grants` + enum `ModuleKey` fermé + middlewares `api.module.grant:<key>`.
3. **Assignments ressources** : `employee_resource_assignments` (view/operate/manage) + `ResourceTypeRegistry` + trait `ChecksResourceScopedAccess`.

RBAC plateforme : `super_admins.platform_role` → enum `PlatformRole` → matrice `PlatformPermission` (22 permissions), middleware dédié.

**Dettes** : ajouter un `manager_role` = migration de CHECK constraint (déjà écrasé une fois par une migration ultérieure) ; `SolutionManifest::permissions()` déclaré mais non câblé à l'enforcement.

---

## 4. Features / Solutions / Provisioning — l'existant

### 4.1 Trois systèmes distincts (à ne pas confondre)

1. **Feature flags tenant** : registre versionné `api/config/feature-flags.php` + allowlist `Company::KNOWN_MODULES` (~20) + `Company::hasFeature()` (kill switch DB → JSONB → défaut registre). **Dette n°1 : triple source de vérité** (registre config + KNOWN_MODULES + `metadata.modules`) — désynchronisations répétées documentées (#7220, #7235, #7432, #7785, #7976). Chaque nouveau module exige 3 enregistrements manuels.
2. **Solution registry** (packs verticaux) : `App\Core\Solutions` — contrat `SolutionManifest` (code, maturity pilot|production|placeholder, requiredModules, sensitiveData, permissions), `SolutionCatalogue` (allowlist, inversion de dépendance Core↛Modules), `SolutionActivator` (idempotent, fail-closed, vérifie dépendances, audit + événement `SolutionActivated`). **8-9 verticales enregistrées.** L'activation = un feature flag company. Dettes : contrats legacy dupliqués (Delivery, RestaurantManager ont leur propre manifest), permissions non enforceées.
3. **API Feature Registry** (`App\Modules\Billing\...\Feature`) : inventaire des endpoints pour le manifeste mobile — **pas du feature-gating**. Nommage quasi identique au précédent = confusion avérée.

### 4.2 Provisioning réel

- **Moteur** : `CompanyProvisioningService::provisionSharedCompany()` — validation pays, allowlist solutions fail-closed, slug, company trial, manager principal, templates sectoriels (`SectorTemplateService`), seed des 10 étapes d'onboarding, activation des solutions. Transactionnel.
- **3 points d'entrée** : console plateforme (`POST /platform/companies`, permission `companies.provision`), approbation de `company_requests`, self-service trial (`/trial/signup` + OTP → `ProvisionGuidedTrial` idempotent).
- **Onboarding** (module mature) : checklists, `SetupInterviewPlanner` — **Q&R déterministe → plan `{solutions, tools}` exécuté via `SolutionActivator`** (#7493/#7494), données démo idempotentes (`DemoDataKit`).
- **Platform** (88 fichiers) : console super-admin complète (companies, plans, impersonation, kill switches, tickets, outbox, réglages IA, métriques, suppression tenant auditée).

**Conclusion : la chaîne Industry → Solution → Modules → Provisioning existe déjà.** Le « Provisioning Engine » de la mission est implémenté. Ce qui manque : l'unification des sources de vérité et le câblage permissions des manifests.

---

## 5. Cartographie des 32 modules

| Maturité | Modules |
|---|---|
| **Production-ready (10)** | Payroll (159 fichiers, 106 tests), TravelAgency (375, 113), RestaurantManager (380, 72), Attendance (109, 47), Accounting (131, 46), EduManager (158, 41), FuelStation (181, 32), CRM (208, 33), HR (106, 28), Delivery (71, 22), Platform (88, ~40) |
| **Fonctionnel (13)** | Billing, Marketing, Planning (**2 tests seulement** — propriétaire canonique absences/frais !), Notification, Onboarding, Cameras, Cabinet, EdgeSync, Retail, HealthManager, Pharmacy, Communication, HospitalityManager, Catalog, Showcase |
| **Partiel (5)** | Recruitment, Fleet (1 test), Expense, Growth (0 modèle propre — parasite de Billing), Absence (façade actée sur Planning) |
| **Squelette intentionnel (1)** | Restaurant (manifest + survey uniquement) |

Constats :
- **Structure DDD uniforme** (Application/Domain/Infrastructure/Interfaces) sur 32/32 modules, gardes CI d'isolation, scaffolding `MakeModuleCommand`.
- **Couche Application absente dans 8 modules** (EduManager, HealthManager, Pharmacy, HospitalityManager, Communication, Catalog, Fleet ; HR n'en a que 3 fichiers) → controllers épais.
- **Cycles d'imports dans le cœur historique** : HR↔Attendance↔Planning↔Payroll, Notification→5 modules — 55 paires allowlistées dans `dev-hub/tools/module-isolation-allowlist.txt`. Bloque toute extraction en packages.
- **Duplication de patterns d'infrastructure entre verticales** : outbox ×4 (Travel, Restaurant, Edu, Platform), webhooks ×3 (Billing, Travel, Platform), POS ×2 (Restaurant, Retail), stock ×4, facturation verticale ×2 (Health, Edu) vs Billing/Accounting transverses.
- **Frontières non tranchées** : Notification vs Communication (threads dupliqués), TravelHotel vs HospitalityManager, TravelVehicle vs Fleet, Restaurant vs RestaurantManager (deux manifests restaurant).
- **Couverture tests très inégale** : Payroll 106 / Travel 113 vs Planning 2, Fleet 1, Expense 2.

---

## 6. IA — l'existant (audit détaillé)

### 6.1 Capacités réelles (`api/app/AI`)

- **Orchestrator** : conversation, politique cloud, budgets tokens, boucle tool-calling (max 3 itérations), audit. Mature.
- **IntentEngine** (~800 lignes, god-class) : dispatch des tools, vérification matrice RBAC **fail-closed avant tout effet de bord**, 19 read-handlers inline, flux de confirmation pour les écritures.
- **ToolRegistry + AIToolDefinitionRegistry** : enregistrement déclaratif par BC (6 providers), filtrage rôle + permissions.
- **ToolPermissionPolicy / WriteToolPolicy** : matrices versionnées dans `config/ai.php`, fail-closed sur outil inconnu.
- **WriteActionRunner** : 8 write-tools avec **parité stricte avec les services REST canoniques** + confirmation humaine (`PendingActionStore`, one-shot, TTL 15 min).
- **Sécurité** : tenant résolu depuis la session (jamais depuis les arguments LLM), `company_id` dans chaque handler, **l'IA ne génère jamais de SQL**, quotas plan (`ai_usage_counters` + `ai_credit_ledger`), budgets fail-closed, DLQ dédiée, export RGPD, `AiCloudPolicy` (flag tenant `ai_cloud_allowed`, OFF par défaut), `PrivacySanitizer` (regex PII).
- **Providers LLM** : **OpenAI, Anthropic, Groq** + Fake (défaut hors prod). Un seul driver actif (`AI_LLM_DRIVER`). **Pas de router, pas de fallback, pas de streaming, pas de retry.**
- **Core/AI** : ports hexagonaux (STT via Groq Whisper, FaceVerification, ModelInference — adaptateurs `Unavailable*` fail-closed par défaut). Consommé par kiosk facial, OCR compteurs fuel, voice.
- **Predictions/Workflows** : heuristiques SQL déterministes (pas de ML/LLM) — honnête mais « IA » marketing.
- **Assistants nommés** : **aucun** — ni Yara ni Léa. Prompt système générique de 30 lignes.
- **Inexistant** : RAG, embeddings, pgvector, knowledge base, mémoire long terme (conversation JSON trimée à 50 messages).
- **Clients** : seul le mobile manager consomme `/ai/chat` ; **aucun front web** ; le flux confirm/reject, la voix et l'agent n'ont **aucun client** (capacités dormantes).

### 6.2 Risques sécurité constatés

1. **Fuite PII vers Anthropic** : `PrivacySanitizer` ne nettoie que les `content` string ; dans la branche Claude, les `tool_result` (tableaux contenant noms/emails d'employés) partent **non sanitisés**. **Correctif prioritaire.**
2. `ai_audit_logs` stocke prompt + réponse en clair (10 000 car.), sans rétention visible.
3. Vue cross-tenant super-admin de toutes les conversations (`/admin/ai/conversations`) — surface RGPD à encadrer.
4. `PendingActionStore` sur Cache : durabilité dépendante du driver.
5. Dette : write-tool legacy `approve_absence` qui bypass l'Action canonique et n'émet pas les événements `AbsenceApproved` ; IntentEngine god-class malgré le pattern déclaratif ; double source outils DB/code gardée par 3 tests.

---

## 7. Fronts, commerce public, edge

| Front | Stack | Rôle | Maturité |
|---|---|---|---|
| `front/web` | Next.js 16 | Landing + **portail tenant** (36 pages) + vitrines/shop/restaurants publics | Le plus gros (631 fichiers) |
| `front/marketplace` | Next.js 16 | **Marketplace publique retail** (catalogue, boutiques, checkout invité, suivi, avis) | Fonctionnelle (#7809) |
| `front/travel-web` | Next.js 16 | **Billetterie publique inter-agences** (résa invitée, e-billet PDF signé, compte client) | Fonctionnelle (#7736) |
| `front/admin-dashboard` | Vue 3 + **JS pur** | Cockpit super-admin plateforme (~33 kLOC) | Mature, dette typage (ADR-0023) |
| `front/mobile_apps` | Flutter (melos) | **8 apps métier** + package `leopardo_core` | Mature |
| `front/web-offline` | Next.js PWA | UI du nœud Edge on-prem | Petit, contractualisé |
| `front/zkteco-kiosk` | HTML/JS + bridge Python | Pointage biométrique offline-first | Minimal, testé |

**Le commerce public existe déjà, en trois exemplaires** (marketplace retail, travel-web, vitrines dans web) — y compris l'agrégation inter-tenants par verticale (boutiques de plusieurs companies retail ; trajets de plusieurs agences). Frontières produit entre `front/web` (shop/restaurants/vitrine) et `front/marketplace` à arbitrer.

**Edge** = nœud on-premise (sync offline, kiosk, vidéo caméras RTSP) — **aucune IA au edge**, aucun Cloudflare Workers. L'idée « Cloudflare Workers AI » de la mission n'a pas de base existante.

---

## 8. Gouvernance, spec-kit, docs, déploiement

- **Spec-driven très avancé** : 248 specs `.specify/features/`, 10 commandes (specify → clarify → plan → tasks → analyze → implement…), constitution non négociable (spec-first, multi-tenant inviolable, golden tests paie), presets métiers, `taskstoissues`.
- **Vision déjà documentée** : P03 « suite métier » (`docs/REFERENTIEL_PRODUIT/POSITIONNEMENT_SUITE_METIER.md`), « Company OS » (`docs/GOTO_MARKET/2026_MARKET_LAUNCH_COMPANY_OS/`), `PLAN_100PCT.md`, 25 ADR, registre de 23 Bounded Contexts gardé en CI. **La mission ne doit pas recréer cette documentation — elle s'y rattache.**
- **Tension stratégique documentée** : FREEZE 60 jours (#5147) + ADR-0012 FOCUS (profondeur noyau paie DZ) **vs** absorption continue de nouvelles verticales. La gouvernance actuelle freine déjà l'usine à features — signal à respecter.
- **CI** : ~75 workflows, gardes nombreuses, coverage gate 65 %, mono-owner `@kitokoh` (bus factor = 1).
- **Déploiement** : Render free-tier — **queue + scheduler dans le conteneur web qui s'endort** (cron perdus, décision billing #7649 documentée non exécutée) ; DB Neon ; fronts Vercel/Cloudflare Pages ; prod déjà restée 2 jours derrière main sans alerte (#8092).

---

## 9. Dettes classées par impact

| # | Dette | Impact | Effort |
|---|---|---|---|
| D1 | Triple source de vérité des features (config + KNOWN_MODULES + metadata) | **Critique** — bugs récurrents à chaque module | Moyen |
| D2 | Fuite PII tool_result vers Claude | **Critique** — RGPD/sécurité | Faible |
| D3 | Prod free-tier : queue/scheduler éphémères | **Élevé** — tâches perdues, facturation | Décision + faible |
| D4 | Cycles d'imports cœur HR (55 paires allowlistées) | **Élevé** — bloque modularité réelle | Élevé (progressif) |
| D5 | Planning propriétaire canonique avec 2 tests | **Élevé** — absences/frais critiques | Moyen |
| D6 | Couche Application absente (8 modules) | Moyen — controllers épais | Moyen |
| D7 | Outbox ×4, webhooks ×3, POS ×2, stock ×4 | Moyen — dérive croissante à chaque verticale | Moyen |
| D8 | Manifests solution legacy dupliqués + permissions non câblées | Moyen | Faible |
| D9 | Email unique global bloquant le multi-org session | Moyen — bloque franchises/groupes | Élevé (migration) |
| D10 | Abonnement représenté 3 fois (companies.plan_id, subscriptions, plans) | Moyen | Moyen |
| D11 | Write-tool legacy bypassant l'Action canonique (absences) | Moyen — cohérence métier | Faible |
| D12 | Docs d'architecture obsolètes sur ≥5 modules | Faible | Faible |
| D13 | Admin-dashboard JS pur (ADR-0023 non démarré) | Faible/moyen | Élevé |
| D14 | Search_path vestigiel + mode schema mort | Faible — pièges résiduels | Faible |
