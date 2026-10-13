# TAD — Technical Architecture Document

**Statut :** canonique · **Base :** `main` au 2026-10-13 · **Issue :** #8444

## 1. Vue d'ensemble

Leopardo est un **Business OS multi-tenant** pour les entreprises de terrain
(restaurants, stations-service, retail, santé, éducation, travel, VTC…). Une
plateforme centrale fournit les capacités transversales (identité, tenants,
rôles, RH, présence, paie, CRM, marketing, comptabilité, documents,
notifications, audit) ; des **solutions verticales** activables ajoutent les
workflows propres à chaque métier.

Principes directeurs :

1. **Plateforme + solutions** — les verticales ne dupliquent jamais les modules
   transversaux (cf. `docs/specifications/PLATFORM_ONBOARDING_AND_VERTICAL_SOLUTIONS.md`).
2. **DDD modulaire** — tout code métier vit dans `api/app/Modules/<Name>/`
   (Application / Domain / Infrastructure / Interfaces / Providers).
3. **Tenant-safe & fail-closed** — le tenant est résolu côté serveur à chaque
   requête ; une permission ou un module non validé reste désactivé.
4. **Offline-first pour le terrain** — le service ne s'arrête pas sans
   internet : bridge Edge local, PWA offline, files d'attente et sync.
5. **Multilingue natif** — fr / en / ar / tr partout (web, mobile, API),
   zéro chaîne en dur (garde CI `PA2-I18N-014`).

## 2. Cartographie du monorepo

```
leopardo-hr/
├── api/                  Laravel (PHP 8.4) — cœur métier, API REST v1
├── front/
│   ├── web/              Next.js 16 — vitrine + dashboard tenant (Vercel)
│   ├── web-offline/      Next.js — PWA offline-first du bridge Edge (leopardo.local)
│   ├── admin-dashboard/  Vue 3 — super-admin plateforme
│   ├── marketplace/      Next.js — marketplace publique
│   ├── travel-web/       Next.js — front TravelAgency
│   ├── mobile_apps/      Flutter — 8 apps + package leopardo_core (melos)
│   └── zkteco-kiosk/     Kiosque HTML/JS — pointage biométrique ZKTeco
├── edge/                 Bridge on-prem ↔ cloud (Caddy, supervisord, install.sh)
├── shared/i18n/          Traductions partagées (fr, en, ar, tr)
├── dev-hub/              SDK + OpenAPI (openapi/v1.yaml généré, vérifié en CI)
├── docs/                 Documentation (dont ce référentiel)
└── .github/workflows/    ~72 pipelines CI/CD
```

## 3. Backend — Laravel DDD

### 3.1 Structure d'un module

```
api/app/Modules/<Name>/
├── Application/    Actions (Command pattern) + DTOs
├── Domain/         Models Eloquent, Exceptions, Contracts
├── Infrastructure/ Services, intégrations externes
├── Interfaces/     Api/V1 (Controllers, Requests, Resources)
└── Providers/      ServiceProvider du module
```

35 modules actifs (vérifié 2026-10-13) : `Absence`, `Accounting`, `Attendance`,
`Billing`, `Cabinet`, `Cameras`, `Catalog`, `Communication`, `CRM`, `Delivery`,
`EdgeSync`, `EduManager`, `Expense`, `Fleet`, `FuelStation`, `Fundraising`,
`Geo`, `Growth`, `HealthManager`, `HospitalityManager`, `HR`, `Marketing`,
`Notification`, `Onboarding`, `Payroll`, `Pharmacy`, `Planning`, `Platform`,
`Recruitment`, `Restaurant`, `RestaurantManager`, `Retail`, `Showcase`,
`TravelAgency`, `Vtc` — plus le socle `Core/` (`Auth`, `Tenant`, `Feature`,
`AI`, `Solutions`, `Privacy`, `Seed`…).

Règles strictes (cf. `api/ARCHITECTURE.md`) : les couches legacy
(`Http/Controllers/Api/V1`, `Services/`, `Models/` à la racine de `app/`) ont
été supprimées ; tout nouveau code va dans les modules DDD.

### 3.2 Multi-tenancy

- **Deux bases logiques** : schéma `public` (plateforme : tenants, abonnements,
  catalogue de solutions) et schéma `tenant` (données métier, 431 migrations
  au 2026-10-13), plus une base `edge` pour le bridge local.
- Résolution du tenant **côté serveur uniquement** (`Core/Tenant`,
  `TenantManager`) ; jamais depuis un identifiant fourni par le client sans
  vérification.
- Isolation : `company_id` obligatoire sur les données métier ; scopes
  globaux Eloquent ; purge tenant classifiée (tables publiques fundraising /
  marketplace, #8426).
- Migrations : `php artisan leopardo:migrate` exclusivement.

### 3.3 Provisioning & solutions verticales

`TenantProvisioningOrchestrator` transforme une intention d'onboarding
confirmée en tenant configuré. Propriétés exigées : **idempotent, rejouable,
audité, fail-closed, tenant-safe, observable, réversible** (détail dans la
spec plateforme). `SolutionCatalogue` + `SolutionActivator` +
`SolutionPermissionInstaller` activent une verticale (rôles, permissions,
seeds, feature flags) sans copier les modules transversaux.

### 3.4 API

- REST versionnée `/api/v1`, Resources Laravel, Requests de validation par
  endpoint.
- Source canonique OpenAPI : `api/openapi.yaml` → miroir généré
  `dev-hub/openapi/v1.yaml` (CI `openapi-ci.yml`).
- Collection Postman : `postman/`.

## 4. Frontends

| Surface | Stack | Rôle |
|---|---|---|
| `front/web` | Next.js 16 (App Router), React, Tailwind, framer-motion | Vitrine + dashboard tenant (tous les espaces métier : restaurant, fuel, travel, VTC…) |
| `front/web-offline` | Next.js PWA | Interface servie par le bridge Edge en local, offline-first |
| `front/admin-dashboard` | Vue 3, Pinia, composables | Super-admin plateforme (tenants, solutions, subscriptions, support) |
| `front/marketplace` | Next.js | Marketplace publique |
| `front/travel-web` | Next.js | Front TravelAgency |
| `front/mobile_apps` | Flutter (melos), `leopardo_core` partagé | 8 apps : employee, manager, hr, marketing, accounting, cameras, platform_admin, travel_agent |
| `front/zkteco-kiosk` | HTML/JS | Pointage biométrique sur kiosque |

Internationalisation : catalogues JSON partagés (`shared/i18n/`,
`front/*/i18n`, `*.arb` Flutter) — fr, en, ar, tr (RTL inclus).

## 5. Offline & Edge

Le terrain doit fonctionner **sans internet** :

- `edge/` : bridge on-prem (Caddy, supervisord, `install.sh`) exposant
  `http://leopardo.local`, synchronisé avec le cloud dès que le réseau
  revient (module `EdgeSync`).
- `front/web-offline/` : PWA servie par l'Edge.
- Files d'attente locales + sync **idempotente** (déduplication par clé) :
  pointages biométriques ZKTeco aujourd'hui ; ventes POS et tickets cuisine
  en extension (#8448, #8449).

## 6. Sécurité

- RBAC : rôles plateforme (`PlatformRole`, `PlatformPermission`) et rôles
  tenant par solution ; attribution par `SolutionPermissionInstaller`.
- Auth : Laravel Sanctum (API tokens) ; SSO Google côté vitrine.
- Audit : décisions de provisioning, accès sensibles et compensations tracés
  (sans secrets) avec correlation ID.
- Secrets : baseline `detect-secrets` (`.secrets.baseline`), Trivy
  (`.trivyignore.yaml`), pre-commit hooks.
- RGPD : registre `docs/RGPD_REGISTRE_TRAITEMENTS.md`, module `Core/Privacy`.
- Politique sécurité : `SECURITY.md`.

## 7. CI/CD & déploiement

- ~72 workflows (`.github/workflows/README.md`) : lint, PHPStan strict,
  tests (PHPUnit/Pest, Vitest, Flutter), gardes structure de modules, garde
  i18n, OpenAPI diff, scans sécurité, déploiements.
- **Branch protection sur `main`** (`.github/BRANCH_PROTECTION_REQUIRED.md`) :
  PR obligatoire, checks verts, CODEOWNERS.
- Déploiement : API et services via Render (`render.yaml`,
  `render.prod.yaml`) ; fronts Next.js via Vercel ; `docker-compose.yml`
  pour l'environnement local ; `site/` pour GitHub Pages.
- Changelog discipliné : entrée `CHANGELOG.md` par PR.

## 8. Décisions structurantes (extraits ADR)

- ADR 0015 — étapes d'onboarding canoniques (`docs/architecture/adr/`).
- Plateforme + solutions verticales (spec PLATFORM_ONBOARDING…).
- Unification DDD : suppression des couches legacy (#824, #1728).
- Classification des tables publiques pour la purge tenant (#8426).

## 9. Limites connues et chantiers

- KDS cuisine et POS offline-first : à durcir (#8448, #8449).
- Hub « Mon Logiciel » côté tenant avec 3D : à construire (#8447).
- Rotations multi-sites : généralisation au-delà de Restaurant/FuelStation (#8450).
