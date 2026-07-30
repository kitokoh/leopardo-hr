# Architecture — Leopardo HR

> Ce document est la référence d'onboarding pour la structure du monorepo.

## Vue d'ensemble

Leopardo HR est un monorepo multi-stack couvrant :

```
leopardo-hr/
├── api/                    # Backend Laravel (PHP 8.4) — cœur métier HRMS
├── front/
│   ├── web/                # Next.js 16 — landing page + dashboard SaaS (déployé sur Vercel)
│   ├── web-offline/        # Next.js — PWA offline-first pour le bridge Edge (http://leopardo.local)
│   ├── admin-dashboard/    # Vue.js 3 — interface super-admin plateforme
│   ├── mobile_apps/        # Flutter — 5 applications mobiles (voir melos.yaml)
│   │   ├── leopardo_core/       # Package partagé (design system, services)
│   │   ├── leopardo_employee/   # App employé
│   │   ├── leopardo_manager/    # App manager/RH
│   │   ├── leopardo_hr/         # App RH dédiée
│   │   └── leopardo_platform_admin/ # App admin plateforme
│   └── zkteco-kiosk/       # Kiosque HTML/JS pour pointage biométrique
├── edge/                   # Bridge on-prem ZKTeco <-> cloud (Caddy, supervisord, install.sh)
├── shared/
│   ├── i18n/               # Traductions partagées (fr, en, ar, tr)
│   └── mediaForMarketing/  # Assets marketing bruts
├── dev-hub/                # Outils/SDK/scripts pour développeurs et intégrateurs externes (inclut dev-hub/openapi/v1.yaml, miroir généré depuis api/openapi.yaml — source canonique — par dev-hub/tools/generate-openapi-sdk.mjs, vérifié en CI par openapi-ci.yml)
├── docs/                   # Documentation technique et stratégique
├── scripts/                # Scripts utilitaires racine (bootstrap, capture screenshots, cleanup)
├── postman/                # Collection Postman de l'API
├── examples/               # Exemples d'usage du SDK
├── assets/ , screenshots/  # Visuels marketing/README (candidats Git LFS — voir docs/architecture/ARCHITECTURE.md)
└── .github/workflows/      # 25 pipelines CI/CD (voir .github/workflows/README.md pour la cartographie)
```

> Cet arbre doit rester synchronisé avec la structure réelle du repo. En cas de doute, vérifier avec `find . -maxdepth 2 -not -path '*/node_modules/*'`.

## Backend — Domain-Driven Design

Le backend suit une **architecture modulaire DDD** avec deux couches en cours d'unification :

### Modules DDD (cible — `api/app/Modules/`)
Chaque module respecte la structure :
```
Modules/<Name>/
├── Application/
│   ├── Actions/        # Cas d'usage (Command pattern)
│   └── DTOs/           # Objets de transfert internes
├── Domain/
│   ├── Models/         # Entités Eloquent de domaine
│   ├── Exceptions/     # Exceptions métier
│   └── Contracts/      # Interfaces de domaine
├── Infrastructure/
│   └── Services/       # Implémentations, intégrations externes
├── Interfaces/
│   └── Api/V1/         # Controllers + Requests + Resources
└── Providers/          # ServiceProvider du module
```

Modules actifs (19, sous `api/app/Modules/`) : `Absence`, `Attendance`, `Billing`, `Cabinet`, `Cameras`, `EdgeSync`, `Expense`, `Fleet`, `Growth`, `HR`, `Marketing`, `Notification`, `Onboarding`, `Payroll`, `Planning`, `Platform`, `Recruitment`, `SmartAttendance`, `Training` + socle transversal `Core/Auth`, `Core/Tenant`, `Core/Feature` (sous `api/app/Core/`).

> Décompte vérifié via `ls api/app/Modules | wc -l`. Voir `docs/ARCHITECTURE_STATUS.md` pour l'état couche-par-couche (Domain/Application/Infrastructure/Interfaces/Providers/Tests) de chaque module.

### Règle de contribution backend
> **Tout nouveau code métier va dans `api/app/Modules/`.**
> `api/app/Http/Controllers/Api/V1/` a été intégralement supprimé (90 controllers legacy, PR #824, 2026-07-01). `api/app/Services/` a perdu ses 26 doublons legacy mais **n'est pas vide** : il reste des services spécialisés non-DDD (`Cache/`, `Communication/`, `Payroll/`, `SSO/`, `Security/`, `Tracking/`, etc.) + le shim `TenantManager.php`. Voir `api/ARCHITECTURE.md` pour le détail exact.
> `api/app/Models/` a été supprimé (migration DDD terminée) — tout nouveau modèle va dans le module DDD concerné sous `api/app/Modules/<Name>/Domain/Models/`.
> Voir `api/ARCHITECTURE.md` pour la liste complète et les TODOs restants.

### Code partagé transversal (`api/app/Shared/`)

Les éléments utilisés par plusieurs modules vivent dans `api/app/Shared/` :

```
app/Shared/
├── DTOs/
│   └── PaginationDTO.php          # DTO pagination générique
├── Traits/
│   ├── BelongsToCompany.php       # Scope tenant + auto-fill company_id
│   ├── Auditable.php              # Journalisation création/modification/suppression
│   └── Approvable.php             # Workflow d'approbation morphique
├── Attributes/
│   ├── ApiFeature.php             # Métadonnées de feature API
│   ├── RequiresPermission.php     # Permission requise sur une méthode
│   └── MobileCompatible.php       # Compatibilité mobile min/max version
├── Enums/
│   └── ApiError.php               # Codes d'erreur API standardisés
└── Exceptions/
    └── DomainException.php        # Exception métier de base
```

> Les `app/Traits/`, `app/Attributes/` et `app/Enums/` résidus sont des shims de backward-compat pointant vers `Shared/`. Ne pas y écrire de nouveau code.

### Gestion des tenants (`app/Core/Tenant/`)

```
app/Core/Tenant/
└── TenantManager.php          # Singleton — activer/désactiver le contexte company
```

| Méthode | Rôle |
|---------|------|
| `setTenant(Company)` | Active le tenant, met à jour PostgreSQL `search_path` |
| `resetToPrevious()` | Restaure le contexte précédent |
| `withinTenant(Company, Closure)` | Exécute une callback dans un contexte isolé |
| `current()` | Retourne la company active ou null |
| `hasTenant()` | True si un tenant est actif |
| `clearTenant()` | Réinitialise sans restaurer (utile tests/artisan) |

> `App\Services\TenantManager` est un alias de backward-compat. Injecter `App\Core\Tenant\TenantManager` dans le nouveau code.

### Migration class aliases — état au 2026-07-19

`app/Models/` et `app/DTOs/` sont maintenant vides/supprimés (migration DDD terminée pour ces deux dossiers).
Il reste des shims backward-compat dans `app/Traits/` (3 shims), `app/Attributes/` (3 shims) et `app/Enums/` (1 shim),
pointant vers leur équivalent canonique sous `app/Shared/`.

Pour supprimer un alias restant :
1. `grep -r "App\\Traits\\NomDuTrait" app/` (ou `Attributes`/`Enums`) → remplacer par le namespace canonique `App\Shared\...`
2. Supprimer le fichier shim correspondant.

## Mobile — Flutter

- `leopardo_core` est le package fondation partagé par toutes les apps.
- `leopardo_employee`, `leopardo_manager` et `leopardo_hr` utilisent le pattern **Feature-first** avec `data/`, `providers/`, `screens/`.
- Apps actives : `leopardo_core`, `leopardo_employee`, `leopardo_manager`, `leopardo_hr`, `leopardo_platform_admin` (voir `front/mobile_apps/README.md`). Le mobile historique (`front/mobile/`) a été retiré du dépôt.

## i18n

Les traductions vivent dans `shared/i18n/locales/{fr,en,ar,tr}.json`.
Des scripts de synchronisation (`shared/i18n/sync/`) propagent les clés vers le backend Laravel (`api/lang/`) et le mobile (`.arb`).

## CI/CD

Voir `.github/workflows/` et `docs/ARCHITECTURE_CICD.md`.

Les pipelines principaux :
- `tests.yml` — tests backend PHPUnit/Pest + lint/build `front/admin-dashboard`
- `web-ci.yml` — lint + build `front/admin-dashboard` (Vue.js/Vite, dashboard plateforme)
- `web-marketing-ci.yml` — lint + build `front/web` (Next.js, vitrine publique)
- `mobile-apps-ci.yml` — build Flutter (`front/mobile_apps/*`)
- `deploy-main.yml` — déploiement production

## Conventions

Voir `CONVENTIONS.md` pour les règles de nommage, commits, branches et PR.
