# Architecture — Leopardo HR

> Ce document est la référence d'onboarding pour la structure du monorepo.

## Vue d'ensemble

Leopardo HR est un monorepo multi-stack couvrant :

```
leopardo-hr/
├── api/                    # Backend Laravel (PHP 8.4) — cœur métier HRMS
├── front/
│   ├── web/                # Next.js 14 — landing page + dashboard SaaS
│   ├── admin-dashboard/    # Vue.js 3 — interface super-admin plateforme
│   ├── mobile_apps/        # Flutter — 4 applications mobiles
│   │   ├── leopardo_core/       # Package partagé (design system, services)
│   │   ├── leopardo_employee/   # App employé
│   │   ├── leopardo_manager/    # App manager/RH
│   │   └── leopardo_platform_admin/ # App admin plateforme
│   └── zkteco-kiosk/       # Kiosque HTML/JS pour pointage biométrique
├── shared/i18n/            # Traductions partagées (fr, en, ar, tr)
├── openapi/                # Spécification OpenAPI 3.x
├── docs/                   # Documentation technique et stratégique
└── .github/workflows/      # 25+ pipelines CI/CD
```

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

Modules actifs (21) : `Absence`, `Attendance`, `Billing`, `Cabinet`, `Cameras`, `EdgeSync`, `Expense`, `Fleet`, `Growth`, `HR`, `Notification`, `Onboarding`, `Payroll`, `Planning`, `Platform`, `Recruitment`, `SmartAttendance`, `Training` + `Core/Auth`, `Core/Tenant`

### Règle de contribution backend
> **Tout nouveau code métier va dans `api/app/Modules/`.**
> `api/app/Http/Controllers/Api/V1/` et `api/app/Services/` sont **supprimés** (PR #824, 2026-07-01).
> `api/app/Models/` est en cours de migration — ne pas y ajouter de nouveau modèle.
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

### Migration en cours — class aliases (`app/Models/`)

75 des 92 modèles de `app/Models/` sont des shims `class_alias` pointant vers leur module DDD canonique.
Les 17 modèles restants (Company, Employee-link, AI*, SuperAdmin, etc.) n'ont pas encore de module dédié.

Pour supprimer un alias :
1. `grep -r "App\\\\Models\\\\NomDuModel" app/` → remplacer par le namespace canonique
2. Supprimer le fichier shim dans `app/Models/`

Même pattern pour `app/DTOs/` (3 shims), `app/Traits/` (3 shims), `app/Attributes/` (3 shims), `app/Enums/` (1 shim).

## Mobile — Flutter

- `leopardo_core` est le package fondation partagé par les trois apps.
- `leopardo_employee` et `leopardo_manager` utilisent le pattern **Feature-first** avec `data/`, `providers/`, `screens/`.
- `leopardo_mobile_legacy` est archivé — ne pas y contribuer.

## i18n

Les traductions vivent dans `shared/i18n/locales/{fr,en,ar,tr}.json`.
Des scripts de synchronisation (`shared/i18n/sync/`) propagent les clés vers le backend Laravel (`api/lang/`) et le mobile (`.arb`).

## CI/CD

Voir `.github/workflows/` et `docs/ARCHITECTURE_CICD.md` (à venir).

Les pipelines principaux :
- `tests.yml` — tests backend PHPUnit/Pest
- `web-ci.yml` — lint + test Next.js
- `mobile-apps-ci.yml` — build Flutter
- `deploy-main.yml` — déploiement production

## Conventions

Voir `CONVENTIONS.md` pour les règles de nommage, commits, branches et PR.
