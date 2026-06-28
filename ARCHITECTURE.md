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

Modules actuels : `Attendance`, `Billing`, `Cabinet`, `Cameras`, `Fleet`, `HR`, `Payroll`, `Planning`, `Recruitment`

### Règle de contribution backend
> **Tout nouveau code métier va dans `api/app/Modules/`.**
> Les dossiers `api/app/Http/Controllers/Api/V1/`, `api/app/Services/` et `api/app/Models/`
> sont en cours de migration vers leurs modules respectifs. Ne pas y ajouter de nouveau code.

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
