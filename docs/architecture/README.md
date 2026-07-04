# Architecture — Leopardo HR API

## Vue d'ensemble

L'API Leopardo HR suit une **Clean Architecture modulaire** basée sur le pattern
Domain-Driven Design (DDD) avec des modules plug-and-play.

## Structure globale

```
api/app/
├── Core/          # Socle transversal (Auth, Tenant)
├── Modules/       # Modules métier indépendants
├── Shared/        # Utilitaires cross-modules
└── [Legacy]       # Ancienne structure flat (en cours de migration)
```

## Documents

| Document | Description |
|---|---|
| [ARCHITECTURE.md](ARCHITECTURE.md) | Vue d'ensemble système (containers, monolithe modulaire, tenancy, workflows) — point d'entrée recommandé |
| [C4_ARCHITECTURE.md](C4_ARCHITECTURE.md) | Diagrammes C4 (contexte, containers, composants) |
| [ADR registry](adr/README.md) | Toutes les Architecture Decision Records (0001-0007) |
| [ADR 0005](adr/0005-clean-architecture-modules.md) | Pourquoi Clean Architecture |
| [ADR 0006](adr/0006-auth-in-core.md) | Pourquoi Auth est dans Core |
| [ADR 0007](adr/0007-progressive-migration-strategy.md) | Stratégie de migration progressive |
| [module-creation-guide.md](module-creation-guide.md) | Créer un nouveau module en 5 min |
| [namespace-map.md](namespace-map.md) | Mapping ancien → nouveau namespace |

## Principes fondamentaux

1. **Les modules ne s'importent jamais entre eux** — communication via `Shared/Events`
2. **Core est sacré** — aucun module ne modifie `app/Core/`
3. **Les originaux survivent** — pendant la migration, les anciens fichiers restent fonctionnels
4. **Stub d'abord** — tout nouveau module part de `api/stubs/module-template/`
