# Audit Phase 0 — Verticale Leopardo International Trade OS (BC-36)

> Date : 2026-10-14 · Auteur : Zentor (agent DEV) · Base auditée : `main` @ `e4c07a9f2`
> Mission : conception et implémentation de la verticale « International Trade OS »
> dans Leopardo Business OS (dépôt `kitokoh/leopardo-hr`).
> Compagnon : `docs/specifications/VERTICAL_TRADE_OS.md` (conception validée par la directive de mission).

---

## 1. Structure réelle du monorepo

| Zone | Contenu | Stack |
|---|---|---|
| `api/` | Backend métier, monolithe modulaire DDD | Laravel 12 / PHP 8.4 / PostgreSQL 16 (+PostGIS) / Redis |
| `front/web/` | Vitrine publique + dashboard SaaS | Next.js 16 (App Router), React 19, TS, Tailwind 4, Jest + Playwright |
| `front/admin-dashboard/` | Console superadmin plateforme | Vue 3.5 + Vite + Pinia, Tailwind 4, Playwright (pas de tests unitaires — décision STACK.md) |
| `front/marketplace/` | « Leopardo Marché » — marketplace publique **Retail (BC-17)**, sans rapport avec la vitrine | Next.js 16 |
| `front/travel-web/` | Front public TravelAgency | Next.js |
| `front/mobile_apps/` | 8 apps Flutter + package `leopardo_core` | Flutter / Riverpod (melos) |
| `edge/`, `front/web-offline/`, `front/zkteco-kiosk/` | Bridge on-prem ZKTeco | Caddy/supervisord, PWA, HTML/JS |
| `shared/i18n/` | Source de vérité i18n (fr/en/ar/tr) + scripts de sync | JSON |
| `dev-hub/` | SDK/outils/gardes CI (`check-*.sh`), openapi miroir | Node/Python |
| `docs/` | Documentation (specifications/, audits/, GOUVERNANCE/, architecture/…) | Markdown |

Backend : 35 modules sous `api/app/Modules/` + socle `api/app/Core/` (Auth, Tenant, Feature, Solutions, AI, Notifications, Privacy, Seed) + `api/app/Shared/` (traits, contrats inter-modules). Règle : tout nouveau code métier dans `api/app/Modules/<Nom>/` (structure DDD `Application/Domain/Infrastructure/Interfaces/Providers`, template `api/stubs/module-template/`).

## 2. Catalogue de solutions & verticales (existant)

- **Registry PHP en mémoire, zéro table DB** : `App\Core\Solutions\SolutionCatalogue` (fail-closed). Chaque verticale fournit un manifest (`App\Core\Solutions\Contracts\SolutionManifest` : `code/name/maturity/industry/description/requiredModules/optionalModules/sensitiveData/permissions`), enregistré via `$this->app->resolving(SolutionCatalogue::class, …)` dans le provider du module (pattern BOS-014).
- **Activation = clé JSONB** `companies.features.<code>` posée par `SolutionActivator::activateWithDependencies()` (idempotent, transactionné, audité `AuditLog`, événement `SolutionActivated`, refuse les deps manquantes en chemin strict).
- **Permissions de manifest** installées par `SolutionPermissionInstaller` (BOS-013) : 1 ligne `employee_module_grants` par permission pour les managers `principal` — **sans étendre le registre fermé `ModuleKey`** (registre réservé à la délégation manuelle HR).
- 12 solutions enregistrées : `fuel_station, edumanager, healthmanager, restaurant, restaurantmanager, travelagency, pharmacy, fundraising, retail, delivery, hospitality, vtc`.
- `SolutionIndustry` : registre fermé versionné, extension par nouvelle case (précédent `Mobility` pour VTC).
- **Registre déclaratif unique** : `App\Core\Feature\Domain\ModuleRegistry` (BOS-011/ADR-0026) — toute nouvelle clé solution DOIT y être déclarée (kind/scope/default/killable/platform_flag/exposed_in_flags/platform_exposable/metadata_mirror/known_order) + bump `VERSION`. Double écriture transitoire : `config/feature-flags.php` et `Company::KNOWN_MODULES` sont des projections/legacy encore synchronisées manuellement (5 incidents historiques de désync : #7220, #7235, #7432, #7785, #7976 → vérifier les 3 points).
- Console OPS : `leopardo:solution:activate {company} {code}`.
- Survey vitrine générique : `Core/Solutions/Survey/*` (endpoints publics `GET|POST /solutions/{code}/survey`) — seul `restaurant` l'implémente ; le point d'extension existe pour `trade`.

## 3. Onboarding & provisionnement (existant)

- **Deux provisionneurs** (dette connue, pas de `TenantProvisioningOrchestrator` unique) :
  - Admin plateforme : `Platform/Infrastructure/Services/CompanyProvisioningService::provisionSharedCompany()` (transaction, slug résilient course, employee principal, templates secteur, `SeedDefaultSteps`, activation solutions, invitation mail).
  - Self-service trial : `Billing/…/SelfServiceTrialController` + `VerifyTrialSignup` (OTP e-mail, verrou atomique anti double-provisioning #2996, `metadata.vertical = solutions[0]`).
- **Idempotence** : verrou statut `pending→processing` sur `trial_provisionings`, slug savepoint+retry, activateur idempotent (`already_active`), listeners `SolutionActivated` avec `insertOrIgnore`.
- Onboarding post-login : table tenant `onboarding_steps` (+ seed paresseux), entretien `SetupInterviewController` (#7493, clôture → `SolutionActivator::activateWithDependencies()`), checklist « go-live », écrans bienvenue, données démo (`DemoDataRegistry`).
- Signup vitrine « pack-aware » (#8462) : `POST /api/v1/trial/signup {solutions: [...]}` validé contre le catalogue (422 fail-closed) ; le front passe le pack via `?pack=<slug>` → mapping fail-closed `front/web/src/modules/vitrine/lib/pack-signup.ts`.

## 4. Company, utilisateurs, rôles, permissions (existant)

- `Company` (`public.companies`, UUID) — frontière tenant ; `tenancy_type='shared'` verrouillé (ADR-0028) : **isolation par colonne `company_id`** (schéma partagé `shared_tenants` + global scope Eloquent `BelongsToCompany`, fail-closed #3727, anti-spoof écriture #7646). Bypass uniquement via wrappers nommés (`forCompany`, `crossTenantForPlatformAdmin`, `crossTenantForSystemTask`).
- `Employee` (schéma shared, Sanctum) = compte applicatif tenant ; `User` (public) = identité plateforme ; pivot `UserEmployeeLink` (exception tenant-scope canonique).
- RBAC maison (pas de spatie) : `employees.role` ∈ `manager|employee|ordinary`, `manager_role` ∈ `principal|rh|dept|comptable|superviseur` ; policies (~40) avec garde cross-tenant systématique ; middlewares `manager`, `manager_role`, `api.manager`, `api.module.grant`, `admin` (= principal).
- Délégation fine : `EmployeeModuleGrant` (#7761, registre fermé `ModuleKey`) + accès par ressource nommée `EmployeeResourceAssignment` (#7597-7600, niveaux view<operate<manage).
- Rôles de verticale : pattern VTC — `VtcRoleResolver` + `EnsureVtcRoleMiddleware` (rôles métier résolus depuis profil employé + grants de manifest, deny-by-default). **Réutilisable pour Trade.**
- Superadmin : modèle dédié `SuperAdmin` (guards `super_admin_api/web`), enums `PlatformRole`/`PlatformPermission` (~20 permissions fermées), middleware `platform.permission`, impersonation auditée, lockout + 2FA. Console : ~30 `Platform*Controller` sous `/platform/*` (jamais de middleware `tenant`).
- Fichiers : disques `local`/`public`/`private`/`s3` ; pattern contrôle d'accès 404 anti-énumération (Cabinet #5445) ; partage public par token signé (Cabinet, AccountingDocumentShare).
- Audit : trait `Auditable` → table `audit_logs` ; `DataAccessAuditLogger` (RGPD S-2) ; guards CI `check-tenant-scope-models.sh`, `check-without-global-scope.sh`, exceptions versionnées `dev-hub/governance/tenant-scope-exceptions.json`.

## 5. Conventions (CONVENTIONS.md / AGENTS.md / gardes CI)

- PHP 8.4 `strict_types`, PHPStan level max sur les fichiers modifiés (diff-gate baseline), level 8 strict Core/Modules/Shared (baseline gelée — toute nouvelle erreur = CI rouge), Pint.
- Migrations : PostgreSQL only, idempotentes, résolution de schéma via `current_schemas(false)` (jamais `Schema::hasTable` nu), préfixe de séquence unique (garde `check-migration-basename-collisions.sh`), **une table = une migration** (garde `migration-duplication-guard.yml`, zéro tolérance).
- i18n : jamais de littéral accentué hors `__()` sur Controllers/Application/Exceptions (garde PA2-I18N-007) ; 4 langues fr/en/ar/tr, source `shared/i18n/locales/` + `node shared/i18n/sync/sync-web.js`.
- API : un concept = un chemin canonique ; POST pour actions métier, PUT/PATCH pour modification ; pas de re-déclaration `throttle:api` ; OpenAPI obligatoire (`api/openapi.yaml` canonique + miroir généré `dev-hub/openapi/v1.yaml` vérifié par `openapi-ci.yml` + couverture de routes).
- Règle d'or nouveaux modules : spec `docs/specifications/` **avant** issues (le présent audit + spec satisfont cette règle ; la directive de mission explicite de l'owner vaut validation du périmètre).
- Protocole branches : lot d'un même BC = une branche `bc/<code-bc>-<slug>`, un commit par issue, une PR qui ferme toutes les issues (`Closes #N` dans le body). Registre BC : `dev-hub/governance/bounded-context-registry.json` (BC-01…BC-35) + label GitHub associé.
- CHANGELOG.md obligatoire sous `[Unreleased]` (Keep a Changelog).
- Interdiction absolue d'imports croisés de modules : consommation via contrats `App\Shared\Contracts\*` uniquement (allowlists immuables, garde Module Structure Validator).

## 6. Superadmin (existant)

Console `front/admin-dashboard` (Vue 3, routes `src/router/index.js`, navigation déclarative `src/navigation/navigation.js` filtrée par permission, services axios par domaine) + endpoints `/platform/*` (module Platform). Existant : companies (détail, features GET/PATCH — l'activation d'une solution y est déjà possible une fois la clé déclarée au ModuleRegistry), plans, billing, analytics/funnel/globe, impersonation, kill switch, webhooks, survey stats, AI settings. **L'ajout d'une section = vue + route + entrée navigation avec `permission`** — pas de seconde console.

## 7. Notifications, fichiers, messagerie, IA, async, audit (existant)

- **Notification** : dispatcher + 5 canaux (app/email/push/sms/whatsapp), préférences, templates Blade, messagerie interne `ConversationThread/Message` (1 PJ).
- **Communication** : boîte mail unifiée (threads/messages multi-PJ, intégration Gmail OAuth, relances auto, réponses IA à approuver) — pertinente pour correspondance fournisseurs.
- **CRM** : accounts/contacts/leads/pipelines/opportunities/activités/timeline/segments/campagnes — base « clients & prospects » du commerce.
- **Accounting** : **devis (Quote), pro forma (Proforma), factures** (`AccountingDocument` + lignes, statuts draft/sent/partially_paid/paid/…, partage public + checkout), **multi-devise** (colonnes `currency` + `exchange_rate`, contrat `CurrencyRateProviderInterface`, provider manuel, registre ~21 devises).
- **Core/AI** : LLMClient résilient + circuit breaker, ToolRegistry (permissions par rôle), **PendingActionStore (confirmation humaine des écritures)**, WriteIdempotencyStore, TokenBudgetGuard, PrivacySanitizer, AIAuditLog, dead letters, crédits IA.
- **Async** : queues database, pattern **Outbox** (CRM/Travel/Restaurant) + dead letters, jobs d'export.
- **Audit** : cf. §4.

## 8. Services externes déjà intégrés

- Paiements : Stripe, Chargily (webhooks signés fail-closed), CinetPay / PayDunya / PVIT (adaptateurs Fundraising — pattern d'adaptateur config-driven à suivre).
- Gmail/Google OAuth (Communication, Marketing), WhatsApp entrant (CRM channel), push (FCM), Sentry, socket.io (globe temps réel).
- Géospatial : **PostGIS core `geo`** (distances, plus-proches — consommé via `App\Shared\Contracts\Geo\GeoServiceContract`).
- FX : **aucun provider temps réel** (manuel only, #5272 hors périmètre) ; deux implémentations de conversion déterministe : Accounting (taux sur document) et Travel (`travel_currency_rates` + `TravelCurrencyConverter`, math entière, paires inverses).
- Secrets : config côté serveur (`config/services.php`), jamais exposés ; garde `secret-scan` (TruffleHog) + `ci-secrets-doc-guard`.

## 9. Tests existants réutilisables

- Harnais tenant : `tests/RefreshTenantDatabase.php`, `tests/Support/SwitchesTenantContext.php`, `CreatesMvpSchema.php`, factories `CompanyFactory`/`EmployeeFactory`/`UserFactory`.
- Patterns d'isolation : `tests/Feature/Security/TenantModelIsolationTest.php` + matrices par module (`TravelIsolationTest`, `AccountingTenantIsolationTest`, `CrmCrossTenantHarnessTest`…).
- Environnement local reconstitué et **vérifié** : PHP 8.4.26 + PostgreSQL 16/PostGIS + Redis — `TenantModelIsolationTest` : **19/19 vert** (2026-10-14).
- Front/web : Jest + Testing Library, 42 specs Playwright, gardes `check:french-copy`, `check:mojibake`, `check:orphans`.
- CI : 78 workflows ; checks **requis au merge** : `PHPStan — Strict`, `Module Structure Validator`, `Frontend — ESLint + TypeScript`, `actionlint (+ shellcheck)` ; `strict: true` (branche à jour requis) ; `enforce_admins: true` ; 0 review requise. `main` @ `e4c07a9f2` : **les 4 checks requis sont verts** (échecs `Déploiement aligné dev/prod` = déploiement, non requis, préexistants — à ne pas imputer à cette mission).

## 10. Risques de régression & dépendances à préserver

1. **Triple enregistrement feature flags** (ModuleRegistry + config/feature-flags.php + Company::KNOWN_MODULES) — historique d'incidents ; vérifier la parité (garde `horizontal-tools-parity`, tests Feature registry).
2. **PRs ouvertes concurrentes** : #8472 (vitrine pack VTC — touche `VerticalsSection.tsx`, `Navbar.tsx`, `pack-pages.ts`), #8470 (« Mon Logiciel » — espace tenant, probable `client-features.ts`), #8471 (VTC lot 3). Surfaces Trade volontairement additives et minimales sur ces fichiers ; rebase si l'une merge avant.
3. **CHANGELOG.md** volumineux (514 Ko) — entrées courtes sous `[Unreleased]` uniquement.
4. **Saturation CI** (#6928, merge-quota 25/jour) : regrouper les commits par lot, une seule PR.
5. **`tenant_scope_exceptions.json`** : tout modèle Trade a `company_id` + `BelongsToCompany` — aucune exception à déclarer.
6. **OpenAPI** : toute route Trade documentée sinon `openapi-ci.yml` rouge ; miroir/SDK régénérés.
7. **Anti-over-promise** (`public-promises-guard.yml`) : la copy vitrine Trade ne doit promettre aucune capacité non livrée (douane/FX externe non configurés = « aide à la préparation », jamais « dédouanement automatique »).

## 11. Classification A/B/C/D des capacités requises

| Capacité Trade | Classe | Base existante / action |
|---|---|---|
| Enregistrement verticale + feature flag + activation/provisioning | **A** | SolutionCatalogue/SolutionActivator/ModuleRegistry — ajouter manifest `trade` + clés (3 points §10.1) |
| Permissions de verticale (install principal, deny-by-default) | **A** | SolutionPermissionInstaller + pattern VtcRoleResolver/EnsureTradeRoleMiddleware |
| Onboarding vitrine pack-aware (slug → solutions[]) | **A** | `pack-signup.ts` + `/trial/signup` — ajouter slugs Trade |
| Survey vitrine par solution | **B** | contrat `SolutionSurvey` + endpoints génériques — implémenter `TradeSurvey` (seul restaurant l'a fait) |
| Profil commercial d'entreprise (direction import/export, marchés, catégories) | **C** | `trade_company_profiles` (1:1 Company) — pas d'équivalent |
| Catalogue produits B2B (specs, HS, origine, MOQ) | **B/C** | Catalog (vitrine simple) et RetailProduct (stock/POS) inadaptés → `trade_products` ; catégories globales v1 = liste contrôlée |
| Clients/prospects/fournisseurs (CRM) | **B** | CRM accounts/contacts complet → satellite `trade_counterparties` (rôles supplier/carrier/…, statut vérification) sans modifier le CRM |
| RFQ multi-fournisseurs + invitations + capture de réponses | **C** | aucun équivalent (seuls devis *clients* existent) → `trade_rfqs(+lines+invitations)` |
| Offres/quotations versionnées + négociation | **C** | `trade_quotes(+lines)` (direction incoming/outgoing, versioning `supersedes_id`) |
| Pro forma / facture commerciale | **B** | Accounting `DocumentType::Proforma/Invoice` — génération via contrat `App\Shared\Contracts\*` (jamais d'import direct) ; v1 : marquage + lien document, génération Accounting câblée si contrat disponible sinon reportée |
| Commandes + expéditions + suivi | **B/C** | cycle fulfillment Retail (domestique) inadapté au fret international → `trade_orders` + `trade_shipments` (pattern d'états inspiré) |
| Demandes/offres de transport (fret) | **C** | Delivery = dernier km ; Vtc = courses — `trade_transport_requests(+quotes)` |
| Dossiers de transaction + messages + PJ | **B/C** | Communication (mailbox Gmail) trop lourd, ConversationThread interne → `trade_deals` + `trade_messages` (PJ disque `private`, 404 anti-énumération) |
| Documents d'opération | **B** | pattern Cabinet (disk private, 404, token de partage) → `trade_documents` |
| Historique des événements | **B** | Auditable (technique) + `trade_events` (timeline métier, pattern outbox non requis v1) |
| Calcul coût total rendu (landed cost) déterministe | **C** | `TradeLandedCostCalculator` (math entière, statuts confirmed/estimated/user_input/unavailable) + `trade_cost_estimates(+lines)` |
| Incoterms 2020 + répartition acheteur/vendeur | **C** | enum `TradeIncoterm` + carte de responsabilités (0 occurrence existante) |
| Devises/pays supportés | **A** | `AccountingCurrencies` (~21) + `App\Support\CountryDefaults` (21 pays) |
| Taux de change datés + source | **B/D** | contrat module `TradeExchangeRateProvider` + provider **manuel** (saisi, daté, sourcé) ; adaptateur HTTP externe : interface + squelette **non configuré → indisponible affiché** (D : clé/compte à décider) |
| Aide douane (checklist docs, droits estimés, infos manquantes) | **C/D** | `trade_customs_cases` déterministe (données saisies) ; provider tarifaire externe = interface + null-provider (D) |
| Vérification d'entreprises | **D** | interface + null-provider v1 (sources par pays à décider) |
| Cotations transporteurs / suivi logistique externe | **D** | cycle interne livré ; APIs carriers = interfaces documentées, non configurées |
| IA commerciale/documentaire | **B (reporté)** | Core/AI complet (ToolRegistry, PendingActionStore) — v1 : pas d'outil IA Trade (reporté, cf. spec §12) |
| Superadmin : activation, stats, suivi intégrations | **A/B** | activation déjà possible (features) ; ajouter `PlatformTradeController` (stats + statuts d'adaptateurs, permission existante) + section admin-dashboard |
| Marketplace publique mondiale / recherche fournisseurs | **Hors périmètre v1** | explicitement différé par la mission (le bouton Trade + parcours + activation sont prioritaires) |

**Légende** : A = réutilisable tel quel · B = existe, extension nécessaire · C = à créer · D = dépend d'une API externe ou d'une décision métier.

## 12. État GitHub au 2026-10-14

- `main` vert sur les 4 checks requis (HEAD `e4c07a9f2`) ; échecs préexistants non requis : `Déploiement aligné (dev|prod)` (déploiement Render — hors portée code, documenté).
- 59 issues ouvertes, aucune « trade » ; 3 PRs ouvertes (#8470, #8471, #8472 — cf. §10.2).
- Aucune branche `*trade*` existante (lock libre).
